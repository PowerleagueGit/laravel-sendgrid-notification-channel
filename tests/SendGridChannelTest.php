<?php

namespace NotificationChannels\SendGrid\Test;

use Mockery;
use SendGrid;
use SendGrid\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use NotificationChannels\SendGrid\SendGridChannel;
use NotificationChannels\SendGrid\SendGridMessage;
use Illuminate\Notifications\Events\NotificationSent;

class SendGridChannelTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testEmailIsSentViaSendGrid()
    {
        Event::fake();

        $notification = new class extends Notification {
            public function via()
            {
                return [SendGridChannel::class];
            }

            public function toSendGrid($notifiable)
            {
                return (new SendGridMessage('sendgrid-template-id'))
                    ->from('test@example.com', 'Example User')
                    ->to('test+test1@example.com', 'Example User1')
                    ->replyTo('replyto@example.com', 'Reply To')
                    ->payload([
                        'bar' => 'foo',
                        'baz' => 'foo2',
                    ]);
            }
        };

        $notifiable = new class {
            use Notifiable;
        };

        $channel = new SendGridChannel(
            $this->mockSendgrid()
        );

        $this->app->instance(SendGridChannel::class, $channel);

        $message = $notification->toSendGrid($notifiable);

        $this->assertEquals($message->templateId, 'sendgrid-template-id');
        $this->assertEquals($message->from->getEmail(), 'test@example.com');
        $this->assertEquals($message->from->getName(), 'Example User');
        $this->assertEquals($message->tos[0]->getEmail(), 'test+test1@example.com');
        $this->assertEquals($message->tos[0]->getName(), 'Example User1');
        $this->assertEquals($message->payload['bar'], 'foo');
        $this->assertEquals($message->payload['baz'], 'foo2');
        $this->assertEquals($message->replyTo->getEmail(), 'replyto@example.com');
        $this->assertEquals($message->replyTo->getName(), 'Reply To');
        $this->assertEquals($message->sandboxMode, false);

        $notifiable->notify($notification);

        Event::assertDispatched(
            NotificationSent::class,
            fn($event) => $event->channel === SendGridChannel::class
        );

        Event::assertDispatched(
            NotificationSent::class,
            fn($event) => $event->response instanceof Response
        );
    }

    public function testDefaultToAddress()
    {
        Event::fake();

        $channel = new SendGridChannel($this->mockSendgrid());

        $notification = new class extends Notification {
            public $sendgridMessage;

            public function via()
            {
                return [SendGridChannel::class];
            }

            public function toSendGrid($notifiable)
            {
                $this->sendgridMessage = (new SendGridMessage('sendgrid-template-id'))
                    ->from('test@example.com', 'Example User')
                    ->replyTo('replyto@example.com', 'Reply To')
                    ->payload([
                        'bar' => 'foo',
                        'baz' => 'foo2',
                    ]);

                return $this->sendgridMessage;
            }
        };

        $notifiable = new class {
            use Notifiable;

            public function routeNotificationForMail($notification)
            {
                return 'john@example.com';
            }
        };

        $channel->send($notifiable, $notification);
        $message = $notification->sendgridMessage;
        $this->assertEquals($message->tos[0]->getEmail(), 'john@example.com');

        // Let's also support returning an array (email => name)
        // https://laravel.com/docs/10.x/notifications#customizing-the-recipient
        $notifiableWithEmailAndName = new class {
            use Notifiable;

            public function routeNotificationForMail($notification)
            {
                return [
                    'john@example.com' => 'John Doe',
                ];
            }
        };

        $channel->send($notifiableWithEmailAndName, $notification);
        $message = $notification->sendgridMessage;

        $this->assertEquals($message->tos[0]->getEmail(), 'john@example.com');
        $this->assertEquals($message->tos[0]->getName(), 'John Doe');

        //Also support multiple recipients
        $notifiableWithEmailAndName = new class {
            use Notifiable;

            public function routeNotificationForMail($notification)
            {
                return [
                    'john@example.com' => 'John Doe',
                    'jane@example.com' => 'Jane Doe',
                ];
            }
        };

        $channel->send($notifiableWithEmailAndName, $notification);
        $message = $notification->sendgridMessage;

        $this->assertEquals($message->tos[0]->getEmail(), 'john@example.com');
        $this->assertEquals($message->tos[0]->getName(), 'John Doe');
        $this->assertEquals($message->tos[1]->getEmail(), 'jane@example.com');
        $this->assertEquals($message->tos[1]->getName(), 'Jane Doe');
    }

    public function testSandboxModeWhenEveryRecipientIsOnATestDomain()
    {
        $mail = $this->sendTo(['loadtest0001@bookingznow.test' => 'Load Test', 'LT.Staff@LOADTEST.bookingznow.TEST' => null], true);

        $this->assertTrue($mail->getMailSettings()->getSandboxMode()->getEnable());
        $this->assertSame('loadtest0001@bookingznow.test', $mail->getPersonalizations()[0]->getTos()[0]->getEmail());
    }

    public function testRealDeliveryWhenAnyRecipientIsNotOnATestDomain()
    {
        $mail = $this->sendTo(['loadtest0001@bookingznow.test' => null, 'player.test@example.com' => null], true);

        $this->assertFalse($mail->getMailSettings()->getSandboxMode()->getEnable());
    }

    public function testSandboxModeIsDecidedBeforeTheTestRecipientRedirect()
    {
        $loadTest = $this->sendTo(['loadtest0001@bookingznow.test' => null], false, 'staging@example.com');
        $real = $this->sendTo(['player@example.com' => null], false, 'staging@example.com');

        $this->assertTrue($loadTest->getMailSettings()->getSandboxMode()->getEnable());
        $this->assertSame('staging@example.com', $loadTest->getPersonalizations()[0]->getTos()[0]->getEmail());
        $this->assertFalse($real->getMailSettings()->getSandboxMode()->getEnable());
        $this->assertSame('staging@example.com', $real->getPersonalizations()[0]->getTos()[0]->getEmail());
    }

    /**
     * @param array<string, string|null> $recipients
     */
    private function sendTo(array $recipients, bool $liveMode, ?string $testRecipient = null): \SendGrid\Mail\Mail
    {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('statusCode')->andReturn(202);

        $sent = null;
        $sendgrid = Mockery::mock(new SendGrid('x'));
        $sendgrid->shouldReceive('send')->once()->with(Mockery::capture($sent))->andReturn($response);
        $this->app->instance(SendGridChannel::class, new SendGridChannel($sendgrid, $testRecipient, $liveMode));
        // The channel manager keeps the first channel it resolves; each send here needs its own.
        $this->app->make(ChannelManager::class)->forgetDrivers();

        $notifiable = new class($recipients) {
            use Notifiable;

            private $recipients;

            public function __construct(array $recipients)
            {
                $this->recipients = $recipients;
            }

            public function routeNotificationForMail($notification)
            {
                return $this->recipients;
            }
        };

        $notifiable->notify(new class extends Notification {
            public function via()
            {
                return [SendGridChannel::class];
            }

            public function toSendGrid($notifiable)
            {
                return (new SendGridMessage('sendgrid-template-id'))->from('test@example.com', 'Example User');
            }
        });

        return $sent;
    }

    private function mockSendgrid($statusCode = 200)
    {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('statusCode')->andReturn($statusCode);

        $sendgrid = Mockery::mock(new SendGrid('x'));
        $sendgrid->shouldReceive('send')->andReturn($response);

        return $sendgrid;
    }
}
