<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only by design — an audit trail that can be edited or deleted
 * through its own viewer defeats the point of being an audit trail. Only
 * `index` exists; do not add store/update/destroy here.
 */
class AuditLogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/audit-logs/index', [
            'auditLogs' => AuditLog::with('user')->latest()->get(),
        ]);
    }
}
