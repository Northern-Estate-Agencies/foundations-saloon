<?php

namespace FoundationsSaloon\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class PostContactRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $forename,
        protected string $surname,
        protected string $email,
        protected string $mobilePhone,
        protected string $marketingConsent,
        protected bool $active,
        protected array $officeIds
    ) {}

    public function resolveEndpoint(): string
    {
        return '/contacts';
    }

    protected function defaultBody(): array
    {
        return [
            'forename' => $this->forename,
            'surname' => $this->surname,
            'surname' => $this->surname,
            'email' => $this->email,
            'mobilePhone' => $this->mobilePhone,
            'marketingConsent' => $this->marketingConsent,
            'active' => true,
            'officeIds' => $this->officeIds,
        ];
    }
}
