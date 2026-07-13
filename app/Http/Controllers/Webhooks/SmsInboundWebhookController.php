<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SmsBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives inbound SMS from Gupshup, authenticated the same way as the
 * WhatsApp webhook (shared-secret `?token=` or `X-Webhook-Token` header).
 * Each clinic has its own Gupshup SMS account and webhook secret (Settings →
 * Integrations); the secret is matched against every clinic's stored value
 * to both authenticate the request and identify which clinic it belongs to
 * — see {@see Setting::resolveClinicBySecret()}. Payload field paths mirror
 * the WhatsApp webhook's best guess at Gupshup's general inbound shape;
 * verify against a live Gupshup SMS dashboard/sandbox if a real message ever
 * fails to parse, same caveat as GupshupWebhookController.
 */
class SmsInboundWebhookController extends Controller
{
    public function handle(Request $request, SmsBookingService $sms): JsonResponse
    {
        $provided = (string) ($request->header('X-Webhook-Token') ?? $request->query('token', ''));
        $clinicId = Setting::resolveClinicBySecret('sms.inbound_webhook_secret', $provided);

        if ($clinicId === null) {
            abort(403);
        }

        $phone = (string) ($request->input('payload.sender.phone')
            ?? $request->input('payload.source')
            ?? $request->input('from')
            ?? $request->input('mobile')
            ?? '');

        $text = (string) ($request->input('payload.payload.text')
            ?? $request->input('payload.text')
            ?? $request->input('text')
            ?? $request->input('message')
            ?? '');

        if ($phone !== '' && $text !== '') {
            try {
                $sms->handleInbound($phone, $text, $clinicId);
            } catch (\Throwable $e) {
                Log::channel('stack')->error('[SmsInboundWebhook] handleInbound failed: '.$e->getMessage());
            }
        }

        return response()->json(['ok' => true]);
    }
}
