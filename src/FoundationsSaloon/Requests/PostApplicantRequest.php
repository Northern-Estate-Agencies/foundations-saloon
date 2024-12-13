<?php

namespace FoundationsSaloon\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class PostApplicantRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /* 
        Making this method much more flexible than normal 
        so we're not restricted with the kind of applicants 
        we can create. 
    */

    public function __construct(
        protected string $marketingMode,
        protected string $relatedContactId,
        protected array $officeIds,
        protected array $negotiatorIds,
        protected string $departmentId,
        protected array $payload = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/applicants';
    }

    protected function defaultBody(): array
    {
        $baseDetailsArray = [
            'marketingMode' => $this->marketingMode,
            'officeIds' => $this->officeIds,
            'negotiatorIds' => $this->negotiatorIds,
            'departmentId' => $this->departmentId,
            'related' => [[
                'associatedId' => $this->relatedContactId,
                'associatedType' => 'contact',
            ]]
        ];

        $applicantDetails = collect($this->payload)
            ->except(['marketingMode', 'relatedContactId'])
            ->merge($baseDetailsArray)
            ->toArray();

        return $applicantDetails;
    }
}
