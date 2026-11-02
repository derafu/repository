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

use Derafu\Repository\Exception\ManagerException;

/**
 * Interface for entity manager.
 */
interface RepositoryManagerInterface
{
    /**
     * Returns the repository associated with an entity class or repository
     * identifier.
     *
     * When an entity class is given the repository is typed with that entity,
     * so its methods return instances of it. With any other identifier the
     * repository is typed with `object`.
     *
     * @template T of object
     * @param string|class-string<T> $repository Entity class or repository
     * identifier.
     * @return ($repository is class-string<T> ? RepositoryInterface<T> : RepositoryInterface<object>)
     * @throws ManagerException
     */
    public function getRepository(string $repository): RepositoryInterface;

    /**
     * Returns the identifiers of every repository that can be requested with
     * getRepository().
     *
     * This does not load any repository, it only lists the identifiers that
     * are available.
     *
     * @return string[]
     */
    public function getAvailableRepositories(): array;
}
