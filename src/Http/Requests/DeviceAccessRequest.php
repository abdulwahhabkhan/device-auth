<?php

namespace AbdulWahhabKhan\DeviceAuth\Http\Requests;

use AbdulWahhabKhan\DeviceAuth\Services\DeviceMetadata;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Request access for this device": the new public key plus what the
 * person and their browser tell us about the device.
 */
class DeviceAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'credential' => ['required', 'array'],
            'credential.clientDataJSON' => ['required', 'string', 'max:8192'],
            'credential.attestationObject' => ['required', 'string', 'max:32768'],
            'client' => ['nullable', 'array'],
        ];

        foreach (DeviceMetadata::CLIENT_KEYS as $key) {
            $rules["client.$key"] = ['nullable', 'max:100'];
        }

        return $rules;
    }

    /** @return array{clientDataJSON: string, attestationObject: string} */
    public function credential(): array
    {
        /** @var array{clientDataJSON: string, attestationObject: string} */
        return $this->validated('credential');
    }
}
