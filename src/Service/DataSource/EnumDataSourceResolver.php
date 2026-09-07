<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Service\DataSource;

use Derafu\Repository\Contract\DataSourceInterface;
use Derafu\Repository\Contract\DataSourceResolverInterface;

/**
 * Resolves sources with the `enum:` scheme prefix into an EnumDataSource.
 *
 * Example: `enum:App\Enum\Status` reads its data from `App\Enum\Status`.
 */
final class EnumDataSourceResolver implements DataSourceResolverInterface
{
    /**
     * Scheme prefix recognized by this resolver.
     *
     * @var string
     */
    private const PREFIX = 'enum:';

    /**
     * {@inheritDoc}
     */
    public function supports(string $source): bool
    {
        return str_starts_with($source, self::PREFIX);
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(string $source): DataSourceInterface
    {
        $enumClass = substr($source, strlen(self::PREFIX));

        return new EnumDataSource($enumClass);
    }
}
