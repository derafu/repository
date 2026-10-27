<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository\Fixture\Services;

use Derafu\Repository\Contract\DataSourceInterface;
use Derafu\Repository\Contract\DataSourceResolverInterface;

final readonly class CatalogueResolver implements DataSourceResolverInterface
{
    public function __construct(private DataSourceInterface $source)
    {
    }

    public function supports(string $source): bool
    {
        return $source === 'catalogue:database';
    }

    public function resolve(string $source): DataSourceInterface
    {
        return $this->source;
    }
}
