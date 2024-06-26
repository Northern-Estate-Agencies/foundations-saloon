<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;

class UpdateApplicantRequest extends Request implements HasBody
{

    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(
        protected string $id,
        protected string $eTag,
        protected array $payload = []
    ) { }

    /**
     * Default Request Headers
     *
     * @return array<string, mixed>
     */
    protected function defaultHeaders(): array
    {
        return [
            'If-Match' => $this->eTag,
        ];
    }

    protected function defaultBody(): array
    {
        return $this->payload;
    }

    public function resolveEndpoint(): string
    {
        return '/applicants/'.$this->id;
    }
}
