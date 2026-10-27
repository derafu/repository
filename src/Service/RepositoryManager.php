<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Service;

use Derafu\Repository\Contract\DataProviderInterface;
use Derafu\Repository\Contract\RepositoryFactoryInterface;
use Derafu\Repository\Contract\RepositoryInterface;
use Derafu\Repository\Contract\RepositoryManagerInterface;
use Derafu\Repository\Exception\ManagerException;
use Exception;

/**
 * Resolves and reuses repositories, delegating their construction to factories.
 */
class RepositoryManager implements RepositoryManagerInterface
{
    /** @var array<string, RepositoryInterface> */
    private array $loaded = [];

    /** @var array<string, RepositoryFactoryInterface> */
    private readonly array $factories;

    /**
     * Explicit factories take precedence over the injected default factory.
     * A manager with only explicit factories needs neither a default factory
     * nor a data provider.
     *
     * @param iterable<string, RepositoryFactoryInterface> $factories
     */
    public function __construct(
        private readonly ?RepositoryFactoryInterface $defaultFactory = null,
        private readonly ?DataProviderInterface $dataProvider = null,
        iterable $factories = [],
    ) {
        $this->factories = is_array($factories) ? $factories : iterator_to_array($factories);
    }

    /**
     * {@inheritDoc}
     */
    public function getRepository(string $repository): RepositoryInterface
    {
        if (!isset($this->loaded[$repository])) {
            try {
                $this->loaded[$repository] = $this->loadRepository($repository);
            } catch (ManagerException $e) {
                throw $e;
            } catch (Exception $e) {
                throw new ManagerException(['{message}', 'message' => $e->getMessage()]);
            }
        }

        return $this->loaded[$repository];
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableRepositories(): array
    {
        return array_values(array_unique([
            ...($this->dataProvider?->getSources() ?? []),
            ...array_keys($this->factories),
        ]));
    }

    private function loadRepository(string $repository): RepositoryInterface
    {
        if (isset($this->factories[$repository])) {
            return $this->factories[$repository]->create($repository);
        }
        if ($this->defaultFactory === null) {
            throw new ManagerException([
                'No repository factory configured for {repository}.',
                'repository' => $repository,
            ]);
        }

        return $this->defaultFactory->create($repository);
    }
}
