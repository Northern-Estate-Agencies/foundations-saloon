<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class GetPropertyImages extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/propertyImages';
    }

    protected function defaultQuery(): array
    {
        return [
            'per_page' => 100
        ];
    }
}
