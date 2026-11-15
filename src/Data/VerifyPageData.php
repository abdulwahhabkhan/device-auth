<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Data;

use AbdulWahhabKhan\DeviceAuth\Data\Concerns\SerializesToArray;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The "verify this device" page shown after the password.
 *
 * @phpstan-import-type UserSummary from DeviceAuth
 */
final readonly class VerifyPageData implements PageData
{
    use SerializesToArray;

    /**
     * @param  UserSummary|null  $user
     */
    public function __construct(
        public ?array $user,
        public ?string $contact,
        public string $verifyOptionsUrl,
        public string $verifyUrl,
        public string $requestOptionsUrl,
        public string $requestUrl,
        public ?string $logoutUrl,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $logout = config('device-auth.logout_route');

        return new self(
            user: DeviceAuth::describeUser($request->user()),
            contact: DeviceAuth::adminContact(),
            verifyOptionsUrl: route('device-auth.verify.options'),
            verifyUrl: route('device-auth.verify.store'),
            requestOptionsUrl: route('device-auth.request.options'),
            requestUrl: route('device-auth.request.store'),
            logoutUrl: is_string($logout) && Route::has($logout) ? route($logout) : null,
        );
    }

    /**
     * @return array{
     *     user: UserSummary|null,
     *     contact: string|null,
     *     urls: array{verifyOptions: string, verify: string, requestOptions: string, request: string, logout: string|null}
     * }
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'contact' => $this->contact,
            'urls' => [
                'verifyOptions' => $this->verifyOptionsUrl,
                'verify' => $this->verifyUrl,
                'requestOptions' => $this->requestOptionsUrl,
                'request' => $this->requestUrl,
                'logout' => $this->logoutUrl,
            ],
        ];
    }
}
