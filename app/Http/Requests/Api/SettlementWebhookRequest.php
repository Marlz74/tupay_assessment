<?php

namespace App\Http\Requests\Api;

use App\Enums\SettlementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SettlementWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|Enum>> */
    public function rules(): array
    {
        return [
            'provider_reference' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::enum(SettlementStatus::class)],
            'wallet_id' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3'],
        ];
    }
}
