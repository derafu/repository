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
use Derafu\Repository\Contract\FileFormatReaderRegistryInterface;
use Derafu\Repository\Exception\DataProviderException;

/**
 * Data source that reads data from a file.
 *
 * The file format (PHP, JSON, YAML, etc.) is resolved from its extension
 * through a FileFormatReaderRegistryInterface.
 */
final class FileDataSource implements DataSourceInterface
{
    /**
     * Data source constructor.
     *
     * @param string $filepath Path of the file to read.
     * @param FileFormatReaderRegistryInterface $readers Registry used to
     * resolve the reader for the file format.
     */
    public function __construct(
        private readonly string $filepath,
        private readonly FileFormatReaderRegistryInterface $readers,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws DataProviderException
     */
    public function read(): array
    {
        $extension = strtolower(pathinfo($this->filepath, PATHINFO_EXTENSION));

        return $this->readers->getReader($extension)->read($this->filepath);
    }
}
