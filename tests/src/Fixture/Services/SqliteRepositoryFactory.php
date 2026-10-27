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

use Derafu\Repository\Contract\RepositoryFactoryInterface;
use Derafu\Repository\Contract\RepositoryInterface;
use Derafu\TestsRepository\Fixture\Factory\SqliteRepository;
use PDO;

final readonly class SqliteRepositoryFactory implements RepositoryFactoryInterface
{
    public function __construct(private PDO $connection)
    {
    }

    public function create(string $repository): RepositoryInterface
    {
        return new SqliteRepository($this->connection);
    }
}
