<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\Setting;
use App\Models\WhatsappConversation;
use App\Services\Flows\FlowEngine;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives inbound WhatsApp traffic from Gupshup: patient replies (advances an
 * active WhatsappConversation via FlowEngine) and delivery-status callbacks
 * (best-effort MessageLog status updates). Gupshup does not sign its webhook
 * payloads, so this route is authenticated by a shared-secret `?token=` query
 * parameter instead of CSRF/session auth. Each clinic has its own Gupshup
 * account and webhook secret (Settings → Integrations); the secret is matched
 * against every clinic's stored value to authenticate the request — see
 * {@see Setting::resolveClinicBySecret()}.
 *
 * Payload shapes below follow Gupshup's general Messages/Events webhook
 * format; the exact field paths should be verified against a live Gupshup
 * sandbox/dashboard if delivery ever looks wrong.
 *
 * The shared secret may arrive either as a `?token=` query parameter or as an
 * `X-Webhook-Token` header — Gupshup's dashboard rejects callback URLs that
 * contain a query string ("Invalid URL Passed"), so the header is the only
 * option when registering the webhook through their UI.
 */
class GupshupWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $provided = (string) ($request->header('X-Webhook-Token') ?? $request->query('token', ''));

        if (Setting::resolveClinicBySecret('whatsapp.gupshup_webhook_secret', $provided) === null) {
            abort(403);
        }

        return match ($request->input('type')) {
            'message' => $this->handleInbound($request),
            'message-event' => $this->handleDeliveryEvent($request),
            default => response()->json(['ok' => true]),
        };
    }

    protected function handleInbound(Request $request): JsonResponse
    {
        $rawPhone = $request->input('payload.sender.phone') ?? $request->input('payload.source');
        $text = $this->extractInboundText($request);
        $providerMessageId = $request->input('payload.id');

        $phone = PhoneNumber::normalize($rawPhone);
        if ($phone === '') {
            return response()->json(['ok' => true]);
        }

        $conversation = WhatsappConversation::active()
            ->where('phone', $phone)
            ->latest('id')
            ->first();

        $log = MessageLog::create([
            'direction' => 'inbound',
            'channel' => 'whatsapp',
            'status' => 'received',
            'appointment_id' => $conversation?->appointment_id,
            'conversation_id' => $conversation?->id,
            'user_id' => $conversation?->patient_id,
            'address' => $phone,
            'source' => 'webhook_inbound',
            'body' => $text,
            'provider' => 'gupshup',
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
        ]);

        if ($conversation) {
            try {
                app(FlowEngine::class)->advance($conversation, $text);
            } catch (\Throwable $e) {
                Log::channel('stack')->error('[GupshupWebhook] FlowEngine::advance failed: '.$e->getMessage());
            }
        }

        return response()->json(['ok' => true, 'matched_conversation' => (bool) $conversation, 'log_id' => $log->id]);
    }

    /**
     * A plain-text message carries its body at `payload.payload.text`. A
     * Quick-Reply-Buttons tap instead carries `payload.payload.type` =
     * "button_reply" (or "list_reply" for list-picker templates), with the
     * tapped button's visible label under a nested `.title` (Gupshup has used
     * slightly different nesting across API versions, so both `payload.title`
     * and the shallower `title` are checked). Surfacing that label as the
     * "reply text" lets ReplyClassifier::classifyYesNo() recognize a Yes/No
     * button tap the same way it already recognizes typed "yes"/"no" — as
     * long as the Gupshup template's buttons are labelled "Yes" / "No".
     */
    protected function extractInboundText(Request $request): string
    {
        $type = (string) $request->input('payload.payload.type', 'text');

        if ($type === 'button_reply' || $type === 'list_reply') {
            $label = $request->input('payload.payload.payload.title')
                ?? $request->input('payload.payload.title')
                ?? $request->input('payload.payload.payload.payload');

            return (string) ($label ?? '');
        }

        return (string) ($request->input('payload.payload.text') ?? $request->input('payload.text') ?? '');
    }

    protected function handleDeliveryEvent(Request $request): JsonResponse
    {
        $providerMessageId = $request->input('payload.id');
        $eventType = (string) $request->input('payload.type');

        $status = match ($eventType) {
            'enqueued' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            default => null,
        };

        if ($providerMessageId && $status) {
            MessageLog::where('provider_message_id', $providerMessageId)->update(['status' => $status]);
        }

        return response()->json(['ok' => true]);
    }
}
