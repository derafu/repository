<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Contract;

use Derafu\Repository\Exception\DataProviderException;

/**
 * Interface for a registry of data source resolvers.
 */
interface DataSourceResolverRegistryInterface
{
    /**
     * Returns the resolver that supports the given raw source.
     *
     * @param string $source
     * @return DataSourceResolverInterface
     * @throws DataProviderException
     */
    public function getResolver(string $source): DataSourceResolverInterface;
}
