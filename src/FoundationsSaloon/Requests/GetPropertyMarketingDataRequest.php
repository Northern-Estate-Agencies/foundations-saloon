<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetPropertyMarketingDataRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/properties/' . $this->id . '/marketing';
    }

    public function __construct(
        protected string $id,
    ) { }
}
