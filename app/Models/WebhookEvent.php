<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $provider_reference
 * @property SettlementStatus $status
 * @property array<string, mixed> $payload
 * @property Carbon|null $processed_at
 */
class WebhookEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider_reference',
        'status',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
