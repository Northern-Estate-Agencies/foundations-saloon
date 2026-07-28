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

    public function storeContactJournalEntry(string $contactId, string $message): Response
    {
        $request = new PostJournalEntriesRequest(
            'MI',
            'contact',
            $contactId,
            $message
        );

        return $this->connector->send($request);
    }

    public function storeApplicantJournalEntry(string $applicantId, string $message): Response
    {
        $request = new PostJournalEntriesRequest(
            typeId: 'MI',
            associatedType: 'applicant',
            associatedId: $applicantId,
            description: $message,
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

    public function updateAppointment(string $appointmentId, array $changes): bool
    {
        $appointmentRequest = new GetAppointmentRequest($appointmentId);

        $response = $this->connector->send($appointmentRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($appointmentRequest, $response);
            return false;
        }

        /** @var array<string,string> $appointmentData */
        $appointmentData = $response->array();
        $etag = $appointmentData['_eTag'] ?? null;

        if (! isset($etag)) {
            Log::error('Could not find etag for contact', ['contactData' => $appointmentData]);
            return false;
        }

        $updateRequest = new UpdateAppointmentRequest($appointmentId, $etag);
        foreach ($changes as $key => $value) {
            $updateRequest->body()->add($key, $value);
        }

        $response = $this->connector->send($updateRequest);
        if (! $response->successful()) {
            $this->handleRequestFail($updateRequest, $response);
        }

        return $response->successful();
    }

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

    public function updateWorksOrder(string $worksOrderRpsId, array $changes): bool
    {
        $worksOrderRequest = new GetWorksOrderRequest($worksOrderRpsId);

        $response = $this->connector->send($worksOrderRequest);

        if (! $response->successful()) {
            Log::error('Error retrieving works order', ['response' => $response->body(), 'status' => $response->status()]);

            return false;
        }

        $worksOrderData = $response->collect()->toArray();
        $etag = $worksOrderData['_eTag'] ?? null;

        if (! isset($etag)) {
            Log::error('Could not find etag for contact', ['contactData' => $worksOrderData]);

            return false;
        }

        $updateRequest = new UpdateWorksOrderRequest($worksOrderRpsId, $etag);

        foreach ($changes as $key => $value) {
            $updateRequest->body()->add($key, $value);
        }

        $response = $this->connector->send($updateRequest);

        if (! $response->successful()) {
            $this->handleRequestFail($updateRequest, $response);
        }

        return $response->successful();
    }

    public function getJournalEntries(array $queryParameters = []): ?array
    {
        $journalEntriesRequest = new GetJournalEntriesRequest();

        foreach ($queryParameters as $key => $value) {
            $journalEntriesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($journalEntriesRequest);
    }

    public function getPropertyMarketingData(string $propertyId, array $queryParameters = []): ?array
    {
        $propertyMarketingDataRequest = new GetPropertyMarketingDataRequest($propertyId);

        foreach ($queryParameters as $key => $value) {
            $propertyMarketingDataRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($propertyMarketingDataRequest);
    }

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

        foreach ($queryParameters as $key => $value) {
            $contactRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($contactRequest);
    }

    public function getCompany(string $companyId, array $queryParameters = []): ?array
    {
        $companyRequest = new GetCompanyRequest($companyId);

        foreach ($queryParameters as $key => $value) {
            $companyRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($companyRequest);
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

        foreach ($queryParameters as $key => $value) {
            $contactRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($contactRequest);
    }

    public function getProperties(array $queryParameters = []): ?array
    {
        $propertiesRequest = new GetPropertiesRequest();

        foreach ($queryParameters as $key => $value) {
            $propertiesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($propertiesRequest);
    }

    public function getProperty(string $propertyId, array $queryParameters = []): ?array
    {
        $propertyRequest = new GetPropertyRequest($propertyId);

        foreach ($queryParameters as $key => $value) {
            $propertyRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($propertyRequest);
    }

    public function getPropertyCertificates(string $propertyId, array $queryParameters = []): ?array
    {
        $propertyCertificatesRequest = new GetPropertyCertificatesRequest($propertyId);

        foreach ($queryParameters as $key => $value) {
            $propertyCertificatesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($propertyCertificatesRequest);
    }

    public function getTenancyExtensions(string $tenancyId, array $queryParameters = []): ?array
    {
        $tenancyExtensionsRequest = new GetTenancyExtensionsRequest($tenancyId);

        foreach ($queryParameters as $key => $value) {
            $tenancyExtensionsRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($tenancyExtensionsRequest);
    }

    public function getCertificateTypes(): ?array
    {
        $certificateTypesRequest = new GetCertificateTypesRequest;

        return $this->getSingleResult($certificateTypesRequest);
    }

    public function getDocuments(array $queryParameters = []): ?array
    {
        $documentsRequest = new GetDocumentsRequest;

        foreach ($queryParameters as $key => $value) {
            $documentsRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($documentsRequest);
    }

    public function getDocument(string $documentRpsId, array $queryParameters = []): ?array
    {
        $documentRequest = new GetDocumentRequest($documentRpsId);

        foreach ($queryParameters as $key => $value) {
            $documentRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($documentRequest);
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

        foreach ($queryParameters as $key => $value) {
            $appointmentsRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($appointmentsRequest);
    }

    public function getAppointment(string $appointmentId, array $queryParameters = []): ?array
    {
        $appointmentRequest = new GetAppointmentRequest($appointmentId);

        foreach ($queryParameters as $key => $value) {
            $appointmentRequest->query()->add($key, $value);
        }
        
        return $this->getSingleResult($appointmentRequest);
    }

    public function getWorksOrders(array $queryParameters = []): ?array
    {
        $worksOrdersRequest = new GetWorksOrdersRequest;

        foreach ($queryParameters as $key => $value) {
            $worksOrdersRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($worksOrdersRequest);
    }

    public function getWorksOrder(string $worksOrderRpsId, array $queryParameters = []): ?array
    {
        $getWorksOrderRequest = new GetWorksOrderRequest($worksOrderRpsId);

        foreach ($queryParameters as $key => $value) {
            $getWorksOrderRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($getWorksOrderRequest);
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

        foreach ($queryParameters as $key => $value) {
            $offersRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($offersRequest);
    }

    public function getSalesProgress(string $offerId, array $queryParameters = []): ?array
    {
        $salesProgressRequest = new GetConveyancingRequest($offerId);

        foreach ($queryParameters as $key => $value) {
            $salesProgressRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($salesProgressRequest);
    }

    public function getArea(string $areaId, array $queryParameters = []): ?array
    {
        $areaRequest = new GetAreaRequest($areaId);

        foreach ($queryParameters as $key => $value) {
            $areaRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($areaRequest);
    }

    public function getNegotiator(string $negotiatorId, array $queryParameters = []): ?array
    {
        $negotiatorRequest = new GetNegotiatorRequest($negotiatorId);

        foreach ($queryParameters as $key => $value) {
            $negotiatorRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($negotiatorRequest);
    }

    public function getNegotiators(array $queryParameters = [])
    {
        $negotiatorsRequest = new GetNegotiatorsRequest;

        foreach ($queryParameters as $key => $value) {
            $negotiatorsRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($negotiatorsRequest);
    }

    public function getOffice(string $officeRpsId, array $queryParameters = []): ?array
    {
        $getOfficeRequest = new GetOfficeRequest($officeRpsId);

        foreach ($queryParameters as $key => $value) {
            $getOfficeRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($getOfficeRequest);
    }

    public function getOffices(array $queryParameters = []): ?array
    {
        $officesRequest = new GetOfficesRequest();

        foreach ($queryParameters as $key => $value) {
            $officesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($officesRequest);
    }

    public function getApplicants(array $queryParameters = []): ?array
    {
        $applicantsRequest = new GetApplicantsRequest();

        foreach ($queryParameters as $key => $value) {
            $applicantsRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($applicantsRequest);
    }

    public function getApplicant(string $applicantId, array $queryParameters = []): ?array
    {
        $applicantRequest = new GetApplicantRequest($applicantId);

        foreach ($queryParameters as $key => $value) {
            $applicantRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($applicantRequest);
    }

    public function getTransactions(array $queryParameters = []): ?array
    {
        $transactionsRequest = new GetTransactionsRequest;

        foreach ($queryParameters as $key => $value) {
            $transactionsRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($transactionsRequest);
    }

    public function getTenancies(array $queryParameters = []): ?array
    {
        $tenanciesRequest = new GetTenanciesRequest();

        foreach ($queryParameters as $key => $value) {
            $tenanciesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($tenanciesRequest);
    }

    public function getTenancy(string $tenancyRpsId, array $queryParameters = []): ?array
    {
        $tenancyRequest = new GetTenancyRequest($tenancyRpsId);

        foreach ($queryParameters as $key => $value) {
            $tenancyRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($tenancyRequest);
    }

    public function getTenancyChecks(string $tenancyId, array $queryParameters = []): ?array
    {
        $tenancyChecksRequest = new GetTenancyChecksRequest($tenancyId);

        foreach ($queryParameters as $key => $value) {
            $tenancyChecksRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($tenancyChecksRequest);
    }

    public function getVendors(array $queryParameters = []): ?array
    {
        $vendorsRequest = new GetVendorsRequest();

        foreach ($queryParameters as $key => $value) {
            $vendorsRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($vendorsRequest);
    }

    public function getVendor(string $ownerRpsId, array $queryParameters = []): ?array
    {
        $vendorRequest = new GetVendorRequest($ownerRpsId);

        foreach ($queryParameters as $key => $value) {
            $vendorRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($vendorRequest);
    }

    public function getLandlords(array $queryParameters = []): ?array
    {
        $landlordsRequest = new GetLandlordsRequest();

        foreach ($queryParameters as $key => $value) {
            $landlordsRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($landlordsRequest);
    }

    public function getLandlord(string $ownerRpsId, array $queryParameters = []): ?array
    {
        $landlordRequest = new GetLandlordRequest($ownerRpsId);

        foreach ($queryParameters as $key => $value) {
            $landlordRequest->query()->add($key, $value);
        }

        return $this->getSingleResult($landlordRequest);
    }

    public function getPropertyImages(array $queryParameters = []): ?array
    {
        $propertyImagesRequest = new GetPropertyImages();

        foreach ($queryParameters as $key => $value) {
            $propertyImagesRequest->query()->add($key, $value);
        }

        return $this->getPaginatedResults($propertyImagesRequest);
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

    protected function getSingleResult(Request $request): ?array
    {
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
    protected function getPaginatedResults(Request $request): ?array
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
