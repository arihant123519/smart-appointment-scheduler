<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Services\Messaging\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::with('creator')->latest()->get();

        return view('announcements.index', compact('announcements'));
    }

    public function store(Request $request, MessageService $messages): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'channel' => ['required', 'in:email,whatsapp'],
        ]);

        $clinicId = auth()->user()->clinic_id;

        // Broadcast to all patients in the clinic.
        $patients = User::role('patient')
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where('is_active', true)
            ->get();

        foreach ($patients as $patient) {
            $messages->send($patient, $data['title'], $data['body'], $data['channel']);
        }

        Announcement::create([
            'clinic_id' => $clinicId,
            'created_by' => auth()->id(),
            'title' => $data['title'],
            'body' => $data['body'],
            'channel' => $data['channel'],
            'recipients_count' => $patients->count(),
            'sent_at' => now(),
        ]);

        return back()->with('success', "Announcement sent to {$patients->count()} patient(s).");
    }
}
