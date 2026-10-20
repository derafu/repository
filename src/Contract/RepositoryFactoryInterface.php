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

/**
 * Creates repositories with their own infrastructure dependencies.
 */
interface RepositoryFactoryInterface
{
    /**
     * Creates the repository registered under the given identifier.
     *
     * The identifier may be an entity class, an interface or an application ID.
     * Repository instances are reused by the manager, not by this factory.
     */
    public function create(string $repository): RepositoryInterface;
}
