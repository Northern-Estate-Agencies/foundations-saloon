<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetWorksOrderRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/worksOrders/' . $this->id;
    }

    public function __construct(
        protected string $id,
    ) { }
}
