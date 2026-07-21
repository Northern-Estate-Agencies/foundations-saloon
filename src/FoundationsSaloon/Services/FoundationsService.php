<?php

use App\Saloon\Foundations\Requests\UpdatePropertyRequest;
use FoundationsSaloon\FoundationsConnector;
use FoundationsSaloon\Requests\GetAreasRequest;
use FoundationsSaloon\Requests\GetCompaniesRequest;
use FoundationsSaloon\Requests\GetCompanyRequest;
use FoundationsSaloon\Requests\GetContactsRequest;
use FoundationsSaloon\Requests\GetJournalEntriesRequest;
use FoundationsSaloon\Requests\GetLandlordsRelationshipsRequest;
use FoundationsSaloon\Requests\GetLandlordsRequest;
use FoundationsSaloon\Requests\GetPropertiesRequest;
use FoundationsSaloon\Requests\GetVendorsRelationshipsRequest;
use FoundationsSaloon\Requests\GetVendorsRequest;
use FoundationsSaloon\Requests\PostJournalEntriesRequest;
use FoundationsSaloon\Requests\UpdateCompanyRequest;
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

    public function getCompanies(array $queryParameters = []): ?array
    {
        $companiesRequest = new GetCompaniesRequest;

        foreach ($queryParameters as $key => $value) {
            $companiesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($companiesRequest);
    }

    /**
     * @param  array<string,string>  $changes
     */
    public function updateCompany(string $companyRpsId, array $changes): bool
    {
        $companyRequest = new GetCompaniesRequest();
        $companyRequest->query()->add('id', $companyRpsId);

        $response = $this->connector->send($companyRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($companyRequest, $response);
            return false;
        }

        /** @var array<array<string,string>> $embeddedData */
        $embeddedData = $response->collect()->get('_embedded');
        $companyData = collect($embeddedData)->first();

        if (!$companyData) {
            Log::error('Could not get a company', ['companyRpsId' => $companyRpsId]);

            return false;
        }

        $etag = $companyData['_eTag'] ?? null;

        if (! isset($etag)) {
            Log::error('Could not find etag for company', ['companyData' => $companyData]);

            return false;
        }

        $updateRequest = new UpdateCompanyRequest($companyRpsId, $etag);

        foreach ($changes as $key => $value) {
            $updateRequest->body()->add($key, $value);
        }

        $response = $this->connector->send($updateRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($updateRequest, $response);
        }

        return $response->successful();
    }

    /**
     * @param  array<string,string>  $changes
     */
    public function updateProperty(string $propertyRpsId, array $changes): bool
    {
        $propertyRequest = new GetPropertiesRequest();
        $propertyRequest->query()->add('id', $propertyRpsId);

        $response = $this->connector->send($propertyRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($propertyRequest, $response);
            return false;
        }

        /** @var array<array<string,string>> $embeddedData */
        $embeddedData = $response->collect()->get('_embedded');
        $propertyData = collect($embeddedData)->first();

        if (!$propertyData) {
            Log::error('Could not get a property', ['propertyRpsId' => $propertyRpsId]);

            return false;
        }

        $etag = $propertyData['_eTag'] ?? null;
        if (! isset($etag)) {
            Log::error('Could not find etag for property', ['propertyData' => $propertyData]);
            return false;
        }

        $updateRequest = new UpdatePropertyRequest($propertyRpsId, $etag);

        foreach ($changes as $key => $value) {
            $updateRequest->body()->add($key, $value);
        }

        $response = $this->connector->send($updateRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($updateRequest, $response);
        }

        return $response->successful();
    }

    /**
     * @param  string  $ownerRpsId
     * @param  bool  $isVendor
     * @return array<string, string>|null
     */
    public function getPropertyOwnerRelationship($ownerRpsId, $isVendor): ?array
    {
        if (($ownerRpsId ?? '') === '') {
            Log::info("No owner ID set, returning null");
            return null;
        }

        if ($isVendor) {
            $getOwnerRequest = new GetVendorsRelationshipsRequest($ownerRpsId);
        } else {
            $getOwnerRequest = new GetLandlordsRelationshipsRequest($ownerRpsId);
        }

        $response = $this->connector->send($getOwnerRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($getOwnerRequest, $response);
            return null;
        }

        /** @var array<string,array<array<string,string>>> $propertyOwnerArray */
        $propertyOwnerArray = json_decode($response->body(), true);
        $propertyOwnerArray = $propertyOwnerArray['_embedded'];

        $propertyOwner = collect($propertyOwnerArray)
            ->filter(fn($item) => $item['associatedType'] === 'contact')
            ->first();

        return $propertyOwner;
    }

    /**
     * @return array<string, string>|null
     */
    public function getContact(string $contactRpsId): ?array
    {
        $contactRequest = new GetContactsRequest();
        $contactRequest->query()->add('id', $contactRpsId);

        $response = $this->connector->send($contactRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($contactRequest, $response);
            return null;
        }

        /** @var array<string,array<array<string,string>>> $contactArray */
        $contactArray = json_decode($response->body(), true);
        $contactArray = $contactArray['_embedded'];

        $contact = collect($contactArray)->first();

        return $contact;
    }

    public function getCompany(string $companyId, array $queryParameters = []): ?array
    {
        $companyRequest = new GetCompanyRequest($companyId);

        foreach ($queryParameters as $key => $value) {
            $companyRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($companyRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($companyRequest, $response);
            return null;
        }

        return json_decode($response->body(), true);
    }

    /**
     * @param  string  $ownerRpsId
     * @param  bool  $isVendor
     * @return array<string, string>|null
     */
    public function getPropertyOwner($ownerRpsId, $isVendor): ?array
    {
        if ($isVendor) {
            $getOwnerRequest = new GetVendorsRequest();
        } else {
            $getOwnerRequest = new GetLandlordsRequest();
        }

        $getOwnerRequest->query()->add('id', $ownerRpsId);
        $response = $this->connector->send($getOwnerRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($getOwnerRequest, $response);
            return null;
        }

        /** @var array<string,array<array<string,string>>> $propertyOwnerArray */
        $propertyOwnerArray = json_decode($response->body(), true);
        $propertyOwnerArray = $propertyOwnerArray['_embedded'];

        $propertyOwner = collect($propertyOwnerArray)->first();

        return $propertyOwner;
    }

    /**
     * @param  array<string,string|int>  $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getContacts(array $queryParameters = []): ?array
    {
        $contactRequest = new GetContactsRequest();

        foreach ($queryParameters as $key => $value) {
            $contactRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($contactRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($contactRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $contacts */
        $contacts = json_decode($response->body(), true) ?? null;

        return $contacts;
    }

    public function getContactsPaged(array $queryParameters = []): ?array
    {
        $contactRequest = new GetContactsRequest();

        foreach ($queryParameters as $key => $value) {
            $contactRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($contactRequest);
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
