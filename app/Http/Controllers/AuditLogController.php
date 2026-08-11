<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $query = AuditLog::with('user')->orderByDesc('created_at');

        // AuditLog has no clinic_id of its own — scope indirectly via the
        // acting user's clinic (system_admin sees every clinic's trail).
        if (! $user->hasRole('system_admin')) {
            $query->whereHas('user', fn ($q) => $q->where('clinic_id', $user->clinic_id));
        }

        $logs = $query->limit(500)->get();

        return view('audit.index', compact('logs'));
    }
}
