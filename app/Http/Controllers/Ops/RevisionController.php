<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\RevisionGateService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PRD §MODULE 1 Tier 2: Ops reviews late-revision submissions (17:01-22:00
 * WIB) flagged PENDING_APPROVAL by RevisionGateService, then approves or
 * rejects them.
 */
class RevisionController extends Controller
{
    public function index(RevisionGateService $revisionGate): Response
    {
        return Inertia::render('ops/revisions/index', [
            'pendingApprovals' => $revisionGate->pendingApprovals(),
        ]);
    }

    public function approve(AuditLog $revision, RevisionGateService $revisionGate): RedirectResponse
    {
        $revisionGate->approveRevision($revision, request()->user());

        return back()->with('success', 'Revisi disetujui.');
    }

    public function reject(AuditLog $revision, RevisionGateService $revisionGate): RedirectResponse
    {
        $revisionGate->rejectRevision($revision, request()->user());

        return back()->with('success', 'Revisi ditolak dan item terkait dihapus.');
    }
}
