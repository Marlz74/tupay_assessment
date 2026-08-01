<?php

namespace App\Actions;

use App\Enums\SettlementStatus;
use App\Jobs\ProcessSettlementWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;

final class IngestSettlementWebhookAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{accepted: bool, duplicate: bool, webhook_event_id: string|null}
     */
    public function execute(array $payload): array
    {
        $status = SettlementStatus::from($payload['status']);

        try {
            $event = WebhookEvent::create([
                'provider_reference' => $payload['provider_reference'],
                'status' => $status,
                'payload' => $payload,
            ]);

            ProcessSettlementWebhookJob::dispatch($event->id);

            return [
                'accepted' => true,
                'duplicate' => false,
                'webhook_event_id' => $event->id,
            ];
        } catch (UniqueConstraintViolationException) {
            $existing = WebhookEvent::where('provider_reference', $payload['provider_reference'])
                ->first();

            // Re-dispatch so out-of-order and status upgrades can be applied
            if ($existing !== null) {
                // Merge latest payload onto a follow-up job keyed by existing id
                $existingPayload = is_array($existing->payload) ? $existing->payload : [];
                $existing->payload = array_merge($existingPayload, $payload);
                $existing->save();

                ProcessSettlementWebhookJob::dispatch($existing->id);
            }

            return [
                'accepted' => true,
                'duplicate' => true,
                'webhook_event_id' => $existing?->id,
            ];
        }
    }
}
