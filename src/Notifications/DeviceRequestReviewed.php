<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Notifications;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the person who asked for access once an administrator decided.
 */
class DeviceRequestReviewed extends Notification
{
    public function __construct(public readonly Device $device) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return DeviceAuth::notificationChannels();
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $device = $this->device;
        $replace = [
            'name' => $device->name,
            'reference' => $device->reference(),
            'status' => $device->status->value,
        ];

        if ($device->isActive()) {
            return (new MailMessage)
                ->subject(__('device-auth::messages.mail.approved_subject', $replace))
                ->line(__('device-auth::messages.mail.approved_line', $replace))
                ->line(__('device-auth::messages.mail.approved_next'))
                ->action(__('device-auth::messages.mail.approved_action'), url(DeviceAuth::home()));
        }

        return (new MailMessage)
            ->subject(__('device-auth::messages.mail.reviewed_subject', $replace))
            ->line(__('device-auth::messages.mail.reviewed_line', $replace))
            ->when($device->review_note !== null && $device->review_note !== '', static fn (MailMessage $mail): MailMessage => $mail->line(
                __('device-auth::messages.mail.reviewed_reason', ['reason' => (string) $device->review_note]),
            ))
            ->line(__('device-auth::messages.mail.reviewed_contact'));
    }

    /** @return array{device_id: int, reference: string, status: string, message: string} */
    public function toArray(mixed $notifiable): array
    {
        return [
            'device_id' => $this->device->id,
            'reference' => $this->device->reference(),
            'status' => $this->device->status->value,
            'message' => __('device-auth::messages.mail.reviewed_summary', [
                'name' => $this->device->name,
                'status' => $this->device->status->value,
            ]),
        ];
    }
}
