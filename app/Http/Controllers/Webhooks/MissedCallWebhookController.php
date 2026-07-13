<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Messaging\MessageService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Turns a missed call into a second chance to book (PRD "automatic text-back
 * on a missed call"). Point your telephony provider's / Gupshup's missed-call
 * or call-forwarding webhook at this URL with the caller's number.
 *
 * Fires at most once per phone number per 24 hours — someone calling three
 * times in frustration gets one helpful text, not three. Each clinic has its
 * own webhook secret (Settings → Integrations); it both authenticates the
 * request and identifies which clinic's SMS credentials to text back with —
 * see {@see Setting::resolveClinicBySecret()}.
 */
class MissedCallWebhookController extends Controller
{
    public function handle(Request $request, MessageService $messages): JsonResponse
    {
        $provided = (string) ($request->header('X-Webhook-Token') ?? $request->query('token', ''));
        $clinicId = Setting::resolveClinicBySecret('sms.missed_call_webhook_secret', $provided);

        if ($clinicId === null) {
            abort(403);
        }

        $phone = PhoneNumber::normalize(
            (string) ($request->input('phone') ?? $request->input('caller') ?? $request->input('from') ?? '')
        );

        if ($phone === '') {
            return response()->json(['ok' => true]);
        }

        $alreadySentToday = MessageLog::where('address', $phone)
            ->where('event_key', 'missed_call_text_back')
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($alreadySentToday) {
            return response()->json(['ok' => true, 'skipped' => 'already_sent_today']);
        }

        $user = User::where('phone', $phone)->first();
        $body = 'Sorry we missed your call! Book an appointment anytime here: '.route('booking.create');

        $messages->sendSmsToPhone($phone, $body, $user, null, 'missed_call', 'missed_call_text_back', $clinicId);

        return response()->json(['ok' => true]);
    }
}
