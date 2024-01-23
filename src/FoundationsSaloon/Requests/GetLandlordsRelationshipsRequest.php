<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetLandlordsRelationshipsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $id
    ) {}

    public function resolveEndpoint(): string
    {
        return "/landlords/{$this->id}/relationships";
    }

    protected function defaultQuery(): array
    {
        return [
            'per_page' => 100
        ];
    }
}
