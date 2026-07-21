<?php

use App\Saloon\Foundations\Requests\UpdatePropertyRequest;
use FoundationsSaloon\FoundationsConnector;
use FoundationsSaloon\Requests\GetApplicantRequest;
use FoundationsSaloon\Requests\GetApplicantsRequest;
use FoundationsSaloon\Requests\GetAreaRequest;
use FoundationsSaloon\Requests\GetAreasRequest;
use FoundationsSaloon\Requests\GetCompaniesRequest;
use FoundationsSaloon\Requests\GetCompanyRequest;
use FoundationsSaloon\Requests\GetContactsRequest;
use FoundationsSaloon\Requests\GetJournalEntriesRequest;
use FoundationsSaloon\Requests\GetLandlordRequest;
use FoundationsSaloon\Requests\GetLandlordsRelationshipsRequest;
use FoundationsSaloon\Requests\GetLandlordsRequest;
use FoundationsSaloon\Requests\GetNegotiatorRequest;
use FoundationsSaloon\Requests\GetOfficesRequest;
use FoundationsSaloon\Requests\GetPropertiesRequest;
use FoundationsSaloon\Requests\GetPropertyRequest;
use FoundationsSaloon\Requests\GetTenanciesRequest;
use FoundationsSaloon\Requests\GetTenancyChecksRequest;
use FoundationsSaloon\Requests\GetTenancyRequest;
use FoundationsSaloon\Requests\GetTransactionsRequest;
use FoundationsSaloon\Requests\GetVendorRequest;
use FoundationsSaloon\Requests\GetVendorsRelationshipsRequest;
use FoundationsSaloon\Requests\GetVendorsRequest;
use FoundationsSaloon\Requests\PostJournalEntriesRequest;
use FoundationsSaloon\Requests\UpdateApplicantRequest;
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
     * @param  array<string,string>  $changes
     */
    public function updateApplicant(string $applicantId, array $changes): bool
    {
        $applicantRequest = new GetApplicantRequest($applicantId);

        $response = $this->connector->send($applicantRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($applicantRequest, $response);
            return false;
        }

        /** @var array<string,string> $applicantData */
        $applicantData = $response->array();
        $etag = $applicantData['_eTag'] ?? null;

        if (! isset($etag)) {
            Log::error('Could not find etag for contact', ['contactData' => $applicantData]);

            return false;
        }

        $updateRequest = new UpdateApplicantRequest($applicantId, $etag);

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

    public function getCompanies(array $queryParameters = []): ?array
    {
        $companiesRequest = new GetCompaniesRequest;

        foreach ($queryParameters as $key => $value) {
            $companiesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($companiesRequest);
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

    /**
     * @param  array<string,string|int,array<string>> $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getProperties(array $queryParameters = []): ?array
    {
        $propertiesRequest = new GetPropertiesRequest();

        foreach ($queryParameters as $key => $value) {
            $propertiesRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($propertiesRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($propertiesRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $properties */
        $properties = json_decode($response->body(), true) ?? null;

        return $properties;
    }

    /**
     * @param  array<string,string|int,array<string>> $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getPropertiesPaged(array $queryParameters = []): ?array
    {
        $propertiesRequest = new GetPropertiesRequest();

        foreach ($queryParameters as $key => $value) {
            $propertiesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($propertiesRequest);
    }

    /**
     * @param array<string,string|int> $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getProperty(string $propertyId, array $queryParameters = []): ?array
    {
        $propertyRequest = new GetPropertyRequest($propertyId);

        foreach ($queryParameters as $key => $value) {
            $propertyRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($propertyRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($propertyRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $property */
        $property = json_decode($response->body(), true) ?? null;

        return $property;
    }

    /**
     * @param array<string,string|int> $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getArea(string $areaId, array $queryParameters = []): ?array
    {
        $areaRequest = new GetAreaRequest($areaId);

        foreach ($queryParameters as $key => $value) {
            $areaRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($areaRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($areaRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $area */
        $area = json_decode($response->body(), true) ?? null;

        return $area;
    }

    /**
     * @param array<string,string|int> $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getNegotiator(string $negotiatorId, array $queryParameters = []): ?array
    {
        $negotiatorRequest = new GetNegotiatorRequest($negotiatorId);

        foreach ($queryParameters as $key => $value) {
            $negotiatorRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($negotiatorRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($negotiatorRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $property */
        $property = json_decode($response->body(), true) ?? null;

        return $property;
    }

    /**
     * @param  array<string,string|int>  $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getOffices(array $queryParameters = []): ?array
    {
        $officesRequest = new GetOfficesRequest();

        foreach ($queryParameters as $key => $value) {
            $officesRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($officesRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($officesRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $offices */
        $offices = json_decode($response->body(), true) ?? null;

        return $offices;
    }

    public function getOfficesPaged(array $queryParameters = []): ?array
    {
        $officesRequest = new GetOfficesRequest();

        foreach ($queryParameters as $key => $value) {
            $officesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($officesRequest);
    }

    /**
     * @param  array<string,string|int|array<string>>  $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getApplicants(array $queryParameters = []): ?array
    {
        $applicantsRequest = new GetApplicantsRequest();

        foreach ($queryParameters as $key => $value) {
            $applicantsRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($applicantsRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($applicantsRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $applicants */
        $applicants = json_decode($response->body(), true) ?? null;

        return $applicants;
    }

    /**
     * @param  array<string,string|int>  $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getApplicant(string $applicantId, array $queryParameters = []): ?array
    {
        $applicantRequest = new GetApplicantRequest($applicantId);

        foreach ($queryParameters as $key => $value) {
            $applicantRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($applicantRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($applicantRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $applicant */
        $applicant = json_decode($response->body(), true) ?? null;

        return $applicant;
    }

    public function getTransactions(array $queryParameters = []): ?array
    {
        $transactionsRequest = new GetTransactionsRequest;

        foreach ($queryParameters as $key => $value) {
            $transactionsRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($transactionsRequest);
    }

    /**
     * @param  array<string,string|int>  $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getTenancies(array $queryParameters = []): ?array
    {
        $tenanciesRequest = new GetTenanciesRequest();

        foreach ($queryParameters as $key => $value) {
            $tenanciesRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($tenanciesRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($tenanciesRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $tenancies */
        $tenancies = json_decode($response->body(), true) ?? null;

        return $tenancies;
    }

    public function getTenancy(string $tenancyRpsId, array $queryParameters = []): ?array
    {
        $tenancyRequest = new GetTenancyRequest($tenancyRpsId);

        foreach ($queryParameters as $key => $value) {
            $tenancyRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($tenancyRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($tenancyRequest, $response);
            return null;
        }

        return json_decode($response->body(), true);
    }

    public function getTenancyChecks(string $tenancyId, array $queryParameters = []): ?array
    {
        $tenancyChecksRequest = new GetTenancyChecksRequest($tenancyId);

        foreach ($queryParameters as $key => $value) {
            $tenancyChecksRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($tenancyChecksRequest);
    }

    /**
     * @param  array<string,string|int>  $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getVendors(array $queryParameters = []): ?array
    {
        $vendorsRequest = new GetVendorsRequest();

        foreach ($queryParameters as $key => $value) {
            $vendorsRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($vendorsRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($vendorsRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $vendors */
        $vendors = json_decode($response->body(), true) ?? null;

        return $vendors;
    }

    public function getVendor(string $ownerRpsId, array $queryParameters = []): ?array
    {
        $vendorRequest = new GetVendorRequest($ownerRpsId);

        $vendorRequest->query()->add('id', $ownerRpsId);

        foreach ($queryParameters as $key => $value) {
            $vendorRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($vendorRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($vendorRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $vendor */
        $vendor = json_decode($response->body(), true) ?? null;

        return $vendor;
    }

    /**
     * @param  array<string,string|int>  $queryParameters
     * @return ?array<array<string,string|array<string>>>
     */
    public function getLandlords(array $queryParameters = []): ?array
    {
        $landlordsRequest = new GetLandlordsRequest();

        foreach ($queryParameters as $key => $value) {
            $landlordsRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($landlordsRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($landlordsRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $landlords */
        $landlords = json_decode($response->body(), true) ?? null;

        return $landlords;
    }

    public function getLandlord(string $ownerRpsId, array $queryParameters = []): ?array
    {
        $landlordRequest = new GetLandlordRequest($ownerRpsId);

        $landlordRequest->query()->add('id', $ownerRpsId);

        foreach ($queryParameters as $key => $value) {
            $landlordRequest->query()->add($key, $value);
        }

        $response = $this->connector->send($landlordRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($landlordRequest, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $landlord */
        $landlord = json_decode($response->body(), true) ?? null;

        return $landlord;
    }

    public function doesContactConsentToMarketing(string $contactRpsId): bool
    {
        $reapitContactRecord = $this->getContact($contactRpsId);

        if (! isset($reapitContactRecord)) {
            Log::error(
                'Could not find contact, assuming marketing consent is denied',
                ['contactRpsId' => $contactRpsId]
            );

            return false;
        }

        $marketingConsent = $reapitContactRecord['marketingConsent'] ?? 'deny';

        return in_array($marketingConsent, ['given', 'grant']);
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
