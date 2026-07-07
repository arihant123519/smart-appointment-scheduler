<?php

namespace App\Services\AI;

use App\Models\AiRequestLog;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AI intelligence layer (PRD §5.2).
 *
 * Design per PRD: "AI is assistive only … a rule-based fallback keeps scheduling
 * working if AI is unavailable, and all AI requests are logged."
 *
 * Each feature method runs through {@see run()}, which:
 *   1. calls the configured provider (Gemini/OpenAI) when enabled,
 *   2. falls back to a deterministic rule-based implementation on any failure,
 *   3. logs provider, latency, token usage and status to ai_request_logs.
 *
 * PHI is minimized (see {@see PhiMinimizer}) before any external call.
 */
class AiService
{
    private const PROMPT_VERSION = 'v2';

    /** Token count from the most recent provider call (for logging). */
    private ?int $lastTokens = null;

    public function provider(): string
    {
        return config('services.ai.provider', 'rule_based');
    }

    /** AI is on only when a non-rule provider is selected and its key is present. */
    public function enabled(): bool
    {
        $provider = $this->provider();

        return in_array($provider, ['gemini', 'openai'], true)
            && ! empty(config("services.ai.{$provider}.key"));
    }

    // --- public features ------------------------------------------------------

    /**
     * Natural-language booking: parse free text into structured booking filters.
     *
     * @return array{service_id:?int, specialty:?string, date:?string, period:?string, urgency:?string, note:string}
     */
    public function parseBookingIntent(string $text): array
    {
        return $this->run('nlp_booking', fn () => $this->ruleParseBooking($text), $text);
    }

    /** Condense intake responses into a concise pre-visit clinical brief. */
    public function summarizeIntake(array $responses): string
    {
        return $this->run('intake_summary', fn () => $this->ruleSummarize($responses), json_encode($responses));
    }

    /** Classify free text as positive | neutral | negative. */
    public function analyzeSentiment(string $text): string
    {
        return $this->run('sentiment', fn () => $this->ruleSentiment($text), $text);
    }

    /**
     * Conversational scheduling assistant (PRD §5.2 "AI scheduling assistant").
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array{reply:string, intent:?array}
     */
    public function chat(array $messages, array $context = []): array
    {
        $input = json_encode(['messages' => $messages, 'context' => $context]);

        return $this->run('assistant_chat', fn () => $this->ruleChat($messages), $input);
    }

    /**
     * Smart symptom routing — suggest specialty + urgency (informational only).
     *
     * @return array{specialty:?string, urgency:string, advice:string, why_serious:string, can_lead_to:string}
     */
    public function routeSymptoms(string $text): array
    {
        return $this->run('symptom_routing', fn () => $this->ruleRouteSymptoms($text), $text);
    }

    /**
     * In-app assistant: decide whether a free-text instruction is a NAVIGATION
     * request ("take me to patients", "create a new clinic") or a DATA QUERY
     * ("how many times did John visit?", "show Dr. Lee's appointments").
     *
     * The destination catalog and the query-type list are both pre-filtered by
     * the caller to what the current user may access.
     *
     * @param  array<int, array{key:string, label:string, keywords?:array<int,string>}>  $catalog
     * @param  array<int, array{type:string, description:string}>  $queryTypes
     * @return array{action:string, key:?string, query:array{type:?string, name:?string, range:?string}, reply:string}
     */
    public function assistantCommand(string $text, array $catalog, array $queryTypes = []): array
    {
        $input = json_encode([
            'request' => $text,
            'destinations' => array_map(fn ($d) => ['key' => $d['key'], 'label' => $d['label']], $catalog),
            'query_types' => $queryTypes,
        ]);

        $result = $this->run(
            'assistant_command',
            fn () => $this->ruleAssistantCommand($text, $catalog, $queryTypes),
            $input
        );

        // Defence in depth: never return a key or query type outside what was offered.
        $allowedKeys = array_column($catalog, 'key');
        $allowedTypes = array_column($queryTypes, 'type');

        if (($result['action'] ?? null) === 'navigate' && ! in_array($result['key'] ?? null, $allowedKeys, true)) {
            $result['action'] = 'none';
            $result['key'] = null;
        }
        if (($result['action'] ?? null) === 'query' && ! in_array($result['query']['type'] ?? null, $allowedTypes, true)) {
            $result['action'] = 'none';
        }

        return $result;
    }

