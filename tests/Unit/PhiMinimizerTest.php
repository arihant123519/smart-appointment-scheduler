<?php

namespace Tests\Unit;

use App\Services\AI\PhiMinimizer;
use Tests\TestCase;

class PhiMinimizerTest extends TestCase
{
    public function test_it_redacts_emails_and_phone_numbers(): void
    {
        $text = 'Contact me at jane.doe@example.com or +1 (415) 555-2671 anytime.';
        $scrubbed = PhiMinimizer::scrub($text);

        $this->assertStringNotContainsString('jane.doe@example.com', $scrubbed);
        $this->assertStringNotContainsString('555', $scrubbed);
        $this->assertStringContainsString('[EMAIL]', $scrubbed);
        $this->assertStringContainsString('[CONTACT]', $scrubbed);
    }

    public function test_it_redacts_supplied_names(): void
    {
        $scrubbed = PhiMinimizer::scrub('Patient Jane Doe reports a headache.', ['Jane Doe']);

        $this->assertStringNotContainsString('Jane', $scrubbed);
        $this->assertStringNotContainsString('Doe', $scrubbed);
        $this->assertStringContainsString('headache', $scrubbed);
    }

    public function test_it_scrubs_nested_arrays(): void
    {
        $scrubbed = PhiMinimizer::scrubArray([
            'reason' => 'follow up',
            'contact' => 'call 9876543210',
            'nested' => ['email' => 'a@b.com'],
        ]);

        $this->assertSame('follow up', $scrubbed['reason']);
        $this->assertStringContainsString('[CONTACT]', $scrubbed['contact']);
        $this->assertStringContainsString('[EMAIL]', $scrubbed['nested']['email']);
    }

    public function test_it_is_a_noop_when_minimization_disabled(): void
    {
        config(['services.ai.minimize_phi' => false]);

        $text = 'email me at a@b.com';
        $this->assertSame($text, PhiMinimizer::scrub($text));
    }
}
