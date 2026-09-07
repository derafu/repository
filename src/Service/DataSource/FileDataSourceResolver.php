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
use Derafu\Repository\Contract\FileFormatReaderRegistryInterface;

/**
 * Resolves any source as a file path.
 *
 * This is the catch-all resolver: it supports every source, so it must be
 * registered last in a DataSourceResolverRegistryInterface.
 */
final class FileDataSourceResolver implements DataSourceResolverInterface
{
    /**
     * Resolver constructor.
     *
     * @param FileFormatReaderRegistryInterface $fileFormatReaders Registry
     * used to resolve the reader for the file format.
     */
    public function __construct(
        private readonly FileFormatReaderRegistryInterface $fileFormatReaders,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $source): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(string $source): DataSourceInterface
    {
        return new FileDataSource($source, $this->fileFormatReaders);
    }
}
