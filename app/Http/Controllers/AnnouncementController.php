<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Support\WhatsappTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::with('creator')->latest()->get();
        $audiences = Announcement::AUDIENCES;

        // Tokens + a sensible default mapping for the inline WhatsApp template.
        $tokens = WhatsappTemplate::tokens();
        $defaultVariables = ['patient_name', 'message'];

        return view('announcements.index', compact('announcements', 'audiences', 'tokens', 'defaultVariables'));
    }

    public function store(Request $request, AnnouncementService $service): RedirectResponse
    {
        $data = $this->validateAnnouncement($request);

        $sendAt = $this->deriveSendAt($data);
        $scheduled = $sendAt !== null;

        $announcement = Announcement::create([
            'clinic_id' => auth()->user()->clinic_id,
            'created_by' => auth()->id(),
            'title' => $data['title'],
            'body' => $data['body'],
            'channel' => implode(',', $data['channels']),
            'audience' => $data['audience'],
            'recipients_count' => 0,
            'send_at' => $scheduled ? $sendAt : null,
            'status' => $scheduled ? 'scheduled' : 'sent',
            'sent_at' => null,
        ] + $this->waFields($data));

        if ($scheduled) {
            return back()->with('success',
                'Announcement scheduled for '.$sendAt->format('M j, Y g:i A').' to '.$announcement->audience_label.'.');
        }

        $count = $service->dispatch($announcement);

        return back()->with('success', "Announcement sent to {$count} recipient(s) ({$announcement->audience_label}).");
    }

    public function edit(Announcement $announcement): View
    {
        $audiences = Announcement::AUDIENCES;
        $tokens = WhatsappTemplate::tokens();

        return view('announcements.edit', compact('announcement', 'audiences', 'tokens'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validateAnnouncement($request);

        $announcement->fill([
            'title' => $data['title'],
            'body' => $data['body'],
            'channel' => implode(',', $data['channels']),
            'audience' => $data['audience'],
        ] + $this->waFields($data));

        // A new offset reschedules the announcement (works even for a previously
        // sent one — i.e. "modify and resend later"). Leaving it blank keeps the
        // current schedule / sent record untouched.
        $sendAt = $this->deriveSendAt($data);
        if ($sendAt !== null) {
            $announcement->send_at = $sendAt;
            $announcement->status = 'scheduled';
            $announcement->sent_at = null;
            $announcement->recipients_count = 0;
        }

        $announcement->save();

        $msg = $announcement->status === 'scheduled'
            ? 'Announcement updated — scheduled for '.$announcement->send_at->format('M j, Y g:i A').'.'
            : 'Announcement updated.';

        return redirect()->route('announcements.index')->with('success', $msg);
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $wasScheduled = $announcement->status === 'scheduled';
        $announcement->delete();

        return redirect()->route('announcements.index')
            ->with('success', $wasScheduled ? 'Scheduled announcement cancelled.' : 'Announcement deleted.');
    }

    /**
     * Shared validation for create/update.
     *
     * @return array<string, mixed>
     */
    private function validateAnnouncement(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,whatsapp'],
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            // WhatsApp template (required only when WhatsApp is one of the channels).
            'wa_template_id' => [
                'nullable', 'string', 'max:255',
                Rule::requiredIf(fn () => in_array('whatsapp', (array) $request->input('channels', []), true)),
            ],
            'wa_namespace' => ['nullable', 'string', 'max:255'],
            'wa_variables' => ['nullable', 'array'],
            'wa_variables.*' => ['nullable', Rule::in(WhatsappTemplate::tokenKeys())],
            'delay_unit' => ['nullable', 'in:hours,days'],
            'delay_value' => [
                'nullable', 'integer', 'min:1',
                // Hours basis: max 24. Days basis: max 30.
                function ($attribute, $value, $fail) use ($request) {
                    $unit = $request->input('delay_unit', 'hours');
                    $max = $unit === 'days' ? 30 : 24;
                    if ((int) $value > $max) {
                        $fail("On a {$unit} basis, the value cannot be more than {$max}.");
                    }
                },
            ],
        ]);
    }

    /**
     * The per-announcement WhatsApp template fields, normalised.
     *
     * @return array{wa_template_id: ?string, wa_namespace: ?string, wa_variables: array<int, string>}
     */
    private function waFields(array $data): array
    {
        return [
            'wa_template_id' => $data['wa_template_id'] ?? null,
            'wa_namespace' => $data['wa_namespace'] ?? null,
            'wa_variables' => array_values(array_filter($data['wa_variables'] ?? [])),
        ];
    }

    /** Turn the hours/days offset into an absolute send time (null = send now). */
    private function deriveSendAt(array $data): ?\Illuminate\Support\Carbon
    {
        if (empty($data['delay_value'])) {
            return null;
        }

        $unit = $data['delay_unit'] ?? 'hours';

        return $unit === 'days'
            ? now()->addDays((int) $data['delay_value'])
            : now()->addHours((int) $data['delay_value']);
    }
}
