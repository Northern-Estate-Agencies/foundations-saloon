<?php

use FoundationsSaloon\FoundationsConnector;
use Saloon\Http\Auth\AccessTokenAuthenticator;

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
}
