<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetAppointmentsRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/appointments';
    }

    protected function defaultQuery(): array
    {
        return [
            'per_page' => 100
        ];
    }
}
