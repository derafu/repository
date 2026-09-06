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
use Symfony\Component\Yaml\Yaml;

/**
 * Reads data from a YAML file.
 */
final class YamlFileFormatReader implements FileFormatReaderInterface
{
    /**
     * {@inheritDoc}
     */
    public function supports(string $extension): bool
    {
        return $extension === 'yaml';
    }

    /**
     * {@inheritDoc}
     */
    public function read(string $filepath): array
    {
        $data = Yaml::parseFile($filepath);

        if (!is_array($data)) {
            throw new DataProviderException(sprintf(
                'The YAML file %s must parse to an array.',
                $filepath
            ));
        }

        return $data;
    }
}
