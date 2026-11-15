<?php

namespace AbdulWahhabKhan\DeviceAuth\Notifications;

use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
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
        return config('device-auth.notifications', ['mail']);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $d = $this->device;
        $approved = $d->status === DeviceStatus::Active;

        $mail = (new MailMessage)
            ->subject($approved
                ? "Device approved: {$d->name}"
                : "Device {$d->status->value}: {$d->name}");

        if ($approved) {
            return $mail
                ->line("Your device \"{$d->name}\" ({$d->reference()}) was approved.")
                ->line('Open the application on that device and choose Verify device to continue.')
                ->action('Open application', url(config('device-auth.home', '/')));
        }

        return $mail
            ->line("Your device \"{$d->name}\" ({$d->reference()}) is {$d->status->value}.")
            ->when($d->review_note, fn (MailMessage $m) => $m->line("Reason: {$d->review_note}"))
            ->line('Contact your administrator if you need access from this device.');
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'device_id' => $this->device->getKey(),
            'reference' => $this->device->reference(),
            'status' => $this->device->status->value,
            'message' => "Device {$this->device->name} is {$this->device->status->value}.",
        ];
    }
}
