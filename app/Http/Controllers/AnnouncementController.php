<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Clinic;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Support\WhatsappTemplate;
use Illuminate\Http\JsonResponse;
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

        return view('announcements.index', array_merge(
            compact('announcements', 'audiences', 'tokens', 'defaultVariables'),
            $this->filterOptions()
        ));
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
            'filters' => $data['audience'] === 'custom' ? $this->normalizeFilters($data) : null,
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

        return view('announcements.edit', array_merge(
            compact('announcement', 'audiences', 'tokens'),
            $this->filterOptions()
        ));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validateAnnouncement($request);

        $announcement->fill([
            'title' => $data['title'],
            'body' => $data['body'],
            'channel' => implode(',', $data['channels']),
            'audience' => $data['audience'],
            'filters' => $data['audience'] === 'custom' ? $this->normalizeFilters($data) : null,
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

    /**
     * Live audience preview for the custom-filter modal: returns the patients
     * matching whatever filter values are currently set in the form, so the
     * picker list updates as staff adjust service/provider/status/etc. With
     * no criteria set at all, falls back to the full patient list (same as
     * the modal's initial state).
     */
    public function previewAudience(Request $request, AnnouncementService $service): JsonResponse
    {
        $f = $request->only(['service_id', 'provider_id', 'status', 'risk_min', 'risk_max', 'date', 'date_from', 'date_to', 'clinic_id']);
        $f = array_filter($f, fn ($v) => $v !== null && $v !== '');

        $clinicId = auth()->user()->clinic_id;

        $users = empty($f)
            ? $this->audienceUsersQuery()->with('roles')->orderBy('name')->get()
            : User::whereIn('id', $service->patientIdsForFilters($f, $clinicId))
                ->where('is_active', true)->with('roles')->orderBy('name')->get();

        return response()->json([
            'users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'role' => $u->roles->first()?->name,
            ])->values(),
        ]);
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
            'filters' => ['nullable', 'array'],
            'filters.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'filters.provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'filters.status' => ['nullable', Rule::in(array_keys(\App\Models\Appointment::STATUSES))],
            'filters.risk_min' => ['nullable', 'integer', 'between:0,100'],
            'filters.risk_max' => ['nullable', 'integer', 'between:0,100'],
            'filters.date' => ['nullable', 'date'],
            'filters.date_from' => ['nullable', 'date'],
            'filters.date_to' => ['nullable', 'date'],
            'filters.clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'filters.user_ids' => ['nullable', 'array'],
            'filters.user_ids.*' => ['integer', 'exists:users,id'],
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

    /** Dropdown + picker data for the custom-audience filter modal. */
    private function filterOptions(): array
    {
        $filterUsers = $this->audienceUsersQuery()->with('roles')->orderBy('name')->get();

        return [
            'filterServices' => Service::forCurrentClinic()->orderBy('name')->get(),
            'filterProviders' => Provider::forCurrentClinic()->with('user')->get(),
            'filterClinics' => auth()->user()->hasRole('system_admin') ? Clinic::orderBy('name')->get() : collect(),
            'filterUsers' => $filterUsers,
            'filterRoleOptions' => $filterUsers->map(fn ($u) => $u->roles->first()?->name)->filter()->unique()->values(),
        ];
    }

    /**
     * The base set of users eligible for the "custom" audience: for a clinic
     * admin (or other non-system-admin staff), every active patient, provider,
     * billing, and front-desk user in their own clinic; for a system admin,
     * every active user across every clinic.
     */
    private function audienceUsersQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        $query = User::query()->where('is_active', true);

        if ($user->hasRole('system_admin')) {
            return $query;
        }

        return $query->where('clinic_id', $user->clinic_id)
            ->role(['patient', 'provider', 'billing', 'front_desk']);
    }

    /** Strip empty/null entries from the submitted filters.* inputs. */
    private function normalizeFilters(array $data): array
    {
        $f = $data['filters'] ?? [];

        return array_filter([
            'service_id' => $f['service_id'] ?? null,
            'provider_id' => $f['provider_id'] ?? null,
            'status' => $f['status'] ?? null,
            'risk_min' => $f['risk_min'] ?? null,
            'risk_max' => $f['risk_max'] ?? null,
            'date' => $f['date'] ?? null,
            'date_from' => $f['date_from'] ?? null,
            'date_to' => $f['date_to'] ?? null,
            'clinic_id' => $f['clinic_id'] ?? null,
            'user_ids' => array_values(array_filter($f['user_ids'] ?? [])),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
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
