<?php

namespace App\Services\Email;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BrevoClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.brevo.api_key'));
    }

    public function isLiveSendEnabled(): bool
    {
        return (bool) config('services.brevo.live_send_enabled', false) && $this->isConfigured();
    }

    public function sendTransactionalEmail(array $payload, ?string $idempotencyKey = null): array
    {
        $this->ensureConfigured();

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers = is_array($payload['headers'] ?? null) ? $payload['headers'] : [];
            $headers['idempotencyKey'] = $idempotencyKey;
            $payload['headers'] = $headers;
        }

        try {
            $response = $this->http()->post('/smtp/email', $payload);
        } catch (ConnectionException) {
            throw new BrevoApiException(
                'Connection timed out or failed before a Brevo response was received.',
                null,
                'connection_uncertain',
                false,
                true,
            );
        }

        if ($response->successful()) {
            $data = $response->json() ?: [];

            return [
                'message_id' => $data['messageId'] ?? ($data['messageIds'][0] ?? null),
                'http_status' => $response->status(),
                'duplicate_idempotent' => false,
            ];
        }

        $body = $response->json() ?: [];
        $code = is_string($body['code'] ?? null) ? $body['code'] : null;

        if ($response->status() === 400 && $code === 'duplicate_parameter' && $idempotencyKey) {
            return [
                'message_id' => null,
                'http_status' => 400,
                'duplicate_idempotent' => true,
            ];
        }

        throw $this->mapErrorResponse($response);
    }

    public function upsertContact(string $email, array $attributes = [], ?int $listId = null): array
    {
        $this->ensureConfigured();

        $payload = [
            'email' => mb_strtolower(trim($email)),
            'updateEnabled' => true,
        ];

        if ($attributes !== []) {
            $payload['attributes'] = $attributes;
        }

        if ($listId !== null && $listId > 0) {
            $payload['listIds'] = [$listId];
        }

        try {
            $response = $this->http()->post('/contacts', $payload);
        } catch (ConnectionException) {
            throw new BrevoApiException('Brevo Contacts request timed out.', null, 'connection_uncertain', true, true);
        }

        if ($response->successful() || $response->status() === 204) {
            $data = $response->json() ?: [];

            return [
                'contact_id' => isset($data['id']) ? (string) $data['id'] : null,
                'http_status' => $response->status(),
            ];
        }

        throw $this->mapErrorResponse($response);
    }

    public function unsubscribeContact(string $email, ?int $listId = null): void
    {
        $this->ensureConfigured();

        $encoded = rawurlencode(mb_strtolower(trim($email)));
        $payload = ['emailBlacklisted' => true];

        if ($listId !== null && $listId > 0) {
            $payload['unlinkListIds'] = [$listId];
        }

        try {
            $response = $this->http()->put("/contacts/{$encoded}", $payload);
        } catch (ConnectionException) {
            throw new BrevoApiException('Brevo Contacts unsubscribe request timed out.', null, 'connection_uncertain', true, true);
        }

        if ($response->status() === 404) {
            return;
        }

        if (! $response->successful() && $response->status() !== 204) {
            throw $this->mapErrorResponse($response);
        }
    }

    public function createCampaign(array $payload): int
    {
        $this->ensureConfigured();

        try {
            $response = $this->http()->post('/emailCampaigns', $payload);
        } catch (ConnectionException) {
            throw new BrevoApiException('Brevo create campaign request timed out.', null, 'connection_uncertain', false, true);
        }

        if ($response->successful()) {
            $data = $response->json() ?: [];
            if (isset($data['id'])) {
                return (int) $data['id'];
            }
        }

        throw $this->mapErrorResponse($response);
    }

    public function sendCampaignTest(int $campaignId, array $emails): void
    {
        $this->ensureConfigured();

        try {
            $response = $this->http()->post("/emailCampaigns/{$campaignId}/sendTest", [
                'emailTo' => array_values($emails),
            ]);
        } catch (ConnectionException) {
            throw new BrevoApiException('Brevo campaign test send timed out.', null, 'connection_uncertain', false, true);
        }

        if (! $response->successful() && $response->status() !== 204) {
            throw $this->mapErrorResponse($response);
        }
    }

    public function sendCampaignNow(int $campaignId): void
    {
        $this->ensureConfigured();

        try {
            $response = $this->http()->post("/emailCampaigns/{$campaignId}/sendNow");
        } catch (ConnectionException) {
            throw new BrevoApiException('Brevo campaign send timed out.', null, 'connection_uncertain', false, true);
        }

        if (! $response->successful() && $response->status() !== 204) {
            throw $this->mapErrorResponse($response);
        }
    }

    public function getCampaign(int $campaignId): array
    {
        $this->ensureConfigured();

        try {
            $response = $this->http()->get("/emailCampaigns/{$campaignId}");
        } catch (ConnectionException) {
            throw new BrevoApiException('Brevo get campaign request timed out.', null, 'connection_uncertain', true, false);
        }

        if ($response->successful()) {
            return $response->json() ?: [];
        }

        throw $this->mapErrorResponse($response);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.brevo.base_url', 'https://api.brevo.com/v3'))
            ->timeout((int) config('services.brevo.timeout', 15))
            ->connectTimeout((int) config('services.brevo.connect_timeout', 5))
            ->withHeaders([
                'api-key' => (string) config('services.brevo.api_key', ''),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ]);
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new BrevoApiException(
                'Brevo API key is not configured.',
                401,
                'missing_api_key',
                false,
                false,
            );
        }
    }

    private function mapErrorResponse(Response $response): BrevoApiException
    {
        $status = $response->status();
        $body = $response->json();
        $errorCode = is_array($body) && is_string($body['code'] ?? null) ? $body['code'] : 'api_error';
        $providerMsg = is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : '';

        if ($status === 429) {
            $reset = $response->header('x-sib-ratelimit-reset') ?? $response->header('Retry-After');
            $wait = is_numeric($reset) ? max(5, min(300, (int) $reset)) : 60;

            return new BrevoApiException(
                'Brevo rate limit reached. Delivery will retry automatically.',
                $status,
                $errorCode,
                true,
                false,
                $wait,
            );
        }

        if ($status >= 500) {
            return new BrevoApiException(
                'Brevo service temporarily unavailable.',
                $status,
                $errorCode,
                true,
                false,
                30,
            );
        }

        if ($status === 401 || $status === 403) {
            return new BrevoApiException(
                'Brevo authentication rejected. Check BREVO_API_KEY and account permissions.',
                $status,
                $errorCode,
                false,
                false,
            );
        }

        if ($status === 402 || str_contains(mb_strtolower($errorCode), 'not_enough_credits')) {
            return new BrevoApiException(
                'Brevo email quota or credits exhausted.',
                $status,
                $errorCode,
                false,
                false,
            );
        }

        $sanitized = $providerMsg !== ''
            ? mb_substr(strip_tags($providerMsg), 0, 240)
            : "Brevo rejected request (HTTP {$status}).";

        return new BrevoApiException($sanitized, $status, $errorCode, false, false);
    }
}
