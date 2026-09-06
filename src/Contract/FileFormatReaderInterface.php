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
 * Interface for a file format reader.
 *
 * Implementations know how to parse one specific file format (PHP, JSON,
 * YAML, etc.) into a plain array.
 */
interface FileFormatReaderInterface
{
    /**
     * Determines whether this reader can handle the given file extension.
     *
     * @param string $extension File extension, in lower case and without
     * the leading dot (e.g. `json`).
     * @return bool
     */
    public function supports(string $extension): bool;

    /**
     * Reads and parses the file, returning its data as an array.
     *
     * @param string $filepath
     * @return array
     * @throws DataProviderException
     */
    public function read(string $filepath): array;
}
