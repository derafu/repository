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
 * Reads data from a JSON file.
 */
final class JsonFileFormatReader implements FileFormatReaderInterface
{
    /**
     * {@inheritDoc}
     */
    public function supports(string $extension): bool
    {
        return $extension === 'json';
    }

    /**
     * {@inheritDoc}
     */
    public function read(string $filepath): array
    {
        $data = json_decode(file_get_contents($filepath), true);

        if (!is_array($data)) {
            throw new DataProviderException(sprintf(
                'The JSON file %s must decode to an array.',
                $filepath
            ));
        }

        return $data;
    }
}
