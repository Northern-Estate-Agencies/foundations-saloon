<?php

namespace FoundationsSaloon\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class PostJournalEntriesRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $typeId,
        protected string $associatedType,
        protected string $associatedId,
        protected string $description
    ) {}

    public function resolveEndpoint(): string
    {
        return '/journalEntries';
    }

    protected function defaultBody(): array
    {
        return [
            'typeId' => $this->typeId,
            'associatedType' => $this->associatedType,
            'associatedId' => $this->associatedId,
            'description' => $this->description
        ];
    }
}
