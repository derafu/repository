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

use Derafu\Repository\Contract\DataSourceInterface;
use PDO;
use RuntimeException;

final readonly class SqliteCatalogueSource implements DataSourceInterface
{
    public function __construct(private PDO $connection)
    {
    }

    public function read(): array
    {
        $statement = $this->connection->query('SELECT id, id AS codigo, name AS glosa FROM catalog ORDER BY id');
        if ($statement === false) {
            throw new RuntimeException('Cannot read the catalogue.');
        }

        return $statement->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }
}
