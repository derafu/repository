<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository\Fixture\Services;

use Derafu\Repository\Contract\FileFormatReaderInterface;
use RuntimeException;

final class IniCatalogueReader implements FileFormatReaderInterface
{
    public function supports(string $extension): bool
    {
        return $extension === 'catalogue';
    }

    public function read(string $filepath): array
    {
        $data = parse_ini_file($filepath, true, INI_SCANNER_TYPED);
        if ($data === false) {
            throw new RuntimeException('Cannot read the INI catalogue.');
        }

        return $data;
    }
}
