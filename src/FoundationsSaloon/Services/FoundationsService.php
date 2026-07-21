<?php

use FoundationsSaloon\FoundationsConnector;
use FoundationsSaloon\Requests\GetAreasRequest;
use FoundationsSaloon\Requests\GetContactsRequest;
use FoundationsSaloon\Requests\GetJournalEntriesRequest;
use FoundationsSaloon\Requests\PostJournalEntriesRequest;
use FoundationsSaloon\Requests\UpdateContactRequest;
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

    public function storeApplicantJournalEntry(string $applicantId, string $message): Response
    {
        $request = new PostJournalEntriesRequest(
            'MI',
            'applicant',
            $applicantId,
            $message . ' - MailFlow'
        );

        return $this->connector->send($request);
    }

    public function storePropertyMatchJournalEntry(string $applicantId, string $propertyId): Response
    {
        $request = new PostJournalEntriesRequest(
            typeId: 'MA',
            associatedType: 'applicant',
            associatedId: $applicantId,
            description: 'Matched Via Mailflow',
            propertyId: $propertyId
        );

        return $this->connector->send($request);
    }

    /**
     * @param  array<string,string|int>  $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getJournalEntries(array $queryParameters = []): ?array
    {
        $journalEntriesRequest = new GetJournalEntriesRequest();

        foreach ($queryParameters as $key => $value) {
            $journalEntriesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($journalEntriesRequest);
    }

    /**
     * @param  array<string,string|int|array<string>>  $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getAreas(array $queryParameters = []): ?array
    {
        $areasRequest = new GetAreasRequest();

        foreach ($queryParameters as $key => $value) {
            $areasRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($areasRequest);
    }

    /**
     * @param  array<string,string>  $changes
     */
    public function updateContact(string $contactRpsId, array $changes): bool
    {
        $contactRequest = new GetContactsRequest();
        $contactRequest->query()->add('id', $contactRpsId);

        $response = $this->connector->send($contactRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($contactRequest, $response);
            return false;
        }

        /** @var array<array<string,string>> $embeddedData */
        $embeddedData = $response->collect()->get('_embedded');
        $contactData = collect($embeddedData)->first();

        if (!$contactData) {
            Log::error('Could not get a contact', ['contactRpsId' => $contactRpsId]);

            return false;
        }

        $etag = $contactData['_eTag'] ?? null;

        if (! isset($etag)) {
            Log::error('Could not find etag for contact', ['contactData' => $contactData]);

            return false;
        }

        $updateRequest = new UpdateContactRequest($contactRpsId, $etag);

        foreach ($changes as $key => $value) {
            $updateRequest->body()->add($key, $value);
        }

        $response = $this->connector->send($updateRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($updateRequest, $response);
        }

        return $response->successful();
    }

    /** @return ?array<array<string,string|array<string>>> $results */
    private function getPaginatedResults(Request $request): ?array
    {
        $results = null;

        try {
            $paginator = $this->connector->paginate($request);
            $results = $paginator->collect()->all();

            $results = collect($results)
                ->filter(fn($item) => is_array($item))
                ->filter(fn($item) => isset(collect($item)->first()['created']))
                ->values()
                ->flatten(1)
                ->toArray();
        } catch (Exception $e) {
            Log::error(
                'Get paginated results request to Foundations failed',
                [
                    'requestType' => get_class($request),
                    'responseStatusCode' => $e->getCode(),
                    'responseBody' => $e->getMessage(),
                ]
            );
        }

        return $results;
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
