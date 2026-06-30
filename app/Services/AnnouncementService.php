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
            default => (clone $base)->role('patient')->get(), // 'patients'
        };
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
                    $this->messages->sendWhatsappTemplate($user, $templateId, $params);
                } else {
                    $this->messages->send($user, $announcement->title, $announcement->body, $channel);
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
