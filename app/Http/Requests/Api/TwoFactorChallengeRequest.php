<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'totp' => ['required', 'string', 'size:6'],
            'action' => ['required', 'string', 'in:swap'],
            'action_payload' => ['required', 'array'],
            'action_payload.source_wallet_id' => ['required', 'uuid'],
            'action_payload.destination_wallet_id' => ['required', 'uuid'],
            'action_payload.amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
