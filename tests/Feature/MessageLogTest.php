<?php

namespace Tests\Feature;

use App\Models\MessageLog;
use App\Models\User;
use App\Services\Messaging\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every send made through MessageService's two public methods must land in
 * message_logs — this is the single choke point every feature (reminders,
 * notifications, announcements, flows) relies on for delivery tracking.
 */
class MessageLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_send_is_logged_as_sent(): void
    {
        $user = User::factory()->create(['email' => 'patient@example.test']);

        $ok = app(MessageService::class)->send($user, 'Test subject', 'Test body', 'email');

        $this->assertTrue($ok);
        $this->assertDatabaseHas('message_logs', [
            'direction' => 'outbound',
            'channel' => 'email',
            'status' => 'sent',
            'user_id' => $user->id,
            'address' => 'patient@example.test',
        ]);
    }

    public function test_whatsapp_send_on_the_default_log_driver_is_logged_as_sent(): void
    {
        config(['services.messaging.whatsapp_driver' => 'log']);
        $user = User::factory()->create(['phone' => '+91 98765 43210']);

        $ok = app(MessageService::class)->send($user, 'Reminder', 'Body', 'whatsapp');

        $this->assertTrue($ok);
        $this->assertDatabaseHas('message_logs', [
            'channel' => 'whatsapp',
            'status' => 'sent',
            'provider' => 'log',
            'address' => '919876543210',
        ]);
    }

    public function test_failed_gupshup_send_is_logged_as_failed_with_an_error(): void
    {
        config([
            'services.messaging.whatsapp_driver' => 'gupshup',
            'services.whatsapp.gupshup_api_key' => 'test-key',
            'services.whatsapp.gupshup_source' => '911234567890',
            'services.whatsapp.gupshup_app_name' => 'TestApp',
        ]);
        Http::fake(['api.gupshup.io/*' => Http::response('bad request', 400)]);

        $user = User::factory()->create(['phone' => '919999999999']);
        $ok = app(MessageService::class)->send($user, 'Reminder', 'Body', 'whatsapp');

        $this->assertFalse($ok);
        $log = MessageLog::latest('id')->first();
        $this->assertSame('failed', $log->status);
        $this->assertSame('gupshup', $log->provider);
        $this->assertNotNull($log->error);
    }

    public function test_successful_gupshup_template_send_captures_the_provider_message_id(): void
    {
        config([
            'services.messaging.whatsapp_driver' => 'gupshup',
            'services.whatsapp.gupshup_api_key' => 'test-key',
            'services.whatsapp.gupshup_source' => '911234567890',
            'services.whatsapp.gupshup_app_name' => 'TestApp',
        ]);
        Http::fake(['api.gupshup.io/*' => Http::response(['status' => 'submitted', 'messageId' => 'msg-abc-123'], 200)]);

        $user = User::factory()->create(['phone' => '919999999999']);
        $ok = app(MessageService::class)->sendWhatsappTemplate($user, 'tmpl-1', ['Hi']);

        $this->assertTrue($ok);
        $this->assertDatabaseHas('message_logs', [
            'status' => 'sent',
            'provider' => 'gupshup',
            'provider_message_id' => 'msg-abc-123',
            'template_id' => 'tmpl-1',
        ]);
    }
}
