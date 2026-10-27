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
 * Contributes repository identifiers and their origins without loading data.
 */
interface RepositorySourceProviderInterface
{
    /**
     * @return iterable<string, string|DataSourceInterface>
     */
    public function getSources(): iterable;
}
