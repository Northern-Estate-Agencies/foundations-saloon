<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetLandlordRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/landlords/' . $this->id;
    }

    public function __construct(
        protected string $id,
    ) {}
}
