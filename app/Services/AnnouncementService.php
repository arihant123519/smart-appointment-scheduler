<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Messaging\MessageService;
use App\Support\WhatsappTemplate;
use Illuminate\Support\Collection;

/**
 * Resolves a broadcast's target audience and delivers it (email / WhatsApp).
 * Used both for immediate sends and for scheduled ones dispatched by the
 * `announcements:dispatch` command.
 */
class AnnouncementService
{
    public function __construct(private MessageService $messages)
    {
    }

    /**
     * The recipients for an announcement, resolved at send time so a scheduled
     * "scheduled appointment" broadcast reflects whoever is booked by then.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(Announcement $announcement): Collection
    {
        $clinicId = $announcement->clinic_id;

        $base = User::query()
            ->where('is_active', true)
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId));

        return match ($announcement->audience) {
            'providers' => (clone $base)->role('provider')->get(),
            'all' => (clone $base)->get(),
            'scheduled' => $this->scheduledAppointmentPatients($clinicId),
            'custom' => $this->filteredRecipients($announcement, $clinicId),
            default => (clone $base)->role('patient')->get(), // 'patients'
        };
    }

    /**
     * Recipients for a 'custom' audience: patients whose appointments match
     * the announcement's stored filter criteria, unioned with any manually
     * selected user ids. If no appointment-level criteria are set, only the
     * manually selected users are returned (no unintended "everyone" fallback).
     *
     * @return Collection<int, User>
     */
    private function filteredRecipients(Announcement $announcement, ?int $clinicId): Collection
    {
        $f = $announcement->filters ?? [];
        $filterClinicId = $f['clinic_id'] ?? $clinicId;

        $apptCriteriaKeys = ['service_id', 'provider_id', 'status', 'risk_min', 'risk_max', 'date', 'date_from', 'date_to'];
        $hasApptCriteria = ! empty(array_filter(
            array_intersect_key($f, array_flip($apptCriteriaKeys)),
            fn ($v) => $v !== null && $v !== ''
        ));

        $patientIds = collect();
        if ($hasApptCriteria) {
            $patientIds = Appointment::query()
                ->when($filterClinicId, fn ($q) => $q->where('clinic_id', $filterClinicId))
                ->when($f['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))
                ->when($f['provider_id'] ?? null, fn ($q, $v) => $q->where('provider_id', $v))
                ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
                ->when(isset($f['risk_min']) && $f['risk_min'] !== '', fn ($q) => $q->where('no_show_score', '>=', $f['risk_min']))
                ->when(isset($f['risk_max']) && $f['risk_max'] !== '', fn ($q) => $q->where('no_show_score', '<=', $f['risk_max']))
                ->when($f['date'] ?? null, fn ($q, $v) => $q->whereDate('start_at', $v))
                ->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereDate('start_at', '>=', $v))
                ->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereDate('start_at', '<=', $v))
                ->pluck('patient_id')->unique();
        }

        $manualIds = collect($f['user_ids'] ?? []);

        return User::whereIn('id', $patientIds->merge($manualIds)->unique())
            ->where('is_active', true)->get();
    }

    /** @return Collection<int, User> */
    private function scheduledAppointmentPatients(?int $clinicId): Collection
    {
        $patientIds = Appointment::upcoming()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->pluck('patient_id')
            ->unique();

        return User::whereIn('id', $patientIds)->where('is_active', true)->get();
    }

    /**
     * Deliver an announcement to its audience and mark it sent.
     * Returns the number of recipients.
     */
    public function dispatch(Announcement $announcement): int
    {
        $recipients = $this->recipientsFor($announcement);

        // The announcement can target one or both channels (e.g. "email,whatsapp").
        $channels = array_filter(explode(',', (string) $announcement->channel));

        // WhatsApp uses the announcement's own Gupshup template (entered with it).
        $templateId = $announcement->wa_template_id;
        $variables = $announcement->wa_variables ?: ['message'];
        $useTemplate = in_array('whatsapp', $channels, true) && $templateId;

        foreach ($recipients as $user) {
            foreach ($channels as $channel) {
                if ($channel === 'whatsapp' && $useTemplate) {
                    $params = WhatsappTemplate::resolveParams($variables, [
                        'message' => $announcement->body,
                        'patient_name' => (string) ($user->name ?? ''),
                        'clinic_name' => (string) (optional($user->clinic)->name ?? ''),
                    ]);
                    $this->messages->sendWhatsappTemplate($user, $templateId, $params, null, null, 'announcement', 'broadcast');
                } else {
                    $this->messages->send($user, $announcement->title, $announcement->body, $channel, null, 'announcement', 'broadcast');
                }
            }
        }

        $announcement->update([
            'recipients_count' => $recipients->count(),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $recipients->count();
    }
}
