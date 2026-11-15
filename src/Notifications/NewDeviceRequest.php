<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Notifications;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
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
        return DeviceAuth::notificationChannels();
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $device = $this->device;
        $requester = DeviceAuth::describeUser($device->requester);

        return (new MailMessage)
            ->subject(__('device-auth::messages.mail.request_subject', ['reference' => $device->reference(), 'name' => $device->name]))
            ->line(__('device-auth::messages.mail.request_intro', [
                'user' => $requester['name'] ?? __('device-auth::messages.mail.request_someone'),
            ]))
            ->line(__('device-auth::messages.mail.request_device', [
                'name' => $device->name,
                'description' => $device->describe() !== '' ? $device->describe() : __('device-auth::messages.mail.request_unknown_device'),
            ]))
            ->line(__('device-auth::messages.mail.request_ip', ['ip' => (string) $device->request_ip]))
            ->when($device->request_note !== null && $device->request_note !== '', static fn (MailMessage $mail): MailMessage => $mail->line(
                __('device-auth::messages.mail.request_reason', ['reason' => (string) $device->request_note]),
            ))
            ->action(__('device-auth::messages.mail.request_action'), route('device-auth.admin.show', $device));
    }

    /** @return array{device_id: int, reference: string, name: string, message: string} */
    public function toArray(mixed $notifiable): array
    {
        return [
            'device_id' => $this->device->id,
            'reference' => $this->device->reference(),
            'name' => $this->device->name,
            'message' => __('device-auth::messages.mail.request_summary', ['name' => $this->device->name]),
        ];
    }
}
