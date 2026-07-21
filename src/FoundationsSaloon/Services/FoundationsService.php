<?php

use FoundationsSaloon\FoundationsConnector;
use FoundationsSaloon\Requests\PostJournalEntriesRequest;
use Illuminate\Support\Facades\Log;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use Saloon\Http\Request;
use Saloon\Http\Response;

class FoundationsService
{
    protected FoundationsConnector $connector;

    protected AccessTokenAuthenticator $authenticator;

    public function __construct(
        ?FoundationsConnector $connector = null,
    ) {
        $this->setUpAuthentication($connector);
    }

    public function setReapitCustomer(string $customer): void
    {
        $this->connector->setReapitCustomer($customer);
    }

    public function useUnsubFlowCredentials(): void
    {
        $connector = new FoundationsConnector(
            useUnsubFlowCredentials: true
        );

        $this->setUpAuthentication($connector);
    }

    public function useJournalEntryAppCredentials(): void
    {
        $connector = new FoundationsConnector(
            useJournalEntryAppCredentials: true
        );

        $this->setUpAuthentication($connector);
    }

    /*
        This is only to be used in rare cases where we think a single method
        or function may run for longer than a token's lifetime.
    */
    public function ensureConnectorIsAuthenticated(string $customer): void
    {
        // If the authenticator has expired we need to effectively re-build the connector
        if ($this->authenticator->hasExpired()) {
            $this->setUpAuthentication();
            $this->setReapitCustomer($customer);
        }
    }

    private function setUpAuthentication(?FoundationsConnector $connector = null): void
    {
        if ($connector === null) {
            $connector = new FoundationsConnector();
        }

        $this->connector = $connector;
        $this->authenticator = new AccessTokenAuthenticator('');

        if (config('app.env', 'testing') !== 'testing') {
            /** @var AccessTokenAuthenticator $authenticator */
            $authenticator = $this->connector->getAccessToken();
            $this->authenticator = $authenticator;
            $this->connector->authenticate($this->authenticator);
        }
    }

    private function isRecordArchived(Request $request): bool
    {
        $request->query()->add('fromArchive', 'true');

        $response = $this->connector->send($request);

        if (! $response->successful()) {
            $this->handleRequestFail($request, $response);
            return false;
        }

        $resultsArray = json_decode($response->body(), true);
        $resultsArray = $resultsArray['_embedded'] ?? [];

        return count($resultsArray) > 0;
    }

    public function storeContactJournalEntry(string $contactId, string $message): Response
    {
        $request = new PostJournalEntriesRequest(
            'MI',
            'contact',
            $contactId,
            $message . ' - MailFlow'
        );

        return $this->connector->send($request);
    }

    private function handleRequestFail(Request $request, Response $response): void
    {
        $responseCode = $response->status();

        $errorContext = [
            'requestClass' => get_class($request),
            'requestQuery' => $request->query()?->all() ?? [],
            'requestEndpoint' => $request->resolveEndpoint(),
            'responseStatusCode' => $response->status(),
            'responseBody' => $response->body(),
        ];

        if ($responseCode >= 500) {
            Log::info('Request to Foundations failed due to Reapit service error', $errorContext);
            return;
        }

        Log::error('Request to Foundations failed', $errorContext);
    }
}
