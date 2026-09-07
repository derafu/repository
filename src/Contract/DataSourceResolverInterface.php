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

/**
 * Interface for a data source resolver.
 *
 * Implementations know how to recognize a raw source string (e.g. one
 * carrying a scheme prefix like `enum:`) and turn it into the
 * DataSourceInterface instance that must be used to read it.
 */
interface DataSourceResolverInterface
{
    /**
     * Determines whether this resolver can handle the given raw source.
     *
     * @param string $source
     * @return bool
     */
    public function supports(string $source): bool;

    /**
     * Resolves the raw source into a data source instance.
     *
     * @param string $source
     * @return DataSourceInterface
     */
    public function resolve(string $source): DataSourceInterface;
}
