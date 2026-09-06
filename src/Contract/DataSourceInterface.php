<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight File Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Contract;

use Derafu\Repository\Exception\DataProviderException;

/**
 * Interface for a single data source.
 *
 * Implementations know how to read raw data from one specific origin (a
 * file, a PDO connection, an enum, etc.) and return it as a plain array.
 *
 * This is the extension point used to add new kinds of sources to a
 * DataProviderInterface without changing it.
 */
interface DataSourceInterface
{
    /**
     * Reads and returns the raw data of the source.
     *
     * @return array
     * @throws DataProviderException
     */
    public function read(): array;
}
