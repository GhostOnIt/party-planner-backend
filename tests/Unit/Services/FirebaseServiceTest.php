<?php

namespace Tests\Unit\Services;

use App\Services\FirebaseService;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Mockery;
use Tests\TestCase;

class FirebaseServiceTest extends TestCase
{
    private function serviceWithMessaging(Messaging $messaging): FirebaseService
    {
        config(['firebase.projects.app.credentials.file' => 'test-credentials.json']);
        $this->app->instance('firebase.messaging', $messaging);

        return new FirebaseService;
    }

    public function test_device_notification_preserves_target_and_payload(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->with(Mockery::on(function (CloudMessage $message): bool {
            $payload = $message->jsonSerialize();

            $this->assertSame('device-token', $payload['token']);
            $this->assertSame('Invitation', $payload['notification']['title']);
            $this->assertSame('Join the party', $payload['notification']['body']);
            $this->assertSame('https://example.com/image.png', $payload['notification']['image']);
            $this->assertSame(['event_id' => '42'], $payload['data']);
            $this->assertArrayHasKey('android', $payload);
            $this->assertArrayHasKey('apns', $payload);
            $this->assertArrayHasKey('webpush', $payload);

            return true;
        }))->andReturn(['name' => 'projects/test/messages/1']);

        $result = $this->serviceWithMessaging($messaging)->sendToDevice(
            'device-token', 'Invitation', 'Join the party', ['event_id' => '42'], 'https://example.com/image.png'
        );

        $this->assertTrue($result['success']);
    }

    public function test_topic_notification_preserves_target(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->with(Mockery::on(function (CloudMessage $message): bool {
            $payload = $message->jsonSerialize();
            $this->assertSame('event-42', $payload['topic']);
            $this->assertSame('Reminder', $payload['notification']['title']);
            $this->assertSame(['event_id' => '42'], $payload['data']);

            return true;
        }))->andReturn(['name' => 'projects/test/messages/2']);

        $result = $this->serviceWithMessaging($messaging)->sendToTopic(
            'event-42', 'Reminder', 'The party starts soon', ['event_id' => '42']
        );

        $this->assertTrue($result['success']);
    }

    public function test_token_validation_uses_dry_run(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->with(Mockery::on(function (CloudMessage $message): bool {
            $this->assertSame('device-token', $message->jsonSerialize()['token']);

            return true;
        }), true)->andReturn(['name' => 'projects/test/messages/3']);

        $this->assertTrue($this->serviceWithMessaging($messaging)->validateToken('device-token'));
    }
}
