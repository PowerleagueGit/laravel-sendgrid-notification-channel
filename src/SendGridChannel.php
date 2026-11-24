<?php

namespace NotificationChannels\SendGrid;

use SendGrid;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Notification;
use NotificationChannels\SendGrid\Exceptions\CouldNotSendNotification;

class SendGridChannel
{
    /**
     * @var SendGrid
     */
    private $sendGrid;

    /**
     * @var string|null
     */
    private $testRecipient;

    /**
     * @var bool
     */
    private $liveMode;

    public function __construct(SendGrid $sendGrid, ?string $testRecipient = null, bool $liveMode = false)
    {
        $this->sendGrid = $sendGrid;
        $this->testRecipient = $testRecipient;
        $this->liveMode = $liveMode;
    }

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     *
     * @throws \NotificationChannels\SendGrid\Exceptions\CouldNotSendNotification
     */
    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toSendGrid')) {
            throw new Exception('You must implement toSendGrid in the notification class for SendGrid channel.');
        }

        /**
         * @var SendGridMessage
         */
        $message = $notification->toSendGrid($notifiable);

        if (empty($message->from)) {
            $message->from(
                config('mail.from.address'),
                config('mail.from.name')
            );
        }

        if (empty($message->tos)) {
            $to = $notifiable->routeNotificationFor('mail', $notification);

            // Handle the case where routeNotificationForMail returns an array (email => name)
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    $message->to($email, $name);
                }
            } else {
                $message->to($to);
            }
        }

        // .test is reserved for load tests: SendGrid validates the send without delivering or charging for it.
        if ($this->allRecipientsOnTestDomains($message)) {
            $message->enableSandboxMode();
        }

        // Safety check: redirect to test recipient unless explicitly in live mode
        if (! $this->liveMode) {
            if (empty($this->testRecipient)) {
                // No test recipient configured - silently drop the email
                Log::debug('SendGrid: Email dropped (not in live mode and no test recipient configured)', [
                    'notification' => get_class($notification),
                    'original_recipients' => array_map(fn($to) => $to->getEmail(), $message->tos),
                ]);
                return null;
            }
            $message->tos = [];
            $message->to($this->testRecipient, 'Test Recipient');
        }

        if (! ($message instanceof SendGridMessage)) {
            throw new Exception('toSendGrid must return an instance of SendGridMessage.');
        }

        $response = $this->sendGrid->send($message->build());

        if ($response->statusCode() < 200 || $response->statusCode() >= 300) {
            throw CouldNotSendNotification::serviceRespondedWithAnError(
                $response->body()
            );
        }

        return $response;
    }

    private function allRecipientsOnTestDomains(SendGridMessage $message): bool
    {
        if (empty($message->tos)) {
            return false;
        }

        foreach ($message->tos as $to) {
            if (substr(strtolower((string) $to->getEmail()), -5) !== '.test') {
                return false;
            }
        }

        return true;
    }
}