    /** Generate a personalized, tone-appropriate reminder message. */
    public function generateReminderMessage(array $context): string
    {
        return $this->run('smart_reminder', fn () => $this->ruleReminder($context), json_encode($context));
    }

    /**
     * Interpret a free-form reply (e.g. "next Tuesday afternoon", "10th July
     * around 3", "day after tomorrow morning") as an absolute date/time.
     * Returns null when no date/time could be made out of the text at all.
     */
    public function parseDateTime(string $text): ?string
    {
        return $this->run('parse_datetime', fn () => $this->ruleParseDateTime($text), $text);
    }

    /** Answer an admin's natural-language question over a supplied metrics array. */
    public function answerReportQuery(string $question, array $data): string
    {
        $input = json_encode(['question' => $question, 'data' => $data]);

        return $this->run('report_query', fn () => $this->ruleReportAnswer($question, $data), $input);
    }

    /**
     * Extract recurring themes from a batch of feedback comments.
     *
     * @return array{summary:string, themes:array<int, string>}
     */
    public function summarizeFeedbackThemes(array $comments): array
    {
        return $this->run('feedback_themes', fn () => $this->ruleFeedbackThemes($comments), json_encode($comments));
    }

    // --- execution + logging --------------------------------------------------

    private function run(string $feature, callable $fallback, string $input)
    {
        $start = microtime(true);
        $providerUsed = 'rule_based';
        $status = 'success';
        $result = null;
        $error = null;
        $this->lastTokens = null;

        try {
            if ($this->enabled() && $this->withinRateLimit()) {
                $result = $this->callProvider($feature, $input);
                if ($result !== null) {
                    $providerUsed = $this->provider();
                }
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = Str::limit($e->getMessage(), 480);
            Log::warning("AI {$feature} failed: ".$e->getMessage());
        }

        if ($result === null) {
            $result = $fallback();
            // If AI was supposed to run but produced nothing, mark it a fallback.
            if ($this->enabled() && $status !== 'failed') {
                $status = 'fallback';
            }
        }

        AiRequestLog::create([
            'user_id' => auth()->id(),
            'feature' => $feature,
            'provider' => $providerUsed,
            'prompt_version' => self::PROMPT_VERSION,
            'tokens' => $this->lastTokens,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            'status' => $status,
            'error' => $error,
        ]);

        return $result;
    }

    /** Simple per-minute cap to protect against AI cost overrun (PRD risk §8). */
    private function withinRateLimit(): bool
    {
        $max = (int) config('services.ai.max_per_minute', 60);
        if ($max <= 0) {
            return true;
        }

        $key = 'ai_calls_'.now()->format('YmdHi');
        $count = (int) Cache::get($key, 0);
        if ($count >= $max) {
            return false;
        }
        Cache::put($key, $count + 1, 120);

        return true;
    }

    // --- provider call --------------------------------------------------------

    /**
     * Build a feature-specific prompt, send it to the active provider, and parse
     * the response. Returns null to trigger the rule-based fallback.
     */
    private function callProvider(string $feature, string $input)
    {
        [$system, $user, $expectJson] = $this->promptFor($feature, $input);

        $raw = $this->provider() === 'openai'
            ? $this->callOpenAi($system, $user, $expectJson)
            : $this->callGemini($system, $user, $expectJson);

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return $this->parseResponse($feature, $raw, $input);
    }

    private function callGemini(string $system, string $user, bool $expectJson): ?string
    {
        $cfg = config('services.ai.gemini');
        $url = rtrim($cfg['endpoint'], '/').'/'.$cfg['model'].':generateContent';

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 1024,
            ],
        ];
        if ($expectJson) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $resp = Http::timeout(config('services.ai.timeout', 20))
            ->withQueryParameters(['key' => $cfg['key']])
            ->acceptJson()
            ->post($url, $payload);

