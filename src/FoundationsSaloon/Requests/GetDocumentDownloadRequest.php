<?php

namespace FoundationsSaloon\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class GetDocumentDownloadRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $id,
    ) { }

    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'api-version' => '2020-01-31',
            'Accept' => 'application/octet-stream'
        ];
    }
    

    public function resolveEndpoint(): string
    {
        return '/documents/'.$this->id.'/download';
    }    
}
