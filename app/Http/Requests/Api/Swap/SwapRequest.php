<?php

namespace App\Http\Requests\Api\Swap;

use Illuminate\Foundation\Http\FormRequest;

class SwapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'source_wallet_id' => ['required', 'uuid'],
            'destination_wallet_id' => ['required', 'uuid', 'different:source_wallet_id'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
