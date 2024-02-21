<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class GetTenancyChecksRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/tenancies/' . $this->id . '/checks';
    }

    protected function defaultQuery(): array
    {
        return [
            'per_page' => 100
        ];
    }

    public function __construct(
        protected string $id,
    ) { }
}
