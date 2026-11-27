<?php

namespace App\Jobs;

use App\Models\EmailDelivery;
use App\Services\Email\BrevoApiException;
use App\Services\Email\BrevoClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SendTransactionalEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $emailDeliveryId)
    {
    }

    public function backoff(): array
    {
        return [15, 45, 120, 300];
    }

    public function handle(BrevoClient $brevo): void
    {
        $claimed = DB::transaction(function (): ?EmailDelivery {
            $delivery = EmailDelivery::query()
                ->whereKey($this->emailDeliveryId)
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                return null;
            }

            if (! in_array($delivery->status, [
                EmailDelivery::STATUS_QUEUED,
                EmailDelivery::STATUS_FAILED,
            ], true)) {
                return null;
            }

            $delivery->status = EmailDelivery::STATUS_PROCESSING;
            $delivery->attempts = $delivery->attempts + 1;
            $delivery->save();

            return $delivery;
        });

        if (! $claimed) {
            return;
        }

        if ($claimed->expires_at !== null && $claimed->expires_at->isPast()) {
            $claimed->status = EmailDelivery::STATUS_EXPIRED;
            $claimed->last_error_code = 'token_expired';
            $claimed->last_error = 'Delivery aborted because the link or token expired before send.';
            if ($claimed->is_sensitive) {
                $claimed->payload_encrypted = null;
                $claimed->payload_pruned_at = now('UTC');
            }
            $claimed->save();

            return;
        }

        if (empty($claimed->payload_encrypted)) {
            $claimed->status = EmailDelivery::STATUS_FAILED;
            $claimed->last_error_code = 'missing_payload';
            $claimed->last_error = 'Encrypted email payload was already pruned or missing.';
            $claimed->failed_at = now('UTC');
            $claimed->save();

            return;
        }

        $payload = json_decode(Crypt::decryptString($claimed->payload_encrypted), true);
        if (! is_array($payload)) {
            $claimed->status = EmailDelivery::STATUS_FAILED;
            $claimed->last_error_code = 'invalid_payload';
            $claimed->last_error = 'Unable to decode queued email payload.';
            $claimed->failed_at = now('UTC');
            $claimed->save();

            return;
        }

        if (! $brevo->isLiveSendEnabled()) {
            $claimed->status = EmailDelivery::STATUS_SIMULATED;
            $claimed->last_http_status = null;
            $claimed->last_error_code = 'live_send_disabled';
            $claimed->last_error = 'Preview/fake mode active: BREVO_LIVE_SEND_ENABLED is false or BREVO_API_KEY is unset.';
            if ($claimed->is_sensitive) {
                $claimed->payload_encrypted = null;
                $claimed->payload_pruned_at = now('UTC');
            }
            $claimed->save();

            return;
        }

        try {
            $result = $brevo->sendTransactionalEmail($payload, $claimed->idempotency_key);
            $claimed->status = EmailDelivery::STATUS_ACCEPTED;
            $claimed->provider_message_id = $result['message_id'] ?? $claimed->provider_message_id;
            $claimed->last_http_status = $result['http_status'] ?? 201;
            $claimed->last_error_code = null;
            $claimed->last_error = null;
            $claimed->accepted_at = now('UTC');
            if ($claimed->is_sensitive) {
                $claimed->payload_encrypted = null;
                $claimed->payload_pruned_at = now('UTC');
            }
            $claimed->save();
        } catch (BrevoApiException $e) {
            $claimed->last_http_status = $e->statusCode;
            $claimed->last_error_code = $e->errorCode;
            $claimed->last_error = mb_substr($e->getMessage(), 0, 490);

            if ($e->isUncertain) {
                $claimed->status = EmailDelivery::STATUS_UNCERTAIN;
                $claimed->save();

                return;
            }

            $maxRetries = max(1, (int) config('services.brevo.max_retries', 4));
            if ($e->isRetryable && $claimed->attempts < $maxRetries) {
                $claimed->status = EmailDelivery::STATUS_QUEUED;
                $claimed->save();
                $delay = $e->retryAfterSeconds ?? min(300, 15 * (2 ** max(0, $claimed->attempts - 1)));
                $this->release($delay);

                return;
            }

            $claimed->status = EmailDelivery::STATUS_FAILED;
            $claimed->failed_at = now('UTC');
            $claimed->save();
        }
    }
}
