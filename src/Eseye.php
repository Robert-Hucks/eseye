<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2022 Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Eseye;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use Seat\Eseye\Access\AccessInterface;
use Seat\Eseye\Access\CheckAccess;
use Seat\Eseye\Cache\CacheInterface;
use Seat\Eseye\Containers\EsiAuthentication;
use Seat\Eseye\Containers\EsiResponse;
use Seat\Eseye\Exceptions\EsiScopeAccessDeniedException;
use Seat\Eseye\Exceptions\InvalidAuthenticationException;
use Seat\Eseye\Exceptions\InvalidContainerDataException;
use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eseye\Exceptions\UriDataMissingException;
use Seat\Eseye\Fetchers\FetcherInterface;
use Seat\Eseye\Log\LogInterface;

/**
 * Class Eseye.
 *
 * @package Seat\Eseye
 */
class Eseye
{
    const VERSION = '2.4.1';

    protected EsiAuthentication|null $authentication = null;

    protected FetcherInterface|null $fetcher = null;

    protected CacheInterface|null $cache;

    protected LogInterface|null $logger;

    protected AccessInterface|null $access_checker = null;

    /**
     * @var array<string, mixed> $query_string
     */
    protected array $query_string = [];

    /**
     * @var array<mixed> $request_body
     */
    protected array $request_body = [];

    protected string $version = '/latest';

    /**
     * HTTP verbs that could have their responses cached.
     *
     * @var array<string> $cachable_verb
     */
    protected array $cachable_verb = ['get'];

    /**
     * Eseye constructor.
     */
    public function __construct(
        EsiAuthentication|null $authentication = null)
    {

        if (! is_null($authentication))
            $this->authentication = $authentication;

        // Setup the logger
        $this->logger = $this->getLogger();
    }

    public function getLogger(): LogInterface
    {

        return $this->getConfiguration()->getLogger();
    }

    public function getConfiguration(): Configuration
    {

        return Configuration::getInstance();
    }

    /**
     * @throws InvalidAuthenticationException
     */
    public function getAuthentication(): EsiAuthentication
    {

        if (is_null($this->authentication))
            throw new InvalidAuthenticationException('Authentication data not set.');

        return $this->authentication;
    }

    /**
     * @throws InvalidContainerDataException
     */
    public function setAuthentication(EsiAuthentication $authentication): self
    {

        if (! $authentication->valid())
            throw new InvalidContainerDataException('Authentication data invalid/empty');

        $this->authentication = $authentication;

        return $this;
    }

    public function isAuthenticated(): bool
    {
        return ! is_null($this->authentication);
    }

    public function setRefreshToken(string $refreshToken): self
    {
        $this->authentication = $this->authentication->setRefreshToken($refreshToken);

        return $this;
    }

    public function setFetcher(FetcherInterface $fetcher): void
    {

        $this->fetcher = $fetcher;
    }

