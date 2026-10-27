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

use Derafu\Repository\Contract\DataSourceResolverInterface;
use Derafu\Repository\Contract\DataSourceResolverRegistryInterface;
use Derafu\Repository\Exception\DataProviderException;

/**
 * Registry of data source resolvers.
 *
 * Resolves the resolver that must be used for a given raw source string.
 * New schemes (e.g. `pdo:`) can be supported by registering additional
 * resolvers, without modifying this class or any of the built-in resolvers.
 */
final class DataSourceResolverRegistry implements DataSourceResolverRegistryInterface
{
    /**
     * Registered resolvers.
     *
     * @var DataSourceResolverInterface[]
     */
    private array $resolvers;

    /**
     * @param iterable<DataSourceResolverInterface> $resolvers
     */
    public function __construct(iterable $resolvers)
    {
        $this->resolvers = [...$resolvers];
    }

    /**
     * Registers an additional resolver.
     *
     * @param DataSourceResolverInterface $resolver
     * @return void
     */
    public function register(DataSourceResolverInterface $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * {@inheritDoc}
     */
    public function getResolver(string $source): DataSourceResolverInterface
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($source)) {
                return $resolver;
            }
        }

        throw new DataProviderException([
            'No data source resolver is registered for "{source}".',
            'source' => $source,
        ]);
    }
}
