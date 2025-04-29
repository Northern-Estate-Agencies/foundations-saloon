<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetAreaRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/areas/' . $this->id;
    }

    public function __construct(
        protected string $id,
    ) { }
}
