<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Service;

use Derafu\Repository\Contract\DataSourceInterface;
use Derafu\Repository\Contract\RepositorySourceProviderInterface;

/**
 * Contributes a source map supplied by application configuration.
 */
final readonly class ArrayRepositorySourceProvider implements RepositorySourceProviderInterface
{
    /**
     * @param array<string, string|DataSourceInterface> $sources
     */
    public function __construct(private array $sources = [])
    {
    }

    public function getSources(): iterable
    {
        return $this->sources;
    }
}
