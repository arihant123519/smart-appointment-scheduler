<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Service;
use App\Services\AI\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiBookingController extends Controller
{
    public function index(AiService $ai): View
    {
        $enabled = $ai->enabled();
        $provider = $ai->provider();

        return view('ai.booking', compact('enabled', 'provider'));
    }

    /** Parse a natural-language request into structured booking suggestions. */
    public function parse(Request $request, AiService $ai): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:500']]);

        $intent = $ai->parseBookingIntent($data['text']);

        // Resolve human-friendly labels + matching providers for the suggested service.
        $service = $intent['service_id'] ? Service::find($intent['service_id']) : null;

        $providers = $this->matchingProviders($intent['specialty'] ?? null);

        return response()->json([
            'intent' => $intent,
            'service' => $service ? ['id' => $service->id, 'name' => $service->name] : null,
            'providers' => $providers,
        ]);
    }

    /**
     * Conversational scheduling assistant. Accepts the running message history
     * and returns the assistant's reply plus any structured booking intent.
     */
    public function chat(Request $request, AiService $ai): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'max:30'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:1000'],
        ]);

        $context = ['clinic' => auth()->user()?->clinic_id];
        $result = $ai->chat($data['messages'], $context);

        // If the assistant surfaced a booking intent, attach matching providers
        // and a pre-filled booking link so the patient can confirm (AI never
        // finalizes a booking itself — PRD §5.2).
        $link = null;
        $providers = [];
        $intent = $result['intent'] ?? null;
        if ($intent && ($intent['action'] ?? null) === 'book') {
            $providers = $this->matchingProviders($intent['specialty'] ?? null);
            $params = [];
            if (! empty($intent['date'])) {
                $params['date'] = $intent['date'];
            }
            $link = route('booking.create').(empty($params) ? '' : '?'.http_build_query($params));
        }

        return response()->json([
            'reply' => $result['reply'],
            'intent' => $intent,
            'providers' => $providers,
            'booking_link' => $link,
        ]);
    }

    /** Smart symptom routing — suggest a specialty + urgency (informational only). */
    public function routeSymptoms(Request $request, AiService $ai): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:1000']]);

        $routing = $ai->routeSymptoms($data['text']);
        $routing['providers'] = $this->matchingProviders($routing['specialty'] ?? null);

        return response()->json($routing);
    }

    /** @return array<int, array{id:int, name:string, specialty:?string}> */
    private function matchingProviders(?string $specialty): array
    {
        return Provider::with('user')->forCurrentClinic()->where('is_active', true)
            ->when($specialty, fn ($q) => $q->where('specialty', 'like', '%'.$specialty.'%'))
            ->limit(8)
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'specialty' => $p->specialty])
            ->all();
    }
}
