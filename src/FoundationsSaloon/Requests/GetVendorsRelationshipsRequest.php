<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetVendorsRelationshipsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $id
    ) {}

    public function resolveEndpoint(): string
    {
        return "/vendors/{$this->id}/relationships";
    }

    protected function defaultQuery(): array
    {
        return [
            'per_page' => 100
        ];
    }
}
