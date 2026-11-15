<?php

namespace AbdulWahhabKhan\DeviceAuth\Notifications;

use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to administrators when a device asks for access.
 */
class NewDeviceRequest extends Notification
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
        $requester = $d->requester;

        return (new MailMessage)
            ->subject("Device access request {$d->reference()}: {$d->name}")
            ->line(sprintf(
                '%s asked for access from a new device.',
                data_get($requester, 'name') ?: data_get($requester, 'email') ?: 'A user',
            ))
            ->line("Device: {$d->name} ({$this->describe()})")
            ->line("IP address: {$d->request_ip}")
            ->when($d->request_note, fn (MailMessage $m) => $m->line("Reason: {$d->request_note}"))
            ->action('Review request', route('device-auth.admin.show', $d));
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'device_id' => $this->device->getKey(),
            'reference' => $this->device->reference(),
            'name' => $this->device->name,
            'message' => "New device access request: {$this->device->name}",
        ];
    }

    private function describe(): string
    {
        return collect([$this->device->device_type, $this->device->os, $this->device->browser])
            ->filter()
            ->implode(', ') ?: 'unknown device';
    }
}
