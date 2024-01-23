<?php

namespace FoundationsSaloon\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Contracts\PendingRequest;
use Saloon\Enums\Method;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

class PostClientCredentialsRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected OAuthConfig $oauthConfig,
        protected array $scopes = [],
        protected string $scopeSeparator = ' '
    ) {}

    public function resolveEndpoint(): string
    {
        return 'https://connect.reapit.cloud/token';
    }

    protected function defaultBody(): array
    {
        return [
            'grant_type' => 'client_credentials',
            'client_id' => $this->oauthConfig->getClientId(),
        ];
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($this->oauthConfig->getClientId() . ':' . $this->oauthConfig->getClientSecret()),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
    }

}
