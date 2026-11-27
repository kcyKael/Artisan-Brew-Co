<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBrevoWebhookEventJob;
use App\Models\EmailWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrevoWebhookController extends Controller
{
    public function transactional(Request $request): JsonResponse
    {
        return $this->ingest($request, 'transactional');
    }

    public function marketing(Request $request): JsonResponse
    {
        return $this->ingest($request, 'marketing');
    }

    private function ingest(Request $request, string $scope): JsonResponse
    {
        $configuredToken = (string) config('services.brevo.webhook_token', '');
        if ($configuredToken === '') {
            return response()->json(['message' => 'Webhook secret not configured.'], 401);
        }

        $providedToken = $this->extractToken($request);
        if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json(['message' => 'Unauthorized webhook request.'], 401);
        }

        $rawContent = $request->getContent();
        if (strlen($rawContent) > 262144) {
            return response()->json(['message' => 'Payload too large.'], 413);
        }

        $payload = json_decode($rawContent, true);
        if (! is_array($payload) || empty($payload['event']) || ! is_string($payload['event'])) {
            return response()->json(['message' => 'Invalid Brevo webhook event payload.'], 422);
        }

        $eventType = trim((string) $payload['event']);
        $messageId = is_string($payload['message-id'] ?? null) ? trim((string) $payload['message-id']) : null;
        $campaignId = isset($payload['camp_id']) && is_numeric($payload['camp_id']) ? (int) $payload['camp_id'] : null;
        $email = is_string($payload['email'] ?? null) ? mb_strtolower(trim((string) $payload['email'])) : null;
        $occurredAt = $this->resolveOccurredAt($payload);

        $compositeParts = [
            $scope,
            mb_strtolower($eventType),
            $messageId ?? '',
            (string) ($campaignId ?? ''),
            $email ?? '',
            (string) ($payload['ts_event'] ?? $payload['ts_epoch'] ?? $payload['ts'] ?? $payload['date'] ?? ''),
        ];
        $eventKey = hash('sha256', implode('|', $compositeParts));

        try {
            $event = EmailWebhookEvent::query()->create([
                'event_key' => $eventKey,
                'scope' => $scope,
                'event_type' => mb_substr($eventType, 0, 50),
                'provider_message_id' => $messageId ? mb_substr($messageId, 0, 191) : null,
                'provider_campaign_id' => $campaignId,
                'email' => $email ? mb_substr($email, 0, 191) : null,
                'occurred_at' => $occurredAt,
                'status' => EmailWebhookEvent::STATUS_PENDING,
                'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);

            ProcessBrevoWebhookEventJob::dispatch($event->id)
                ->onQueue((string) config('email.queues.webhooks', 'emails-webhooks'));
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return response()->json(['status' => 'duplicate_ignored'], 202);
            }
            throw $e;
        }

        return response()->json(['status' => 'accepted', 'event_id' => $event->id], 202);
    }

    private function extractToken(Request $request): string
    {
        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            return trim($bearer);
        }

        $header = $request->header('X-Brevo-Webhook-Token');
        if (is_string($header) && $header !== '') {
            return trim($header);
        }

        return '';
    }

    private function resolveOccurredAt(array $payload): CarbonImmutable
    {
        if (isset($payload['ts_event']) && is_numeric($payload['ts_event'])) {
            return CarbonImmutable::createFromTimestampUTC((int) $payload['ts_event']);
        }

        if (isset($payload['ts_epoch']) && is_numeric($payload['ts_epoch'])) {
            $seconds = (int) floor(((float) $payload['ts_epoch']) / 1000);

            return CarbonImmutable::createFromTimestampUTC($seconds);
        }

        if (isset($payload['ts']) && is_numeric($payload['ts'])) {
            return CarbonImmutable::createFromTimestampUTC((int) $payload['ts']);
        }

        if (! empty($payload['date']) && is_string($payload['date'])) {
            try {
                return CarbonImmutable::parse($payload['date'], 'UTC');
            } catch (\Throwable) {
            }
        }

        return CarbonImmutable::now('UTC');
    }
}
