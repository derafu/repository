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

use Derafu\Config\Trait\ConfigurableTrait;
use Derafu\Repository\Contract\DataProviderInterface;
use Derafu\Repository\Contract\RepositoryFactoryInterface;
use Derafu\Repository\Contract\RepositoryInterface;
use Derafu\Repository\Contract\RepositoryManagerInterface;
use Derafu\Repository\Entity;
use Derafu\Repository\Exception\ManagerException;
use Derafu\Repository\Factory\DataProviderRepositoryFactory;
use Derafu\Repository\Repository;
use Exception;

/**
 * Resolves and reuses repositories, delegating their construction to factories.
 */
class RepositoryManager implements RepositoryManagerInterface
{
    use ConfigurableTrait;

    /**
     * Worker configuration schema.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $configurationSchema = [
        'entityClass' => [
            'types' => 'string',
            'default' => Entity::class,
        ],
        'repositoryClass' => [
            'types' => 'string',
            'default' => Repository::class,
        ],
    ];

    /** @var array<string, RepositoryInterface> */
    private array $loaded = [];

    /**
     * Explicit factories take precedence over the data-provider fallback.
     *
     * A manager with only explicit factories does not need a data provider.
     *
     * @param array<string, RepositoryFactoryInterface> $factories
     */
    public function __construct(
        private readonly ?DataProviderInterface $dataProvider = null,
        private readonly array $factories = [],
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigurationSchema(): array
    {
        return $this->configurationSchema;
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
        if ($this->dataProvider === null) {
            throw new ManagerException([
                'No repository factory configured for {repository}.',
                'repository' => $repository,
            ]);
        }

        return (new DataProviderRepositoryFactory(
            $this->dataProvider,
            $this->getConfiguration()
        ))->create($repository);
    }
}
