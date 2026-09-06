<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight File Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Service\DataSource\FileFormat;

use Derafu\Repository\Contract\FileFormatReaderInterface;
use Derafu\Repository\Exception\DataProviderException;

/**
 * Reads data from a PHP file that returns an array.
 */
final class PhpFileFormatReader implements FileFormatReaderInterface
{
    /**
     * {@inheritDoc}
     */
    public function supports(string $extension): bool
    {
        return $extension === 'php';
    }

    /**
     * {@inheritDoc}
     */
    public function read(string $filepath): array
    {
        $data = require $filepath;

        if (!is_array($data)) {
            throw new DataProviderException(sprintf(
                'The PHP file %s must return an array.',
                $filepath
            ));
        }

        return $data;
    }
}
