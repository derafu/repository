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
 * Interface for a registry of file format readers.
 */
interface FileFormatReaderRegistryInterface
{
    /**
     * Returns the reader that supports the given file extension.
     *
     * @param string $extension
     * @return FileFormatReaderInterface
     * @throws DataProviderException
     */
    public function getReader(string $extension): FileFormatReaderInterface;
}
