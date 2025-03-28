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

namespace Seat\Eseye\Cache;

use Seat\Eseye\Containers\EsiResponse;

/**
 * Class NullCache.
 *
 * @package Seat\Eseye\Cache
 */
class NullCache implements CacheInterface
{
    public function set(string $uri, string $query, EsiResponse $data): mixed
    {
        return null;
    }

    public function get(string $uri, string $query = ''): EsiResponse|bool
    {
        return false;
    }

    public function forget(string $uri, string $query = ''): mixed
    {
        return null;
    }

    /**
     * @param  string  $uri
     * @param  string  $query
     * @return bool
     */
    public function has(string $uri, string $query = ''): bool
    {
        return false;
    }
}
