<?php

namespace FoundationsSaloon;

use FoundationsSaloon\Requests\PostClientCredentialsRequest;
use Predis\Client;
use Saloon\RateLimitPlugin\Limit;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Connector;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\PaginationPlugin\PagedPaginator;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\Traits\OAuth2\ClientCredentialsGrant;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Stores\PredisStore;

class FoundationsConnector extends Connector implements HasPagination
{
    use ClientCredentialsGrant;
    use HasRateLimits;
    // use HasLogging;

    public ?int $tries = 1;

    private bool $useUnsubFlowCredentials = false;

    public function resolveBaseUrl(): string
    {
        return 'https://platform.reapit.cloud/';
    }

    public function setReapitCustomer(string $customer): self
    {
        $this->headers()->add('reapit-customer', $customer);
        return $this;
    }

    public function useUnsubFlowCredentials(bool $useUnsub = true): void
    {
        $this->useUnsubFlowCredentials = $useUnsub;

        $clientId = config('services.reapit.unsub_client_id') ?? 'XYX';
        $clientSecret = config('services.reapit.unsub_client_secret') ?? 'XYX';

        $this->oauthConfig()->setClientId($clientId);
        $this->oauthConfig()->setClientSecret($clientSecret);

    }

    public function paginate(Request $request): PagedPaginator
    {
        return new class($this, $request) extends PagedPaginator {
            protected string $pageKeyName = 'PageNumber';
            protected string $limitKeyName = 'PageSize';
            protected string $totalKeyName = 'totalCount';
            protected string $nextPageKeyName = '_links.next.href';
            protected ?int $perPageLimit = 100;

            protected function isLastPage(Response $response): bool
            {
                return $response->json('totalPageCount') < $response->json('pageNumber');
            }

            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->json();
            }

            protected function getTotalPages(Response $response): int
            {
                return $response->json('totalPageCount');
            }

            protected function applyPagination(Request $request): Request
            {
                $request->query()->add('pageNumber', $this->page);

                if (isset($this->perPageLimit)) {
                    $request->query()->add('pageSize', $this->perPageLimit);
                }
                return $request;
            }
        };
    }

    protected function defaultOauthConfig(): OAuthConfig
    {
        $clientId = config('services.reapit.client_id') ?? 'XYX';
        $clientSecret = config('services.reapit.client_secret') ?? 'XYX';

        if($this->useUnsubFlowCredentials){           
            $clientId = config('services.reapit.unsub_client_id') ?? 'XYX';
            $clientSecret = config('services.reapit.unsub_client_secret') ?? 'XYX';
        }

        return OAuthConfig::make()
            ->setClientId($clientId)
            ->setClientSecret($clientSecret);
    }

    protected function resolveAccessTokenRequest(OAuthConfig $oauthConfig, array $scopes = [], string $scopeSeparator = ' '): Request
    {
        return new PostClientCredentialsRequest($oauthConfig, $scopes, $scopeSeparator);
    }

    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'api-version' => '2020-01-31'
        ];
    }


    protected function resolveRateLimitStore(): RateLimitStore
    {
        if(config('app.env') === 'local') {
            return new MemoryStore();
        }

        $predisClient = new Client([
            'scheme' => 'tcp',
            'host'   => config('database.redis.default.host'),
            'port'   => config('database.redis.default.port'),
            'password' => config('database.redis.default.password'),
        ]);

        return new PredisStore($predisClient);
    }

    protected function resolveLimits(): array
    {
        return [
            Limit::allow(20)->everySeconds(1)->sleep(),
        ];
    }

}
