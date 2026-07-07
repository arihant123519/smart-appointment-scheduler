<?php

namespace Tests\Unit;

use App\Support\ReplyClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReplyClassifierTest extends TestCase
{
    #[DataProvider('replies')]
    public function test_it_classifies_short_whatsapp_replies(string $text, ?string $expected): void
    {
        $this->assertSame($expected, ReplyClassifier::classifyYesNo($text));
    }

    public static function replies(): array
    {
        return [
            'plain yes' => ['yes', 'yes'],
            'yeah casual' => ['yeah that works', 'yes'],
            'sure' => ['sure, sounds good', 'yes'],
            'confirmed' => ['Confirmed!', 'yes'],
            'plain no' => ['no', 'no'],
            'nope casual' => ['nope, can\'t make it', 'no'],
            'negation beats a positive word' => ['not sure, can we change it', 'no'],
            'ambiguous free text' => ['maybe next week sometime?', null],
            'empty string' => ['', null],
        ];
    }
}
