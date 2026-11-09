<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An uploaded .docx format. The file is never replaced: changing a format
 * means uploading a new template, so documents already issued keep theirs.
 */
class DocumentTemplate extends Model
{
    public const SURAT_JALAN = 'SURAT_JALAN';

    public const TANDA_TERIMA = 'TANDA_TERIMA';

    public const INVOICE = 'INVOICE';

    public const TYPES = [self::SURAT_JALAN, self::TANDA_TERIMA, self::INVOICE];

    protected $fillable = [
        'name',
        'document_type',
        'file_path',
        'original_filename',
        'placeholders',
        'is_active',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @param  array<int, string>  $placeholders
     */
    public function usesAny(array $placeholders): bool
    {
        return array_intersect($placeholders, $this->placeholders) !== [];
    }

    /**
     * @param  array<int, string>  $placeholders
     */
    public function usesAll(array $placeholders): bool
    {
        return array_diff($placeholders, $this->placeholders) === [];
    }
}
