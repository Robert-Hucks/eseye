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

use Seat\Eseye\Cache\CacheInterface;
use Seat\Eseye\Containers\EsiConfiguration;
use Seat\Eseye\Exceptions\InvalidConfigurationException;
use Seat\Eseye\Log\LogInterface;

/**
 * Class Configuration.
 *
 * @mixin EsiConfiguration
 *
 * @package Seat\Eseye
 */
class Configuration
{
    private static Configuration|null $instance = null;

    protected LogInterface|null $logger = null;

    protected CacheInterface|null $cache = null;

    protected EsiConfiguration $configuration;

    /**
     * Configuration constructor.
     */
    public function __construct()
    {
        $this->configuration = new EsiConfiguration();
    }

    public static function getInstance(): self
    {
        if (is_null(self::$instance))
            self::$instance = new self();

        return self::$instance;
    }

    public function getConfiguration(): EsiConfiguration
    {
        return $this->configuration;
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function setConfiguration(EsiConfiguration $configuration): void
    {

        if (! $configuration->valid())
            throw new InvalidConfigurationException(
                'The configuration is empty/invalid values');

        $this->configuration = $configuration;
    }

    public function getLogger(): LogInterface
    {
        if (! $this->logger)
            $this->logger = new $this->configuration->logger;

        return $this->logger;
    }

    public function getCache(): CacheInterface
    {
        if (! $this->cache)
            $this->cache = new $this->configuration->cache;

        return $this->cache;
    }

    /**
     * Magic method to get the configuration from the configuration
     * property.
     */
    public function __get(string $name): mixed
    {
        return $this->configuration->$name;
    }

    public function __set(string $name, string $value): void
    {

        $this->configuration->$name = $value;
    }
}
