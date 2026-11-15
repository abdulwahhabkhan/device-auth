<?php

namespace AbdulWahhabKhan\DeviceAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The browser's answer to navigator.credentials.get(), base64url-encoded.
 */
class AssertionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:1024'],
            'clientDataJSON' => ['required', 'string', 'max:8192'],
            'authenticatorData' => ['required', 'string', 'max:8192'],
            'signature' => ['required', 'string', 'max:2048'],
        ];
    }

    /** @return array{id: string, clientDataJSON: string, authenticatorData: string, signature: string} */
    public function assertion(): array
    {
        /** @var array{id: string, clientDataJSON: string, authenticatorData: string, signature: string} */
        return $this->only(['id', 'clientDataJSON', 'authenticatorData', 'signature']);
    }
}
