<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
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
        $contents = @file_get_contents($filepath);
        if ($contents === false) {
            throw new DataProviderException([
                'Could not read JSON file {file}.',
                'file' => $filepath,
            ]);
        }
        $data = json_decode($contents, true);

        if (!is_array($data)) {
            throw new DataProviderException([
                'The JSON file {file} must decode to an array.',
                'file' => $filepath,
            ]);
        }

        return $data;
    }
}
