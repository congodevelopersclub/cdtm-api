<?php

namespace Tests\Unit\Services;

use App\Services\NotificationService;
use Mockery;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationService $notificationService;

    public function test_send_notification_sends_notification(): void
    {
        $notificationService = new NotificationService();

        $resut = $notificationService->sendMail('some-random-params');

        $this->assertSame('Mail sent', $resut);
    }
}
