<?php

namespace FoundationsSaloon\Traits;

use Illuminate\Support\Facades\Log;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;

trait HasLogging
{
    public function bootHasLogging(PendingRequest $pendingRequest): void
    {
        $pendingRequest->middleware()->onRequest(static function (PendingRequest $pendingRequest) {
            Log::info('External Request Sent', [
                'method' => $pendingRequest->getMethod()->value,
                'url' => $pendingRequest->getUrl(),
                'headers' => $pendingRequest->headers()->all(),
                'body' => $pendingRequest->body()?->all(),
                'request_id' => hash('sha256', json_encode([$pendingRequest->getUrl(), $pendingRequest->headers()->all(), $pendingRequest->body()?->all()]))
            ]);
        });

        $pendingRequest->middleware()->onResponse(static function (Response $response) {
            Log::info('External Request Received', [
                'method' => $response->getPendingRequest()->getMethod()->value,
                'url' => $response->getPendingRequest()->getUrl(),
                'headers' => $response->headers()->all(),
                'status' => $response->status(),
                'response' => $response->json(),
                'request_id' => hash('sha256', json_encode([$response->getPendingRequest()->getUrl(), $response->getPendingRequest()->headers()->all(), $response->getPendingRequest()->body()?->all()]))
            ]);
        });
    }
}