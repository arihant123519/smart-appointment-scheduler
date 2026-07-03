<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Provider;
use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Global in-app AI assistant (floating widget). Turns a natural-language
 * instruction — "take me to the patients list", "create a new clinic" — into a
 * navigation action, scoped to what the current user is allowed to access.
 *
 * The AI only ever picks from a permission-filtered catalog, so it can never
 * route a user somewhere they lack permission for.
 */
class AssistantController extends Controller
{
    /**
     * Master catalog of navigable destinations.
     * Each: route name, label, required permission (null = any signed-in user),
     * and keywords used by the rule-based fallback.
     *
     * @return array<int, array{key:string, label:string, route:string, permission:?string, keywords:array<int,string>}>
     */
    private function catalog(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'permission' => null, 'keywords' => ['home', 'dashboard', 'overview']],
            ['key' => 'ai.booking', 'label' => 'AI Assistant', 'route' => 'ai.booking', 'permission' => null, 'keywords' => ['ai', 'assistant', 'chat']],
            ['key' => 'booking.create', 'label' => 'Book an Appointment', 'route' => 'booking.create', 'permission' => null, 'keywords' => ['book', 'appointment', 'schedule visit']],
            ['key' => 'notifications.index', 'label' => 'Notifications', 'route' => 'notifications.index', 'permission' => null, 'keywords' => ['notification', 'alerts', 'bell']],
            ['key' => 'profile.edit', 'label' => 'My Profile', 'route' => 'profile.edit', 'permission' => null, 'keywords' => ['profile', 'account', 'my settings', 'api token']],

            ['key' => 'calendar', 'label' => 'Calendar', 'route' => 'calendar', 'permission' => 'view appointments', 'keywords' => ['calendar', 'schedule']],
            ['key' => 'appointments.index', 'label' => 'Appointments List', 'route' => 'appointments.index', 'permission' => 'view appointments', 'keywords' => ['appointments', 'visits', 'bookings list']],
            ['key' => 'appointments.create', 'label' => 'New Appointment', 'route' => 'appointments.create', 'permission' => 'manage appointments', 'keywords' => ['new appointment', 'create appointment', 'add appointment']],
            ['key' => 'waitlist.index', 'label' => 'Waitlist', 'route' => 'waitlist.index', 'permission' => 'manage waitlist', 'keywords' => ['waitlist', 'waiting list']],
            ['key' => 'announcements.index', 'label' => 'Broadcast Messaging', 'route' => 'announcements.index', 'permission' => 'manage reminders', 'keywords' => ['broadcast', 'announcement', 'campaign']],

            ['key' => 'patients.index', 'label' => 'Patients List', 'route' => 'patients.index', 'permission' => 'manage patients', 'keywords' => ['patients', 'patient list']],
            ['key' => 'patients.create', 'label' => 'New Patient', 'route' => 'patients.create', 'permission' => 'manage patients', 'keywords' => ['new patient', 'create patient', 'add patient', 'register patient']],
            ['key' => 'providers.index', 'label' => 'Providers List', 'route' => 'providers.index', 'permission' => 'manage providers', 'keywords' => ['providers', 'doctors', 'staff']],
            ['key' => 'providers.create', 'label' => 'New Provider', 'route' => 'providers.create', 'permission' => 'manage providers', 'keywords' => ['new provider', 'create provider', 'add doctor']],
            ['key' => 'services.index', 'label' => 'Services', 'route' => 'services.index', 'permission' => 'manage services', 'keywords' => ['services', 'service catalog', 'appointment types']],
            ['key' => 'services.create', 'label' => 'New Service', 'route' => 'services.create', 'permission' => 'manage services', 'keywords' => ['new service', 'create service', 'add service']],

            ['key' => 'reports.index', 'label' => 'Reports & Analytics', 'route' => 'reports.index', 'permission' => 'view reports', 'keywords' => ['reports', 'analytics', 'stats', 'no-show rate']],
            ['key' => 'reviews.index', 'label' => 'Reviews & Feedback', 'route' => 'reviews.index', 'permission' => 'view reports', 'keywords' => ['reviews', 'feedback', 'ratings']],
            ['key' => 'payments.index', 'label' => 'Billing', 'route' => 'payments.index', 'permission' => 'view billing', 'keywords' => ['billing', 'payments', 'invoices', 'refund']],