    /**
     * @param  array<mixed>  $body
     */
    public function setBody(array $body): self
    {

        $this->request_body = $body;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $uri_data
     *
     * @throws EsiScopeAccessDeniedException
     * @throws RequestFailedException
     * @throws InvalidAuthenticationException
     * @throws InvalidContainerDataException
     * @throws UriDataMissingException
     */
    public function invoke(string $method, string $uri, array $uri_data = []): EsiResponse
    {
        // Check the Access Requirement
        if (! $this->getAccessChecker()->can(
            $method, $uri, $this->getFetcher()->getAuthenticationScopes())
        ) {

            // Build the uri so that there is context around what is denied.
            $uri = $this->buildDataUri($uri, $uri_data);

            // Log the deny.
            $this->logger->warning('Access denied to ' . $uri . ' due to ' .
                'missing scopes.');

            throw new EsiScopeAccessDeniedException('Access denied to ' . $uri);
        }

        // Build the URI from the parts we have.
        $uri = $this->buildDataUri($uri, $uri_data);

        // Check if there is a cached response we can return
        if (in_array(strtolower($method), $this->cachable_verb) &&
            $cached = $this->getCache()->get($uri->getPath(), $uri->getQuery())
        ) {

            // In case the cached entry is still valid, mark content as being loaded from cache.
            if (! $cached->expired())
                $cached->setIsCachedLoad();

            // Handling ETag marked response specifically (ignoring the expired time)
            // Sending a request with the stored ETag in header - if we have a 304 response, data has not been altered.
            if ($cached->hasHeader('ETag') && $cached->expired()) {

                $result = $this->rawFetch($method, $uri, $this->getBody(), ['If-None-Match' => $cached->getHeader('ETag')]);

                if ($result->getErrorCode() == 304) {

                    // update expires header with newly provided value
                    $cached->setExpires($result->expires());

                    // store updated response in cache to renew internal cache duration
                    $this->getCache()->set($uri->getPath(), $uri->getQuery(), $cached);

                    $cached->setIsCachedLoad();
                }
            }

            // In case the result is effectively retrieved from cache,
            // return the cached element.
            if ($cached->isCachedLoad()) {

                // Perform some debug logging
                $logging_msg = 'Loaded cached response for ' . $method . ' -> ' . $uri;

                if ($cached->hasHeader('ETag'))
                    $logging_msg = sprintf('%s [%s]', $logging_msg, $cached->getHeader('ETag'));

                $this->getLogger()->debug($logging_msg);

                $this->cleanupRequestData();

                return $cached;
            }
        }

        // Call ESI itself and get the EsiResponse in case it has not already been handled with cache control
        if (! isset($result))
            $result = $this->rawFetch($method, $uri, $this->getBody());

        // Cache the response if it was a get and is not already expired
        if (in_array(strtolower($method), $this->cachable_verb) && ! $result->expired())
            $this->getCache()->set($uri->getPath(), $uri->getQuery(), $result);

        // In preparation for the next request, perform some
        // self cleanups of this objects request data such as
        // query string parameters and post bodies.
        $this->cleanupRequestData();

        return $result;
    }

    public function getAccessChecker(): AccessInterface
    {

        if (! $this->access_checker)
            $this->access_checker = new CheckAccess;

        return $this->access_checker;
    }

    public function setAccessChecker(AccessInterface $checker): self
    {
        $this->access_checker = $checker;

        return $this;
    }

    /**
     * @throws InvalidContainerDataException
     */
    private function getFetcher(): FetcherInterface
    {

        if (! $this->fetcher) {

            $fetcher_class = $this->getConfiguration()->fetcher;
            $this->fetcher = new $fetcher_class(...[$this->authentication]);

        }

        return $this->fetcher;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws UriDataMissingException
     * @throws InvalidContainerDataException
     */
    public function buildDataUri(string $uri, array $data): UriInterface
    {

        // Create a query string for the URI. We automatically
        // include the datasource value from the configuration.
        $query_params = array_merge([
            'datasource' => $this->getConfiguration()->datasource,
        ], $this->getQueryString());

        return Uri::fromParts([
            'scheme' => $this->getConfiguration()->esi_scheme,
            'host'   => $this->getConfiguration()->esi_host,
            'port'   => $this->getConfiguration()->esi_port,
            'path'   => rtrim($this->getVersion(), '/') .
                $this->mapDataToUri($uri, $data),
            'query'  => http_build_query($query_params),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryString(): array
    {

        return $this->query_string;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function setQueryString(array $query): self
    {

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                $query[$key] = implode(',', $value);
            }
        }

        $this->query_string = array_merge($this->query_string, $query);

        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Set the version of the API endpoints base URI.
     */
    public function setVersion(string $version): self
    {

        if (!str_starts_with($version, '/'))
            $version = '/' . $version;

        $this->version = $version;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws UriDataMissingException
     */
    private function mapDataToUri(string $uri, array $data): string
    {
        // Extract fields in curly braces. If there are fields,
        // replace the data with those in the URI
        if (preg_match_all('/{+(.*?)}/', $uri, $matches)) {

            if (empty($data))
                throw new UriDataMissingException(
                    'The data array for the uri ' . $uri . ' is empty. Please provide data to use.');

            foreach ($matches[1] as $match) {

                if (! array_key_exists($match, $data))
                    throw new UriDataMissingException(
                        'Data for ' . $match . ' is missing. Please provide this by setting a value ' .
                        'for ' . $match . '.');

                $uri = str_replace('{' . $match . '}', $data[$match], $uri);
            }
        }

        return $uri;
    }

    /**
     * @throws InvalidContainerDataException
     */
    private function getCache(): CacheInterface
    {
        return $this->getConfiguration()->getCache();
    }

    /**
     * @param  array<mixed>  $body
     * @param  array<string, mixed>  $headers
     *
     * @throws InvalidAuthenticationException
     * @throws RequestFailedException
     * @throws InvalidContainerDataException
     */
    public function rawFetch(string $method, string $uri, array $body, array $headers = []): EsiResponse
    {
        return $this->getFetcher()->call($method, $uri, $body, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->request_body;
    }

    public function cleanupRequestData(): self
    {
        $this->unsetBody();
        $this->unsetQueryString();

        return $this;
    }

    public function unsetBody(): self
    {
        $this->request_body = [];

        return $this;
    }

    public function unsetQueryString(): self
    {
        $this->query_string = [];

        return $this;
    }

    /**
     * A helper method to specify the page to retrieve.
     */
    public function page(int $page): self
    {
        $this->setQueryString(['page' => $page]);

        return $this;
    }
}
