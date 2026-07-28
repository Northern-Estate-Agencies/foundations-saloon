<?php

namespace FoundationsSaloon\Services;

use Exception;
use FoundationsSaloon\FoundationsConnector;
use FoundationsSaloon\Requests\GetApplicantRequest;
use FoundationsSaloon\Requests\GetApplicantsRequest;
use FoundationsSaloon\Requests\GetAppointmentRequest;
use FoundationsSaloon\Requests\GetAppointmentsRequest;
use FoundationsSaloon\Requests\GetAppointmentTypesRequest;
use FoundationsSaloon\Requests\GetAreaRequest;
use FoundationsSaloon\Requests\GetAreasRequest;
use FoundationsSaloon\Requests\GetBuyingPositionsRequest;
use FoundationsSaloon\Requests\GetCertificateTypesRequest;
use FoundationsSaloon\Requests\GetCompaniesRequest;
use FoundationsSaloon\Requests\GetCompanyRequest;
use FoundationsSaloon\Requests\GetContactRequest;
use FoundationsSaloon\Requests\GetContactsRequest;
use FoundationsSaloon\Requests\GetConveyancingRequest;
use FoundationsSaloon\Requests\GetDocumentDownloadRequest;
use FoundationsSaloon\Requests\GetDocumentRequest;
use FoundationsSaloon\Requests\GetDocumentsRequest;
use FoundationsSaloon\Requests\GetJournalEntriesRequest;
use FoundationsSaloon\Requests\GetLandlordRequest;
use FoundationsSaloon\Requests\GetLandlordsRelationshipsRequest;
use FoundationsSaloon\Requests\GetLandlordsRequest;
use FoundationsSaloon\Requests\GetNegotiatorRequest;
use FoundationsSaloon\Requests\GetNegotiatorsRequest;
use FoundationsSaloon\Requests\GetOffersRequest;
use FoundationsSaloon\Requests\GetOfficeRequest;
use FoundationsSaloon\Requests\GetOfficesRequest;
use FoundationsSaloon\Requests\GetPropertiesRequest;
use FoundationsSaloon\Requests\GetPropertyCertificatesRequest;
use FoundationsSaloon\Requests\GetPropertyImages;
use FoundationsSaloon\Requests\GetPropertyMarketingDataRequest;
use FoundationsSaloon\Requests\GetPropertyRequest;
use FoundationsSaloon\Requests\GetTenanciesRequest;
use FoundationsSaloon\Requests\GetTenancyChecksRequest;
use FoundationsSaloon\Requests\GetTenancyExtensionsRequest;
use FoundationsSaloon\Requests\GetTenancyRequest;
use FoundationsSaloon\Requests\GetTransactionsRequest;
use FoundationsSaloon\Requests\GetVendorRequest;
use FoundationsSaloon\Requests\GetVendorsRelationshipsRequest;
use FoundationsSaloon\Requests\GetVendorsRequest;
use FoundationsSaloon\Requests\GetWorksOrderRequest;
use FoundationsSaloon\Requests\GetWorksOrdersRequest;
use FoundationsSaloon\Requests\GetWorksOrderTypesRequest;
use FoundationsSaloon\Requests\PostContactRequest;
use FoundationsSaloon\Requests\PostJournalEntriesRequest;
use FoundationsSaloon\Requests\UpdateApplicantRequest;
use FoundationsSaloon\Requests\UpdateAppointmentRequest;
use FoundationsSaloon\Requests\UpdateCompanyRequest;
use FoundationsSaloon\Requests\UpdateContactRequest;
use FoundationsSaloon\Requests\UpdatePropertyRequest;
use FoundationsSaloon\Requests\UpdateWorksOrderRequest;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
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

    public function storeJournalEntry(string $typeId, string $assocatiedType, string $contactId, string $message): Response
    {
        $request = new PostJournalEntriesRequest(
            typeId: $typeId,
            associatedType: $assocatiedType,
            associatedId: $contactId,
            description: $message
        );

        return $this->connector->send($request);
    }

    public function updateContact(string $contactRpsId, array $changes): bool
    {
        $contactRequest = new GetContactRequest($contactRpsId);

        return $this->updateEntityWithEtag(
            $contactRequest,
            $contactRpsId,
            $changes,
            static fn(string $id, string $eTag, array $payload): Request => new UpdateContactRequest($id, $eTag, $payload)
        );
    }

    public function updateAppointment(string $appointmentId, array $changes): bool
    {
        $appointmentRequest = new GetAppointmentRequest($appointmentId);

        return $this->updateEntityWithEtag(
            $appointmentRequest,
            $appointmentId,
            $changes,
            static fn(string $id, string $eTag, array $payload): Request => new UpdateAppointmentRequest($id, $eTag, $payload)
        );
    }

    public function updateCompany(string $companyRpsId, array $changes): bool
    {
        $companyRequest = new GetCompanyRequest($companyRpsId);

        return $this->updateEntityWithEtag(
            $companyRequest,
            $companyRpsId,
            $changes,
            static fn(string $id, string $eTag, array $payload): Request => new UpdateCompanyRequest($id, $eTag, $payload)
        );
    }

    public function updateProperty(string $propertyRpsId, array $changes): bool
    {
        $propertyRequest = new GetPropertyRequest($propertyRpsId);

        return $this->updateEntityWithEtag(
            $propertyRequest,
            $propertyRpsId,
            $changes,
            static fn(string $id, string $eTag, array $payload): Request => new UpdatePropertyRequest($id, $eTag, $payload)
        );
    }

    public function updateApplicant(string $applicantId, array $changes): bool
    {
        $applicantRequest = new GetApplicantRequest($applicantId);

        return $this->updateEntityWithEtag(
            $applicantRequest,
            $applicantId,
            $changes,
            static fn(string $id, string $eTag, array $payload): Request => new UpdateApplicantRequest($id, $eTag, $payload)
        );
    }

    public function updateWorksOrder(string $worksOrderRpsId, array $changes): bool
    {
        $worksOrderRequest = new GetWorksOrderRequest($worksOrderRpsId);

        return $this->updateEntityWithEtag(
            $worksOrderRequest,
            $worksOrderRpsId,
            $changes,
            static fn(string $id, string $eTag, array $payload): Request => new UpdateWorksOrderRequest($id, $eTag, $payload)
        );
    }

    public function getJournalEntries(array $queryParameters = []): ?array
    {
        $journalEntriesRequest = new GetJournalEntriesRequest();

        return $this->getPaginatedResults($journalEntriesRequest, $queryParameters);
    }

    public function getPropertyMarketingData(string $propertyId, array $queryParameters = []): ?array
    {
        $propertyMarketingDataRequest = new GetPropertyMarketingDataRequest($propertyId);

        return $this->getSingleResult($propertyMarketingDataRequest, $queryParameters);
    }

    public function getAreas(array $queryParameters = []): ?array
    {
        $areasRequest = new GetAreasRequest();

        return $this->getPaginatedResults($areasRequest, $queryParameters);
    }

    public function getCompanies(array $queryParameters = []): ?array
    {
        $companiesRequest = new GetCompaniesRequest;

        return $this->getPaginatedResults($companiesRequest, $queryParameters);
    }

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
    public function getContact(string $contactRpsId, array $queryParameters = []): ?array
    {
        $contactRequest = new GetContactRequest($contactRpsId);

        return $this->getSingleResult($contactRequest, $queryParameters);
    }

    public function getCompany(string $companyId, array $queryParameters = []): ?array
    {
        $companyRequest = new GetCompanyRequest($companyId);

        return $this->getSingleResult($companyRequest, $queryParameters);
    }

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

    public function getContacts(array $queryParameters = []): ?array
    {
        $contactRequest = new GetContactsRequest();

        return $this->getPaginatedResults($contactRequest, $queryParameters);
    }

    public function getProperties(array $queryParameters = []): ?array
    {
        $propertiesRequest = new GetPropertiesRequest();

        return $this->getPaginatedResults($propertiesRequest, $queryParameters);
    }

    public function getProperty(string $propertyId, array $queryParameters = []): ?array
    {
        $propertyRequest = new GetPropertyRequest($propertyId);

        return $this->getSingleResult($propertyRequest, $queryParameters);
    }

    public function getPropertyCertificates(string $propertyId, array $queryParameters = []): ?array
    {
        $propertyCertificatesRequest = new GetPropertyCertificatesRequest($propertyId);

        return $this->getPaginatedResults($propertyCertificatesRequest, $queryParameters);
    }

    public function getTenancyExtensions(string $tenancyId, array $queryParameters = []): ?array
    {
        $tenancyExtensionsRequest = new GetTenancyExtensionsRequest($tenancyId);

        return $this->getPaginatedResults($tenancyExtensionsRequest, $queryParameters);
    }

    public function getCertificateTypes(): ?array
    {
        $certificateTypesRequest = new GetCertificateTypesRequest;

        return $this->getSingleResult($certificateTypesRequest);
    }

    public function getDocuments(array $queryParameters = []): ?array
    {
        $documentsRequest = new GetDocumentsRequest;

        return $this->getPaginatedResults($documentsRequest, $queryParameters);
    }

    public function getDocument(string $documentRpsId, array $queryParameters = []): ?array
    {
        $documentRequest = new GetDocumentRequest($documentRpsId);

        return $this->getSingleResult($documentRequest, $queryParameters);
    }

    public function getDocumentDownload(string $documentId): ?string
    {
        $documentDownloadRequest = new GetDocumentDownloadRequest($documentId);

        try {
            $response = $this->connector->send($documentDownloadRequest);
        } catch (NotFoundException $e) {
            return null;
        }

        if (! $response->successful()) {
            $this->handleRequestFail($documentDownloadRequest, $response);
            return null;
        }

        return $response->body();
    }

    public function getAppointments(array $queryParameters = []): ?array
    {
        $appointmentsRequest = new GetAppointmentsRequest;

        return $this->getPaginatedResults($appointmentsRequest, $queryParameters);
    }

    public function getAppointment(string $appointmentId, array $queryParameters = []): ?array
    {
        $appointmentRequest = new GetAppointmentRequest($appointmentId);

        return $this->getSingleResult($appointmentRequest, $queryParameters);
    }

    public function getWorksOrders(array $queryParameters = []): ?array
    {
        $worksOrdersRequest = new GetWorksOrdersRequest;

        return $this->getPaginatedResults($worksOrdersRequest, $queryParameters);
    }

    public function getWorksOrder(string $worksOrderRpsId, array $queryParameters = []): ?array
    {
        $getWorksOrderRequest = new GetWorksOrderRequest($worksOrderRpsId);

        return $this->getSingleResult($getWorksOrderRequest, $queryParameters);
    }

    public function getWorksOrderTypes(): ?array
    {
        $worksOrderTypesRequest = new GetWorksOrderTypesRequest;

        return $this->getSingleResult($worksOrderTypesRequest);
    }

    public function getBuyingPositions(): ?array
    {
        $buyingPositionsRequest = new GetBuyingPositionsRequest();

        return $this->getSingleResult($buyingPositionsRequest);
    }

    public function getAppointmentTypes(): ?array
    {
        $appointmentTypesRequest = new GetAppointmentTypesRequest;

        return $this->getSingleResult($appointmentTypesRequest);
    }

    public function getOffers(array $queryParameters = []): ?array
    {
        $offersRequest = new GetOffersRequest;

        return $this->getPaginatedResults($offersRequest, $queryParameters);
    }

    public function getSalesProgress(string $offerId, array $queryParameters = []): ?array
    {
        $salesProgressRequest = new GetConveyancingRequest($offerId);

        return $this->getSingleResult($salesProgressRequest, $queryParameters);
    }

    public function getArea(string $areaId, array $queryParameters = []): ?array
    {
        $areaRequest = new GetAreaRequest($areaId);

        return $this->getSingleResult($areaRequest, $queryParameters);
    }

    public function getNegotiator(string $negotiatorId, array $queryParameters = []): ?array
    {
        $negotiatorRequest = new GetNegotiatorRequest($negotiatorId);

        return $this->getSingleResult($negotiatorRequest, $queryParameters);
    }

    public function getNegotiators(array $queryParameters = [])
    {
        $negotiatorsRequest = new GetNegotiatorsRequest;

        return $this->getPaginatedResults($negotiatorsRequest, $queryParameters);
    }

    public function getOffice(string $officeRpsId, array $queryParameters = []): ?array
    {
        $getOfficeRequest = new GetOfficeRequest($officeRpsId);

        return $this->getSingleResult($getOfficeRequest, $queryParameters);
    }

    public function getOffices(array $queryParameters = []): ?array
    {
        $officesRequest = new GetOfficesRequest();

        return $this->getPaginatedResults($officesRequest, $queryParameters);
    }

    public function getApplicants(array $queryParameters = []): ?array
    {
        $applicantsRequest = new GetApplicantsRequest();

        return $this->getPaginatedResults($applicantsRequest, $queryParameters);
    }

    public function getApplicant(string $applicantId, array $queryParameters = []): ?array
    {
        $applicantRequest = new GetApplicantRequest($applicantId);

        return $this->getSingleResult($applicantRequest, $queryParameters);
    }

    public function getTransactions(array $queryParameters = []): ?array
    {
        $transactionsRequest = new GetTransactionsRequest;

        return $this->getPaginatedResults($transactionsRequest, $queryParameters);
    }

    public function getTenancies(array $queryParameters = []): ?array
    {
        $tenanciesRequest = new GetTenanciesRequest();

        return $this->getPaginatedResults($tenanciesRequest, $queryParameters);
    }

    public function getTenancy(string $tenancyRpsId, array $queryParameters = []): ?array
    {
        $tenancyRequest = new GetTenancyRequest($tenancyRpsId);

        return $this->getSingleResult($tenancyRequest, $queryParameters);
    }

    public function getTenancyChecks(string $tenancyId, array $queryParameters = []): ?array
    {
        $tenancyChecksRequest = new GetTenancyChecksRequest($tenancyId);

        return $this->getPaginatedResults($tenancyChecksRequest, $queryParameters);
    }

    public function getVendors(array $queryParameters = []): ?array
    {
        $vendorsRequest = new GetVendorsRequest();

        return $this->getPaginatedResults($vendorsRequest, $queryParameters);
    }

    public function getVendor(string $ownerRpsId, array $queryParameters = []): ?array
    {
        $vendorRequest = new GetVendorRequest($ownerRpsId);

        return $this->getSingleResult($vendorRequest, $queryParameters);
    }

    public function getLandlords(array $queryParameters = []): ?array
    {
        $landlordsRequest = new GetLandlordsRequest();

        return $this->getPaginatedResults($landlordsRequest, $queryParameters);
    }

    public function getLandlord(string $ownerRpsId, array $queryParameters = []): ?array
    {
        $landlordRequest = new GetLandlordRequest($ownerRpsId);

        return $this->getSingleResult($landlordRequest, $queryParameters);
    }

    public function getPropertyImages(array $queryParameters = []): ?array
    {
        $propertyImagesRequest = new GetPropertyImages();

        return $this->getPaginatedResults($propertyImagesRequest, $queryParameters);
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

    public function createContact(
        string $title,
        string $forename,
        string $surname,
        string $email,
        string $mobilePhone,
        string $marketingConsent,
        bool $active,
        array $officeIds,
        array $negotiatorIds
    ): ?array {

        $createContactRequest = new PostContactRequest(
            title: $title,
            forename: $forename,
            surname: $surname,
            email: $email,
            mobilePhone: $mobilePhone,
            marketingConsent: $marketingConsent,
            active: $active,
            officeIds: $officeIds,
            negotiatorIds: $negotiatorIds
        );

        $response = $this->connector->send($createContactRequest);

        if (! $response->successful()) {

            $this->handleRequestFail($createContactRequest, $response);

            return null;
        }

        $contacts = $this->getContacts(['email' => $email]);

        return collect($contacts)->first();
    }

    protected function logConnection(Request $request): void
    {
        // stub
    }

    /**
     * @param callable(string, string, array): Request $buildUpdateRequest
     */
    protected function updateEntityWithEtag(Request $request, string $rpsId, array $changes, callable $buildUpdateRequest): bool
    {

        $response = $this->connector->send($request);

        if (! $response->successful()) {
            $this->handleRequestFail($request, $response);
            return false;
        }

        $propertyData = $response->array();
        $etag = $propertyData['_eTag'] ?? null;

        if (! isset($etag)) {
            Log::error('Could not find etag for property', ['propertyData' => $propertyData]);
            return false;
        }

        $updateRequest = $buildUpdateRequest($rpsId, $etag, $changes);

        $response = $this->connector->send($updateRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($updateRequest, $response);
        }

        return $response->successful();
    }

    protected function getSingleResult(Request $request, array $queryParameters = []): ?array
    {
        $this->applyQueryParameters($request, $queryParameters);

        $response = $this->connector->send($request);

        if (! $response->successful()) {
            $this->handleRequestFail($request, $response);
            return null;
        }

        /** @var ?array<array<string,string|array<string>>> $responseDecoded */
        $responseDecoded = json_decode($response->body(), true) ?? null;

        return $responseDecoded;
    }

    /** @return ?array<array<string,string|array<string>>> $results */
    protected function getPaginatedResults(Request $request, array $queryParameters = []): ?array
    {
        $this->applyQueryParameters($request, $queryParameters);

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

    private function applyQueryParameters(Request $request, array $queryParameters): void
    {
        foreach ($queryParameters as $key => $value) {
            $request->query()->add($key, $value);
        }
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