            ['key' => 'users.index', 'label' => 'Users', 'route' => 'users.index', 'permission' => 'manage users', 'keywords' => ['users', 'staff accounts']],
            ['key' => 'users.create', 'label' => 'New User', 'route' => 'users.create', 'permission' => 'manage users', 'keywords' => ['new user', 'create user', 'add user', 'add staff']],
            ['key' => 'roles.index', 'label' => 'Roles & Permissions', 'route' => 'roles.index', 'permission' => 'manage roles', 'keywords' => ['roles', 'permissions', 'access control']],
            ['key' => 'roles.create', 'label' => 'New Role', 'route' => 'roles.create', 'permission' => 'manage roles', 'keywords' => ['new role', 'create role', 'add role']],
            ['key' => 'clinics.index', 'label' => 'Clinics', 'route' => 'clinics.index', 'permission' => 'manage clinics', 'keywords' => ['clinics', 'locations', 'branches']],
            ['key' => 'clinics.create', 'label' => 'New Clinic', 'route' => 'clinics.create', 'permission' => 'manage clinics', 'keywords' => ['new clinic', 'create clinic', 'add clinic', 'add location']],
            ['key' => 'audit.index', 'label' => 'Audit Logs', 'route' => 'audit.index', 'permission' => 'view audit logs', 'keywords' => ['audit', 'logs', 'activity']],
            ['key' => 'settings.integrations.edit', 'label' => 'Integration Settings', 'route' => 'settings.integrations.edit', 'permission' => 'manage settings', 'keywords' => ['settings', 'integrations', 'whatsapp', 'sms', 'email config']],
        ];
    }

    /** Catalog filtered to what the current user is permitted to reach. */
    private function permitted(): array
    {
        $user = auth()->user();

        return array_values(array_filter($this->catalog(), function ($d) use ($user) {
            return $d['permission'] === null || $user->can($d['permission']);
        }));
    }

    /**
     * Data questions the assistant can answer, gated by permission.
     *
     * @return array<int, array{type:string, description:string}>
     */
    private function queryTypes(): array
    {
        $types = [];
        if (auth()->user()->can('view appointments')) {
            $types[] = ['type' => 'provider_appointments', 'description' => "appointments of a specific provider/doctor by name"];
            $types[] = ['type' => 'patient_appointments', 'description' => "appointments/visits of a specific patient/client by name"];
            $types[] = ['type' => 'patient_visit_count', 'description' => "how many times a patient/client has visited the clinic"];
        }

        return $types;
    }

    public function command(Request $request, AiService $ai): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:300']]);

        // Self-schedule shortcut — "what appointments do I have today",
        // "aaj meri konsi appointment hai", "my upcoming visits". These refer to
        // the CURRENT user, so answer directly: name extraction would otherwise
        // fail on "my/me/meri" and ask "whose record?".
        if ($self = $this->selfAppointments($data['text'])) {
            return response()->json($self + ['auto' => false]);
        }

        $permitted = $this->permitted();
        $slim = array_map(fn ($d) => [
            'key' => $d['key'], 'label' => $d['label'], 'keywords' => $d['keywords'],
        ], $permitted);

        $result = $ai->assistantCommand($data['text'], $slim, $this->queryTypes());

        // --- Data query → run it and answer in chat (no auto-redirect) --------
        if (($result['action'] ?? null) === 'query') {
            $answer = $this->runQuery($result['query']);

            return response()->json([
                'reply' => $answer['reply'],
                'url' => $answer['url'],
                'label' => $answer['label'],
                'auto' => false,
            ]);
        }

        // --- Navigation → resolve URL and let the widget redirect -------------
        $url = $label = null;
        if (($result['action'] ?? null) === 'navigate' && $result['key']) {
            $dest = collect($permitted)->firstWhere('key', $result['key']);
            if ($dest) {
                $url = route($dest['route']);
                $label = $dest['label'];
            }
        }

        return response()->json([
            'reply' => $result['reply'],
            'url' => $url,
            'label' => $label,
            'auto' => (bool) $url,
        ]);
    }

    /**
     * Detect a question about the CURRENT user's own appointments ("my", "me",
     * "meri/mera/mujhe/apni") and answer it directly. Returns null when the text
     * isn't a self-schedule question, so normal handling continues.
     *
     * @return array{reply:string, url:?string, label:?string}|null
     */
    private function selfAppointments(string $text): ?array
    {
        $lower = mb_strtolower($text);

        // A booking/navigation verb — let the normal flow handle those.
        if (Str::contains($lower, ['book ', 'create ', 'new appointment'])) {
            return null;
        }

        // Strip filler phrases where "me" means "display", not ownership
        // ("show me…", "tell me…") so they aren't read as self-reference.
        $cleaned = preg_replace('/\b(show|tell|give|let|find|get)\s+me\b/u', ' ', $lower);

        // Someone else is referenced (honorific/role or a possessive name) → not
        // a self-question; let the name-based query handle it.
        $mentionsOther = Str::contains($cleaned, ['dr ', 'dr.', 'doctor', 'provider', 'patient', 'client'])
            || (bool) preg_match("/\b[a-z]+'s\b/u", $cleaned);

        $mentionsAppt = Str::contains($lower, [
            'appointment', 'appt', 'visit', 'booking', 'schedule',
        ]);
        // Strong ownership signals (English + romanized Hindi/Urdu).
        $selfStrong = (bool) preg_match('/\b(my|mine)\b/u', $cleaned)
            || Str::contains($cleaned, [
                'meri', 'mera', 'mere', 'mujhe', 'mujhy', 'apni', 'apna', 'apne', 'hamari', 'humari', 'hamare',
            ]);
        // Weak signals ("me", "i") only count when no one else is named.
        $selfWeak = (bool) preg_match('/\b(me|i)\b/u', $cleaned) && ! $mentionsOther;

        if (! $mentionsAppt || ! ($selfStrong || $selfWeak)) {
            return null;
        }

        $range = match (true) {
            Str::contains($lower, ['upcoming', 'future', 'next', 'agli', 'aane wali', 'aanewali']) => 'upcoming',
            Str::contains($lower, ['past', 'previous', 'history', 'pichli', 'pichhli']) => 'past',
            Str::contains($lower, ['today', 'aaj']) => 'today',
            default => 'upcoming',
        };

        return $this->mySchedule($range);
    }

    /**
     * Build the current user's own appointment answer (provider or patient).
     *
     * @return array{reply:string, url:?string, label:?string}
     */
    private function mySchedule(string $range): array
    {
        $user = auth()->user();

        // Provider — their own schedule within the clinic.
        if ($user->provider) {
            $base = $this->applyRange(
                Appointment::forCurrentClinic()->where('provider_id', $user->provider->id), $range
            );
            $count = (clone $base)->count();
            $next = $range === 'past' ? null
                : (clone $base)->with('patient')->orderBy('start_at')->first();

            $reply = "You have {$count} ".$this->rangeWord($range).' appointment(s).';
            if ($next && $next->patient) {
                $reply .= " Next: {$next->patient->name} at ".$next->start_at->format('M j, g:i A').'.';
            }

            $params = ['provider_id' => $user->provider->id];
            if ($range === 'today') {
                $params['date'] = today()->toDateString();
            }

            return ['reply' => $reply, 'url' => route('appointments.index', $params), 'label' => 'my appointments'];
        }

        // Patient — their own appointments (linked to the personal dashboard).
        if ($user->hasRole('patient')) {
            $count = $this->applyRange($user->appointments(), $range)->count();

            return [
                'reply' => "You have {$count} ".$this->rangeWord($range).' appointment(s).',
                'url' => route('dashboard'),
                'label' => 'my appointments',
            ];
        }

        // Staff with no personal calendar.
        return [
            'reply' => "You don't have a personal appointment calendar. Try asking about a specific patient or provider by name — e.g. “Dr. Chen's appointments today”.",
            'url' => null,
            'label' => null,
        ];
    }

    /**
     * Execute a permission-scoped data query.
     *
     * @return array{reply:string, url:?string, label:?string}
     */
    private function runQuery(array $query): array
    {
        if (! auth()->user()->can('view appointments')) {
            return ['reply' => "You don't have permission to view that information.", 'url' => null, 'label' => null];
        }

        $name = trim((string) ($query['name'] ?? ''));
        if ($name === '') {
            return ['reply' => 'Whose record would you like to see? Please include a name.', 'url' => null, 'label' => null];
        }
        $range = $query['range'] ?? 'all';

        return match ($query['type'] ?? null) {
            'provider_appointments' => $this->providerAppointments($name, $range),
            'patient_appointments' => $this->patientAppointments($name, $range),
            'patient_visit_count' => $this->patientVisitCount($name),
            default => ['reply' => "I couldn't run that query.", 'url' => null, 'label' => null],
        };
    }

    private function providerAppointments(string $name, string $range): array
    {
        // Strip honorifics so "Dr. Sarah Lee" matches a stored name of "Sarah Lee".
        $name = trim(preg_replace('/^\s*(dr\.?|doctor|prof\.?|mr\.?|mrs\.?|ms\.?)\s+/i', '', $name));

        $providers = Provider::with('user')->forCurrentClinic()
            ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$name}%"))->get();

        if ($providers->isEmpty()) {
            return ['reply' => "I couldn't find a provider matching “{$name}”.", 'url' => null, 'label' => null];
        }
        if ($providers->count() > 1) {
            $names = $providers->take(5)->map(fn ($p) => $p->name)->implode(', ');

            return ['reply' => "Multiple providers match “{$name}”: {$names}. Please be more specific.", 'url' => null, 'label' => null];
        }

        $provider = $providers->first();
        $count = $this->applyRange(
            Appointment::forCurrentClinic()->where('provider_id', $provider->id), $range
        )->count();

        $params = ['provider_id' => $provider->id];
        if ($range === 'today') {
            $params['date'] = today()->toDateString();
        }

        return [
            'reply' => "{$provider->name} has {$count} ".$this->rangeWord($range)." appointment(s).",
            'url' => route('appointments.index', $params),
            'label' => 'these appointments',
        ];
    }

    private function patientAppointments(string $name, string $range): array
    {
        $patient = $this->findPatient($name);
        if (is_array($patient)) {
            return $patient; // not-found / ambiguous message
        }

        $count = $this->applyRange(
            Appointment::forCurrentClinic()->where('patient_id', $patient->id), $range
        )->count();

        $params = ['q' => $patient->name];
        if ($range === 'today') {
            $params['date'] = today()->toDateString();
        }

        return [
            'reply' => "{$patient->name} has {$count} ".$this->rangeWord($range)." appointment(s).",
            'url' => route('appointments.index', $params),
            'label' => 'their appointments',
        ];
    }

    private function patientVisitCount(string $name): array
    {
        $patient = $this->findPatient($name);
        if (is_array($patient)) {
            return $patient;
        }

        $base = Appointment::forCurrentClinic()->where('patient_id', $patient->id);
        $visits = (clone $base)->where('status', Appointment::STATUS_COMPLETED)->count();
        $total = (clone $base)->count();

        return [
            'reply' => "{$patient->name} has visited {$visits} time(s) (completed). {$total} appointment(s) on record in total.",
            'url' => route('appointments.index', ['q' => $patient->name]),
            'label' => 'their appointments',
        ];
    }

    /** Resolve a patient by name within the current clinic, or return a message array. */
    private function findPatient(string $name): User|array
    {
        $q = User::role('patient')->where('name', 'like', "%{$name}%");
        if ($clinic = auth()->user()->clinic_id) {
            $q->where('clinic_id', $clinic);
        }
        $patients = $q->get();

        if ($patients->isEmpty()) {
            return ['reply' => "I couldn't find a patient matching “{$name}”.", 'url' => null, 'label' => null];
        }
        if ($patients->count() > 1) {
            $names = $patients->take(5)->pluck('name')->implode(', ');

            return ['reply' => "Multiple patients match “{$name}”: {$names}. Please be more specific.", 'url' => null, 'label' => null];
        }

        return $patients->first();
    }

    private function applyRange($query, string $range)
    {
        return match ($range) {
            'upcoming' => $query->where('start_at', '>=', now())
                ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW]),
            'past' => $query->where('start_at', '<', now()),
            'today' => $query->whereDate('start_at', today()),
            default => $query,
        };
    }

    private function rangeWord(string $range): string
    {
        return match ($range) {
            'upcoming' => 'upcoming',
            'past' => 'past',
            'today' => "today's",
            default => 'total',
        };
    }
}
