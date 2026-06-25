<?php

namespace Tests\Feature;

use App\Models\AiRequestLog;
use App\Services\AI\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the PRD guarantee: "a rule-based fallback keeps scheduling working if
 * AI is unavailable, and all AI requests are logged."
 */
class AiServiceFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ai.provider' => 'rule_based']);
    }

    public function test_ai_disabled_when_provider_is_rule_based(): void
    {
        $this->assertFalse(app(AiService::class)->enabled());
    }

    public function test_booking_intent_falls_back_and_is_logged(): void
    {
        $ai = app(AiService::class);

        $intent = $ai->parseBookingIntent('book a dentist next tuesday afternoon');

        // Rule-based parse still extracts a usable date + period.
        $this->assertArrayHasKey('date', $intent);
        $this->assertSame('afternoon', $intent['period']);

        // Every call is recorded for observability.
        $this->assertSame(1, AiRequestLog::where('feature', 'nlp_booking')->count());
        $log = AiRequestLog::first();
        $this->assertSame('rule_based', $log->provider);
        $this->assertSame('success', $log->status);
    }

    public function test_sentiment_classifies_without_a_provider(): void
    {
        $ai = app(AiService::class);

        $this->assertSame('positive', $ai->analyzeSentiment('The staff were friendly and helpful, great visit'));
        $this->assertSame('negative', $ai->analyzeSentiment('Terrible, rude and the worst wait ever'));
        $this->assertSame('neutral', $ai->analyzeSentiment('I had an appointment today'));
    }

    public function test_symptom_routing_returns_specialty_and_urgency(): void
    {
        $routing = app(AiService::class)->routeSymptoms('severe chest pain and shortness of breath');

        $this->assertSame('urgent', $routing['urgency']);
        $this->assertNotEmpty($routing['specialty']);
    }

    public function test_assistant_resolves_navigation_intent(): void
    {
        $catalog = [['key' => 'patients.index', 'label' => 'Patients List', 'keywords' => ['patients']]];

        $result = app(AiService::class)->assistantCommand('take me to the patients list', $catalog, []);

        $this->assertSame('navigate', $result['action']);
        $this->assertSame('patients.index', $result['key']);
    }

    public function test_assistant_resolves_data_query_intent_with_name(): void
    {
        $types = [['type' => 'patient_visit_count', 'description' => 'how many times a patient visited']];

        $result = app(AiService::class)->assistantCommand('how many times did John Smith visit', [], $types);

        $this->assertSame('query', $result['action']);
        $this->assertSame('patient_visit_count', $result['query']['type']);
        $this->assertStringContainsString('John', (string) $result['query']['name']);
    }

    public function test_assistant_strips_query_type_not_offered(): void
    {
        // No query types offered (e.g. user lacks permission) → must not return a query action.
        $result = app(AiService::class)->assistantCommand('how many times did John Smith visit', [], []);

        $this->assertNotSame('query', $result['action']);
    }
}
