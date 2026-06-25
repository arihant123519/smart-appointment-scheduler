<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $logs = AuditLog::with('user')->orderByDesc('created_at')->limit(500)->get();

        return view('audit.index', compact('logs'));
    }
}