        if (! $resp->successful()) {
            throw new \RuntimeException('Gemini HTTP '.$resp->status().': '.Str::limit($resp->body(), 200));
        }

        $this->lastTokens = $resp->json('usageMetadata.totalTokenCount');

        return $resp->json('candidates.0.content.parts.0.text');
    }

    private function callOpenAi(string $system, string $user, bool $expectJson): ?string
    {
        $cfg = config('services.ai.openai');

        $payload = [
            'model' => $cfg['model'],
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ];
        if ($expectJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $resp = Http::timeout(config('services.ai.timeout', 20))
            ->withToken($cfg['key'])
            ->acceptJson()
            ->post($cfg['endpoint'], $payload);

        if (! $resp->successful()) {
            throw new \RuntimeException('OpenAI HTTP '.$resp->status().': '.Str::limit($resp->body(), 200));
        }

        $this->lastTokens = $resp->json('usage.total_tokens');

        return $resp->json('choices.0.message.content');
    }

    // --- prompts --------------------------------------------------------------

    /**
     * @return array{0:string, 1:string, 2:bool} [system, user, expectJson]
     */
    private function promptFor(string $feature, string $input): array
    {
        $today = now()->toDateString();

        return match ($feature) {
            'nlp_booking' => [
                "You are a healthcare booking assistant. Today is {$today}. ".
                'Extract booking intent from the patient message. Reply ONLY with JSON: '.
                '{"service":string|null,"specialty":string|null,"date":"YYYY-MM-DD"|null,'.
                '"period":"morning"|"afternoon"|"evening"|null,"urgency":"routine"|"soon"|"urgent"}. '.
                'Resolve relative dates (e.g. "next Tuesday") to an absolute date.',
                PhiMinimizer::scrub($input),
                true,
            ],
            'intake_summary' => [
                'You are a clinical scribe. Summarize the patient intake responses into a concise, '.
                'neutral pre-visit brief for the provider (3-5 short lines, no diagnosis, no advice). '.
                'Start with "Pre-visit summary:".',
                PhiMinimizer::scrub($this->readableResponses($input)),
                false,
            ],
            'sentiment' => [
                'Classify the sentiment of the patient feedback. Reply with exactly one word: '.
                'positive, neutral, or negative.',
                PhiMinimizer::scrub($input),
                false,
            ],
            'assistant_chat' => [
                "You are the clinic's friendly scheduling assistant. Today is {$today}. ".
                'Help patients book, reschedule, cancel, or answer FAQs. Be concise and warm. '.
                'Never give medical diagnosis or final confirmation — always tell the patient to '.
                'confirm the suggested slot. Reply ONLY with JSON: '.
                '{"reply":string,"intent":{"action":"book"|"reschedule"|"cancel"|"faq"|"handoff",'.
                '"specialty":string|null,"date":"YYYY-MM-DD"|null,"period":string|null}|null}.',
                PhiMinimizer::scrub($this->readableChat($input)),
                true,
            ],
            'symptom_routing' => [
                'You are a triage helper (informational only, NOT a diagnosis). From the described '.
                'symptoms suggest the most relevant medical specialty and an urgency level, and explain '.
                'in plain, non-alarming language why the symptoms matter and what they could lead to if '.
                'ignored. Reply ONLY with JSON: {"specialty":string,"urgency":"routine"|"soon"|"urgent",'.
                '"advice":string,"why_serious":string,"can_lead_to":string}. Keep "advice" to one neutral '.
                'sentence and recommend emergency care if symptoms sound severe. Keep "why_serious" and '.
                '"can_lead_to" to one short sentence each, general and educational, never a definitive '.
                'diagnosis or a claim about this specific person.',
                PhiMinimizer::scrub($input),
                true,
            ],
            'smart_reminder' => [
                'Write a short, warm appointment reminder message (max 60 words) in the language and '.
                'tone implied by the context. Include the appointment details given. Do not invent '.
                'links or facts not in the context.',
                PhiMinimizer::scrub($input, []),
                false,
            ],
            'report_query' => [
                'You are a clinic analytics assistant. Answer the admin question using ONLY the JSON '.
                'metrics provided. Be specific and include the numbers. If the data cannot answer the '.
                'question, say so plainly. 1-3 sentences.',
                $input, // metrics only, no PHI
                false,
            ],
            'parse_datetime' => [
                "Today is {$today}. A patient was asked what date and time they'd prefer for their ".
                "rescheduled appointment, and replied in their own words. Resolve their reply to a single ".
                'absolute date and time. Reply ONLY with JSON: {"datetime":"YYYY-MM-DD HH:MM"|null}. Use '.
                '24-hour time. If the reply names no nameable date/time at all, reply {"datetime":null}.',
                $input, // the reply IS the date/time to resolve — PHI-scrubbing would corrupt the numbers
                true,
            ],
            'feedback_themes' => [
                'Analyze the patient feedback comments. Identify up to 5 recurring themes. Reply ONLY '.
                'with JSON: {"summary":string,"themes":[string,...]}. Summary is one sentence.',
                PhiMinimizer::scrub($input),
                true,
            ],
            'assistant_command' => [
                'You are an in-app assistant for a clinic scheduling system. You get the user request '.
                'plus two JSON lists: "destinations" (pages to navigate to, each key+label) and '.
                '"query_types" (data questions you can answer, each type+description). Decide the intent: '.
                'NAVIGATE to a page, run a data QUERY, or none. For a query, extract the person\'s NAME '.
                'mentioned and a time range if stated. Reply ONLY with JSON: '.
                '{"action":"navigate"|"query"|"none","key":string|null,'.
                '"query":{"type":string|null,"name":string|null,'.
                '"range":"all"|"upcoming"|"past"|"today"|null},"reply":string}. '.
                'The reply is one short, friendly sentence.',
                PhiMinimizer::scrub($input),
                true,
            ],
            default => ['You are a helpful assistant.', PhiMinimizer::scrub($input), false],
        };
    }

    /** Make a JSON-encoded responses array readable for the summary prompt. */
    private function readableResponses(string $input): string
    {
        $data = json_decode($input, true);
        if (! is_array($data)) {
            return $input;
        }
        $lines = [];
        foreach ($data as $key => $value) {
            $value = is_array($value) ? implode(', ', $value) : (string) $value;
            $lines[] = Str::headline((string) $key).': '.$value;
        }

        return implode("\n", $lines);
    }

    /** Flatten the chat payload into a readable transcript. */
    private function readableChat(string $input): string
    {
        $data = json_decode($input, true);
        $messages = $data['messages'] ?? [];
        $lines = [];
        foreach ($messages as $m) {
            $role = ($m['role'] ?? 'user') === 'assistant' ? 'Assistant' : 'Patient';
            $lines[] = "{$role}: ".($m['content'] ?? '');
        }
        if (! empty($data['context'])) {
            $lines[] = 'Context: '.json_encode($data['context']);
        }

        return implode("\n", $lines);
    }

    // --- response parsing -----------------------------------------------------

    private function parseResponse(string $feature, string $raw, string $input)
    {
        return match ($feature) {
            'nlp_booking' => $this->parseBookingResponse($raw),
            'sentiment' => $this->parseSentimentResponse($raw),
            'assistant_chat' => $this->parseChatResponse($raw),
            'symptom_routing' => $this->parseSymptomResponse($raw),
            'feedback_themes' => $this->parseThemesResponse($raw),
            'assistant_command' => $this->parseCommandResponse($raw),
            'parse_datetime' => $this->parseDateTimeResponse($raw),
            // intake_summary, smart_reminder, report_query => plain text
            default => trim($raw),
        };
    }

    private function decodeJson(string $raw): ?array
    {
        $raw = trim($raw);
        // Strip ```json fences if present.
        $raw = preg_replace('/^```(?:json)?|```$/m', '', $raw);
        $data = json_decode(trim($raw), true);

        return is_array($data) ? $data : null;
    }

    private function parseBookingResponse(string $raw): ?array
    {
        $data = $this->decodeJson($raw);
        if ($data === null) {
            return null;
        }

        // Resolve a service_id from the suggested service/specialty name.
        $name = $data['service'] ?? null;
        $specialty = $data['specialty'] ?? null;
        $service = null;
        if ($name || $specialty) {
            $service = Service::where('is_active', true)->get()->first(function ($s) use ($name, $specialty) {
                return ($name && Str::contains(Str::lower($s->name), Str::lower($name)))
                    || ($specialty && $s->specialty && Str::contains(Str::lower($s->specialty), Str::lower($specialty)));
            });
        }

        return [
            'service_id' => $service?->id,
            'specialty' => $specialty,
            'date' => $this->normalizeDate($data['date'] ?? null),
            'period' => in_array($data['period'] ?? null, ['morning', 'afternoon', 'evening'], true) ? $data['period'] : null,
            'urgency' => $data['urgency'] ?? 'routine',
            'note' => 'Interpreted by AI assistant. Please review the suggested slots before confirming.',
        ];
    }

    private function parseSentimentResponse(string $raw): string
    {
        $word = Str::lower(trim($raw));
        foreach (['positive', 'negative', 'neutral'] as $s) {
            if (Str::contains($word, $s)) {
                return $s;
            }
        }

        return 'neutral';
    }

    private function parseChatResponse(string $raw): ?array
    {
        $data = $this->decodeJson($raw);
        if ($data === null || empty($data['reply'])) {
            return null;
        }

        return ['reply' => (string) $data['reply'], 'intent' => $data['intent'] ?? null];
    }

    private function parseSymptomResponse(string $raw): ?array
    {
        $data = $this->decodeJson($raw);
        if ($data === null) {
            return null;
        }

        return [
            'specialty' => $data['specialty'] ?? null,
            'urgency' => in_array($data['urgency'] ?? null, ['routine', 'soon', 'urgent'], true) ? $data['urgency'] : 'routine',
            'advice' => (string) ($data['advice'] ?? 'This is informational only — please consult a clinician.'),
            'why_serious' => (string) ($data['why_serious'] ?? ''),
            'can_lead_to' => (string) ($data['can_lead_to'] ?? ''),
        ];
    }

    private function parseThemesResponse(string $raw): ?array
    {
        $data = $this->decodeJson($raw);
        if ($data === null) {
            return null;
        }

        return [
            'summary' => (string) ($data['summary'] ?? ''),
            'themes' => array_values(array_filter((array) ($data['themes'] ?? []))),
        ];
    }

    private function parseCommandResponse(string $raw): ?array
    {
        $data = $this->decodeJson($raw);
        if ($data === null) {
            return null;
        }

        $action = in_array($data['action'] ?? null, ['navigate', 'query', 'none'], true) ? $data['action'] : 'none';
        $query = is_array($data['query'] ?? null) ? $data['query'] : [];

        return [
            'action' => $action,
            'key' => $data['key'] ?? null,
            'query' => [
                'type' => $query['type'] ?? null,
                'name' => $query['name'] ?? null,
                'range' => $query['range'] ?? null,
            ],
            'reply' => (string) ($data['reply'] ?? 'On it…'),
        ];
    }

    private function parseDateTimeResponse(string $raw): ?string
    {
        $data = $this->decodeJson($raw);
        $value = $data['datetime'] ?? null;
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }
        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    // --- rule-based fallbacks -------------------------------------------------

    private function ruleParseBooking(string $text): array
    {
        $lower = Str::lower($text);

        $service = Service::where('is_active', true)->get()->first(function ($s) use ($lower) {
            return Str::contains($lower, Str::lower($s->name))
                || ($s->specialty && Str::contains($lower, Str::lower($s->specialty)));
        });

        $specialty = collect(['dentist' => 'Dental', 'dental' => 'Dental', 'therapy' => 'Mental Health',
            'therapist' => 'Mental Health', 'child' => 'Pediatrics', 'pediatric' => 'Pediatrics'])
            ->first(fn ($v, $k) => Str::contains($lower, $k));

        $date = null;
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            if (Str::contains($lower, $day)) {
                $date = Carbon::parse('next '.$day)->toDateString();
                break;
            }
        }
        if (Str::contains($lower, 'tomorrow')) {
            $date = now()->addDay()->toDateString();
        } elseif (Str::contains($lower, 'today')) {
            $date = now()->toDateString();
        }

        $period = match (true) {
            Str::contains($lower, 'morning') => 'morning',
            Str::contains($lower, 'afternoon') => 'afternoon',
            Str::contains($lower, 'evening') => 'evening',
            default => null,
        };

        $urgency = match (true) {
            Str::contains($lower, ['urgent', 'asap', 'emergency', 'severe']) => 'urgent',
            Str::contains($lower, ['soon', 'pain', 'today', 'tomorrow']) => 'soon',
            default => 'routine',
        };

        return [
            'service_id' => $service?->id,
            'specialty' => $specialty,
            'date' => $date,
            'period' => $period,
            'urgency' => $urgency,
            'note' => 'Parsed by rule-based engine. Please review the suggested slots before confirming.',
        ];
    }

    private function ruleSummarize(array $responses): string
    {
        if (empty($responses)) {
            return 'No intake responses provided.';
        }

        $lines = ['Pre-visit summary:'];
        foreach ($responses as $key => $value) {
            $label = Str::headline((string) $key);
            $value = is_array($value) ? implode(', ', $value) : (string) $value;
            if (trim($value) !== '') {
                $lines[] = "• {$label}: {$value}";
            }
        }

        return implode("\n", $lines);
    }

    private function ruleSentiment(string $text): string
    {
        $lower = Str::lower($text);
        $neg = ['bad', 'terrible', 'awful', 'late', 'rude', 'worst', 'hate', 'unhappy', 'disappointed', 'poor'];
        $pos = ['great', 'good', 'excellent', 'amazing', 'love', 'friendly', 'helpful', 'best', 'wonderful', 'happy'];

        $score = 0;
        foreach ($pos as $w) {
            if (Str::contains($lower, $w)) {
                $score++;
            }
        }
        foreach ($neg as $w) {
            if (Str::contains($lower, $w)) {
                $score--;
            }
        }

        return $score > 0 ? 'positive' : ($score < 0 ? 'negative' : 'neutral');
    }

    private function ruleChat(array $messages): array
    {
        $last = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? 'user') === 'user') {
                $last = $m['content'] ?? '';
                break;
            }
        }

        $intent = $this->ruleParseBooking($last);
        $hasSignal = $intent['service_id'] || $intent['specialty'] || $intent['date'];

        $reply = $hasSignal
            ? 'I can help with that. I have noted your request — please open the booking page to pick from the matching open slots and confirm.'
            : 'I can help you book, reschedule, or cancel an appointment. Could you tell me the type of visit and a preferred day?';

        return [
            'reply' => $reply,
            'intent' => [
                'action' => Str::contains(Str::lower($last), ['cancel']) ? 'cancel'
                    : (Str::contains(Str::lower($last), ['reschedule', 'move', 'change']) ? 'reschedule' : 'book'),
                'specialty' => $intent['specialty'],
                'date' => $intent['date'],
                'period' => $intent['period'],
            ],
        ];
    }

    private function ruleAssistantCommand(string $text, array $catalog, array $queryTypes = []): array
    {
        $lower = Str::lower($text);
        $none = ['action' => 'none', 'key' => null, 'query' => ['type' => null, 'name' => null, 'range' => null]];

        // 1) Try to detect a data query first.
        $typeKeys = array_column($queryTypes, 'type');
        $name = $this->extractName($text);
        $range = match (true) {
            Str::contains($lower, ['upcoming', 'future', 'next']) => 'upcoming',
            Str::contains($lower, ['past', 'previous', 'history', 'so far', 'till now', 'until now']) => 'past',
            Str::contains($lower, ['today']) => 'today',
            default => 'all',
        };

        $queryType = null;
        if (Str::contains($lower, ['how many', 'how often', 'number of times', 'times']) && in_array('patient_visit_count', $typeKeys, true)) {
            $queryType = 'patient_visit_count';
        } elseif (Str::contains($lower, ['provider', 'doctor', 'dr ', 'dr.']) && in_array('provider_appointments', $typeKeys, true)) {
            $queryType = 'provider_appointments';
        } elseif (Str::contains($lower, ['appointment', 'visit', 'booking']) && $name && in_array('patient_appointments', $typeKeys, true)) {
            $queryType = 'patient_appointments';
        }

        if ($queryType && $name) {
            return [
                'action' => 'query',
                'key' => null,
                'query' => ['type' => $queryType, 'name' => $name, 'range' => $range],
                'reply' => 'Looking that up…',
            ];
        }

        // 2) Otherwise fall back to navigation matching.
        $best = null;
        $bestScore = 0;
        foreach ($catalog as $dest) {
            $score = 0;
            foreach (array_merge([$dest['label']], $dest['keywords'] ?? []) as $term) {
                foreach (preg_split('/\s+/', Str::lower($term)) as $word) {
                    if (mb_strlen($word) >= 3 && Str::contains($lower, $word)) {
                        $score++;
                    }
                }
            }
            if (Str::contains($lower, ['create', 'new', 'add']) && Str::contains(Str::lower($dest['key']), 'create')) {
                $score += 2;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $dest;
            }
        }

        if ($best) {
            return array_merge($none, ['action' => 'navigate', 'key' => $best['key'], 'reply' => 'Opening '.$best['label'].'…']);
        }

        return array_merge($none, ['reply' => "I'm not sure — try \"open patients\", \"create a clinic\", or \"how many times did John visit?\""]);
    }

    /** Best-effort name extraction from a query for the rule-based path. */
    private function extractName(string $text): ?string
    {
        // Grab the phrase after "of" / "for" (e.g. "appointments of John Smith").
        if (preg_match('/\b(?:of|for|by|with)\s+([A-Za-z][A-Za-z.\'-]*(?:\s+[A-Za-z][A-Za-z.\'-]*){0,2})/u', $text, $m)) {
            $name = trim($m[1]);
            // Drop trailing stop-words that aren't part of a name.
            $name = preg_replace('/\b(till|until|now|so|far|today|the|clinic|provider|patient|client)\b.*$/iu', '', $name);

            return trim($name) ?: null;
        }

        // Otherwise the first run of Capitalized words.
        if (preg_match('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,2})\b/u', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function ruleRouteSymptoms(string $text): array
    {
        $lower = Str::lower($text);
        $map = [
            'Dental' => ['tooth', 'teeth', 'gum', 'dental', 'cavity'],
            'Cardiology' => ['chest pain', 'heart', 'palpitation'],
            'Dermatology' => ['skin', 'rash', 'acne', 'mole'],
            'Pediatrics' => ['child', 'baby', 'infant'],
            'Mental Health' => ['anxiety', 'depress', 'stress', 'panic'],
            'Orthopedics' => ['bone', 'joint', 'fracture', 'knee', 'back pain'],
            'ENT' => ['ear', 'nose', 'throat', 'sinus'],
        ];

        $specialty = 'General Medicine';
        foreach ($map as $spec => $keywords) {
            if (Str::contains($lower, $keywords)) {
                $specialty = $spec;
                break;
            }
        }

        $urgent = Str::contains($lower, ['chest pain', 'breath', 'bleeding', 'severe', 'faint', 'unconscious']);
        $soon = Str::contains($lower, ['pain', 'fever', 'vomit', 'days']);
        $urgency = $urgent ? 'urgent' : ($soon ? 'soon' : 'routine');

        // Why it matters + what untreated symptoms in this area can lead to.
        // Educational only — deliberately non-diagnostic and specialty-scoped.
        $context = [
            'Dental' => [
                'why' => 'Dental pain and gum problems often signal infection or decay that does not resolve on its own.',
                'leads' => 'Untreated, it can progress to abscesses, tooth loss, and infection spreading to the jaw or bloodstream.',
            ],
            'Cardiology' => [
                'why' => 'Chest pain, palpitations, or heart-related symptoms can reflect how well your heart is getting blood and oxygen.',
                'leads' => 'If ignored, some causes can lead to a heart attack, dangerous rhythm problems, or lasting heart damage.',
            ],
            'Dermatology' => [
                'why' => 'Skin changes, new or changing moles, and persistent rashes can be early signs of infection or skin disease.',
                'leads' => 'Left unchecked, certain lesions can spread, scar, or in rare cases progress to skin cancer.',
            ],
            'Pediatrics' => [
                'why' => 'Children can decline faster than adults, so fever or feeding changes deserve prompt attention.',
                'leads' => 'Delays can allow dehydration or infection to worsen quickly and become harder to treat.',
            ],
            'Mental Health' => [
                'why' => 'Ongoing anxiety, low mood, or panic affect daily functioning and tend to build over time.',
                'leads' => 'Without support they can deepen, disrupt sleep, work, and relationships, and increase health risks.',
            ],
            'Orthopedics' => [
                'why' => 'Bone, joint, and back symptoms can point to injury, inflammation, or nerve involvement.',
                'leads' => 'Untreated, they can cause chronic pain, reduced mobility, or permanent joint damage.',
            ],
            'ENT' => [
                'why' => 'Ear, nose, throat, and sinus symptoms can indicate infection that sits close to the airway and brain.',
                'leads' => 'If untreated, infections can spread, affect hearing, or block breathing.',
            ],
            'General Medicine' => [
                'why' => 'Persistent or worsening symptoms are your body signalling that something needs to be checked.',
                'leads' => 'Waiting can let a treatable problem progress and become more serious or harder to manage.',
            ],
        ];
        $info = $context[$specialty] ?? $context['General Medicine'];

        $why = $urgent
            ? 'These symptoms can be a sign of a serious problem that may worsen quickly. '.$info['why']
            : $info['why'];

        return [
            'specialty' => $specialty,
            'urgency' => $urgency,
            'advice' => $urgent
                ? 'These symptoms may be serious — please seek emergency care if they worsen. This is informational only.'
                : 'This is informational only and not a diagnosis. Please confirm with a clinician.',
            'why_serious' => $why,
            'can_lead_to' => $info['leads'],
        ];
    }

    private function ruleReminder(array $context): string
    {
        $name = $context['patient'] ?? 'there';
        $service = $context['service'] ?? 'your appointment';
        $provider = $context['provider'] ?? 'our team';
        $when = $context['when'] ?? 'soon';

        return "Hi {$name}, this is a friendly reminder for your {$service} with {$provider} on {$when}. ".
            'Please reply to confirm, reschedule, or cancel. We look forward to seeing you!';
    }

    /** Deterministic fallback — Carbon already understands a lot of plain phrasing without AI. */
    private function ruleParseDateTime(string $text): ?string
    {
        try {
            return Carbon::parse($text)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function ruleReportAnswer(string $question, array $data): string
    {
        $parts = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = Str::headline((string) $key).': '.$value;
            }
        }

        return 'Based on the available metrics — '.implode('; ', $parts).
            '. (Rule-based answer; enable the AI provider for natural-language analysis.)';
    }

    private function ruleFeedbackThemes(array $comments): array
    {
        $text = Str::lower(implode(' ', array_map(fn ($c) => is_array($c) ? ($c['comment'] ?? '') : (string) $c, $comments)));
        $candidates = [
            'wait time' => ['wait', 'waiting', 'late', 'delay'],
            'staff friendliness' => ['friendly', 'rude', 'staff', 'helpful'],
            'cleanliness' => ['clean', 'dirty', 'hygiene'],
            'booking ease' => ['book', 'reschedule', 'app', 'online'],
            'cost' => ['price', 'expensive', 'cost', 'bill'],
        ];

        $themes = [];
        foreach ($candidates as $label => $keywords) {
            if (Str::contains($text, $keywords)) {
                $themes[] = $label;
            }
        }

        return [
            'summary' => $themes
                ? 'Common themes detected: '.implode(', ', $themes).'.'
                : 'No strong recurring themes detected in the current feedback.',
            'themes' => $themes,
        ];
    }
}
