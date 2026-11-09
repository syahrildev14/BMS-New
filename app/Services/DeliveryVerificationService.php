<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DeliveryDocumentPhoto;
use App\Models\SuratJalan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Arrival photos (Dapur) and the koperasi's H+1 crosscheck (Ops). VERIFIED is
 * what SplitInvoiceService requires before an Order can be invoiced and what
 * locks the document's STT. Every status change takes the document row lock,
 * which OrderController::submitStt and SplitInvoiceService also take, so STT,
 * billing and verification can't interleave.
 */
class DeliveryVerificationService
{
    public const MAX_PHOTOS_PER_DOCUMENT = 10;

    /** Below PHP/nginx's 25 MB request limit (docker/php/local.ini, docker/nginx/default.conf). */
    public const MAX_UPLOAD_KB = 20480;

    public function __construct(private readonly BillingGuard $billing) {}

    public function verify(SuratJalan $document, User $user, ?string $notes = null): SuratJalan
    {
        return DB::transaction(function () use ($document, $user, $notes) {
            $document = $this->lock($document);

            // A stale page or repeated POST must not overwrite who verified it.
            if ($document->verification_status === SuratJalan::VERIFICATION_VERIFIED) {
                throw ValidationException::withMessages(['verification' => 'Dokumen ini sudah terverifikasi.']);
            }

            $missing = $document->orderItems()->whereNull('qty_received')->count();

            if ($missing > 0) {
                throw ValidationException::withMessages([
                    'verification' => "Belum bisa diverifikasi: {$missing} barang di dokumen ini belum diisi STT oleh Dapur.",
                ]);
            }

            return $this->transition($document, SuratJalan::VERIFICATION_VERIFIED, $notes, $user);
        });
    }

    /**
     * Record a discrepancy, or reopen a VERIFIED document. Reopening lets the
     * STT change again, so it's refused once billing is final, and DRAFT
     * billing is voided so it can't be approved with the old amount.
     */
    public function markDiscrepancy(SuratJalan $document, User $user, string $notes): SuratJalan
    {
        return DB::transaction(function () use ($document, $user, $notes) {
            $document = $this->lock($document);

            $lines = $document->orderItems()->get(['order_items.id', 'order_id', 'supplier_id']);

            // Only reopening is refused: a document not yet verified can have
            // a real shortage to record even when an earlier document for the
            // same (order, supplier) has already been billed.
            if ($document->verification_status === SuratJalan::VERIFICATION_VERIFIED && $this->billing->hasFinalBilling($lines)) {
                throw ValidationException::withMessages([
                    'verification' => 'Invoice atau tagihan supplier untuk barang di dokumen ini sudah terbit, statusnya tidak bisa diubah lagi.',
                ]);
            }

            $this->billing->voidDrafts($lines);

            return $this->transition($document, SuratJalan::VERIFICATION_DISCREPANCY, $notes, $user);
        });
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function addPhotos(SuratJalan $document, array $files, User $user): void
    {
        $storedPaths = [];

        try {
            DB::transaction(function () use ($document, $files, $user, &$storedPaths) {
                $document = $this->lock($document);
                $this->assertPhotosEditable($document);

                if ($document->photos()->count() + count($files) > self::MAX_PHOTOS_PER_DOCUMENT) {
                    throw ValidationException::withMessages([
                        'photos' => 'Maksimal '.self::MAX_PHOTOS_PER_DOCUMENT.' foto per dokumen.',
                    ]);
                }

                foreach ($files as $file) {
                    $path = $file->store("delivery-photos/{$document->id}", 'local');

                    if ($path === false) {
                        throw ValidationException::withMessages(['photos' => 'Foto gagal disimpan, coba lagi.']);
                    }

                    $storedPaths[] = $path;

                    $document->photos()->create([
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'uploaded_by' => $user->id,
                    ]);
                }
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($storedPaths);

            throw $e;
        }
    }

    public function deletePhoto(DeliveryDocumentPhoto $photo): void
    {
        $path = DB::transaction(function () use ($photo) {
            $this->assertPhotosEditable($this->lock($photo->suratJalan));
            $photo->delete();

            return $photo->path;
        });

        Storage::disk('local')->delete($path);
    }

    private function assertPhotosEditable(SuratJalan $document): void
    {
        if ($document->verification_status === SuratJalan::VERIFICATION_VERIFIED) {
            throw ValidationException::withMessages(['photos' => 'Dokumen sudah terverifikasi, foto tidak bisa diubah.']);
        }
    }

    private function lock(SuratJalan $document): SuratJalan
    {
        return SuratJalan::whereKey($document->id)->lockForUpdate()->firstOrFail();
    }

    private function transition(SuratJalan $document, string $status, ?string $notes, User $user): SuratJalan
    {
        $before = $document->verification_status;

        $document->update([
            'verification_status' => $status,
            'verification_notes' => $notes,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => "DELIVERY_DOCUMENT_{$status}",
            'description' => "Dokumen {$document->sj_number}: {$before} -> {$status}.",
            'old_data' => ['surat_jalan_id' => $document->id, 'verification_status' => $before],
            'new_data' => ['surat_jalan_id' => $document->id, 'verification_status' => $status, 'notes' => $notes],
        ]);

        return $document;
    }
}
