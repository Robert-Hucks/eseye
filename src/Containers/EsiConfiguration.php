<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to present Leon Jacobs
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

namespace Seat\Eseye\Containers;

use Seat\Eseye\Cache\CacheInterface;
use Seat\Eseye\Cache\FileCache;
use Seat\Eseye\Fetchers\FetcherInterface;
use Seat\Eseye\Fetchers\GuzzleFetcher;
use Seat\Eseye\Log\LogInterface;
use Seat\Eseye\Log\RotatingFileLogger;
use Seat\Eseye\Traits\ConstructsContainers;
use Seat\Eseye\Traits\ValidatesContainers;

/**
 * Class EsiConfiguration.
 *
 * @package Seat\Eseye\Containers
 *
 * @property string $http_user_agent
 *
 * @property string $datasource
 * @property string $esi_scheme
 * @property string $esi_host
 * @property int $esi_port
 *
 * @property string $sso_scheme
 * @property string $sso_host
 * @property string $sso_iss
 * @property int $sso_port
 *
 * @property class-string<FetcherInterface> $fetcher
 *
 * @property class-string<LogInterface> $logger
 * @property string $logger_level
 * @property string $logfile_location
 * @property int $log_max_files
 *
 * @property class-string<CacheInterface> $cache
 *
 * @property string $file_cache_location
 *
 * @property string $redis_cache_location
 * @property string $redis_cache_prefix
 *
 * @property string $memcached_cache_host
 * @property int $memcached_cache_port
 * @property string $memcached_cache_prefix
 * @property bool $memcached_cache_compressed
 */
class EsiConfiguration extends AbstractArrayAccess
{

    use ConstructsContainers, ValidatesContainers;

    /**
     * @var array<string, mixed> $data
     */
    protected array $data = [
        'http_user_agent'            => 'Eseye Default Library',

        // Esi
        'datasource'                 => 'tranquility',
        'esi_scheme'                 => 'https',
        'esi_host'                   => 'esi.evetech.net',
        'esi_port'                   => 443,

        // Eve Online SSO
        'sso_scheme'                 => 'https',
        'sso_host'                   => 'login.eveonline.com',
        'sso_iss'                    => 'https://login.eveonline.com',
        'sso_port'                   => 443,

        // Fetcher
        'fetcher'                    => GuzzleFetcher::class,

        // Logging
        'logger'                     => RotatingFileLogger::class,
        'logger_level'               => 'info',
        'logfile_location'           => 'logs/',

        // Rotating Logger Details
        'log_max_files'              => 10,

        // Cache
        'cache'                      => FileCache::class,

        // File Cache
        'file_cache_location'        => 'cache/',

        // Redis Cache
        'redis_cache_location'       => 'tcp://127.0.0.1',
        'redis_cache_prefix'         => 'eseye:',

        // Memcached Cache
        'memcached_cache_host'       => '127.0.0.1',
        'memcached_cache_port'       => 11211,
        'memcached_cache_prefix'     => 'eseye:',
        'memcached_cache_compressed' => false,
    ];

}
