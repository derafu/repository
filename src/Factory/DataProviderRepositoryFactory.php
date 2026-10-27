<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Factory;

use Derafu\Config\Contract\ConfigurableInterface;
use Derafu\Config\Contract\ConfigurationInterface;
use Derafu\Config\Trait\ConfigurableTrait;
use Derafu\Repository\Contract\CriteriaEvaluatorInterface;
use Derafu\Repository\Contract\DataProviderInterface;
use Derafu\Repository\Contract\RepositoryFactoryInterface;
use Derafu\Repository\Contract\RepositoryInterface;
use Derafu\Repository\Entity;
use Derafu\Repository\Exception\ManagerException;
use Derafu\Repository\Mapping as DEM;
use Derafu\Repository\Repository;
use ReflectionClass;

/**
 * Builds in-memory repositories from normalized data and entity metadata.
 */
final class DataProviderRepositoryFactory implements RepositoryFactoryInterface, ConfigurableInterface
{
    use ConfigurableTrait;

    /** @var array<string, array<string, mixed>> */
    protected array $configurationSchema = [
        'entityClass' => ['types' => 'string', 'default' => Entity::class],
        'repositoryClass' => ['types' => 'string', 'default' => Repository::class],
    ];

    /**
     * Interface suffix.
     *
     * @var string
     */
    private const ENTITY_INTERFACE_SUFFIX = 'Interface';

    /**
     * Interface namespace.
     *
     * Important: only the immediately superior level.
     *
     * @var string
     */
    private const ENTITY_INTERFACE_NAMESPACE = 'Contract';

    /**
     * Entity class suffix.
     *
     * Important: no suffixes are used in entities, but it's standardized here
     * in the constant.
     *
     * @var string
     */
    private const ENTITY_CLASS_SUFFIX = ''; // Empty on purpose.

    /**
     * Entity namespace.
     *
     * Important: only the immediately superior level.
     *
     * @var string
     */
    private const ENTITY_CLASS_NAMESPACE = 'Entity';

    /** @param array<array-key, mixed>|ConfigurationInterface $config */
    public function __construct(
        private readonly DataProviderInterface $dataProvider,
        private readonly CriteriaEvaluatorInterface $criteriaEvaluator,
        array|ConfigurationInterface $config = [],
    ) {
        $this->setConfiguration($config);
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
    public function create(string $repository): RepositoryInterface
    {
        $entityClass = $this->resolveEntityClass($repository);
        $repositoryClass = $this->resolveRepositoryClass($entityClass);
        $data = $this->dataProvider->fetch($repository);
        $idAttribute = $this->dataProvider->getConfiguration()->get(
            'normalization.idAttribute'
        );
        if (!is_string($idAttribute)) {
            throw new ManagerException([
                'Normalization setting {attribute} must be a string; {type} given.',
                'attribute' => 'idAttribute',
                'type' => get_debug_type($idAttribute),
            ]);
        }

        return new $repositoryClass($data, $entityClass, $idAttribute, $this->criteriaEvaluator);
    }

    /**
     * Determines the entity class that should be used with the repository.
     *
     * The repository identifier can be the FQCN of the entity class (ideal) or
     * a generic identifier that will be resolved to the entity class configured
     * in the worker.
     *
     * If the repository identifier is an interface it's expected:
     *
     *   - The interface is within a "Contract" namespace.
     *   - The interface has "Interface" as suffix.
     *   - The entity is within the "Entity" namespace without suffix.
     *
     * @param string $repository Repository identifier.
     * @return class-string Entity class for the repository.
     */
    private function resolveEntityClass(string $repository): string
    {
        // If the repository identifier "looks like" a class it's assumed to be
        // the entity class or an entity interface in the same namespace.
        if (str_contains($repository, '\\')) {
            $entityClass = $this->guessEntityClass($repository);
        }

        // The default entity class is returned when the identifier is not a
        // class. If it doesn't have "\" then it doesn't have a namespace, in
        // this case it's assumed it's not a class.
        else {
            $entityClass = $this->getConfiguration()->get('entityClass');
        }

        // Throw error if the class doesn't exist as it might have been
        // misspelled by the programmer.
        if (!is_string($entityClass)) {
            throw new ManagerException([
                'Repository setting {setting} must be a string; {type} given.',
                'setting' => 'entityClass',
                'type' => get_debug_type($entityClass),
            ]);
        }
        if (!class_exists($entityClass)) {
            throw new ManagerException([
                'Entity class {class} does not exist. Could it be misspelled?',
                'class' => $entityClass,
            ]);
        }

        // Return the entity class.
        return $entityClass;
    }

    /**
     * Guesses the entity class in case the input class is an interface.
     *
     * @param string $class
     * @return string
     */
    private function guessEntityClass(string $class): string
    {
        // If the class is an interface it's assumed to be an entity class in
        // the same namespace. This is rigid and requires a format for the class
        // and interface FQCN. For now it's sufficient.
        if (str_ends_with($class, self::ENTITY_INTERFACE_SUFFIX)) {
            $length = strlen($class) - strlen(self::ENTITY_INTERFACE_SUFFIX);
            return str_replace(
                '\\' . self::ENTITY_INTERFACE_NAMESPACE .  '\\',
                '\\' . self::ENTITY_CLASS_NAMESPACE  . '\\',
                substr($class, 0, $length)
            ) . self::ENTITY_CLASS_SUFFIX;
        }

        // Return the same class, as it doesn't have the expected format to
        // guess the entity class.
        return $class;
    }

    /**
     * Determines the repository class that should be used for an entity.
     *
     * If the entity class doesn't provide information about its associated
     * repository, the repository class configured in the worker will be
     * returned.
     *
     * @param class-string $entityClass Entity class.
     * @return class-string<Repository> Repository class.
     */
    private function resolveRepositoryClass(string $entityClass): string
    {
        // Try to get the repository class from a PHP attribute of the entity
        // class.
        $reflectionClass = new ReflectionClass($entityClass);
        $attributes = $reflectionClass->getAttributes(DEM\Entity::class);
        if (!empty($attributes)) {
            $repositoryClass = $attributes[0]->newInstance()->repositoryClass;
            if ($repositoryClass) {
                return $this->requireRepositoryClass($repositoryClass);
            }
        }

        return $this->requireRepositoryClass(
            $this->getConfiguration()->get('repositoryClass')
        );
    }

    /**
     * Validates the class constructed with the in-memory repository arguments.
     *
     * Other implementations belong in explicitly registered factories.
     *
     * @return class-string<Repository>
     */
    private function requireRepositoryClass(mixed $repositoryClass): string
    {
        if (!is_string($repositoryClass)) {
            throw new ManagerException([
                'Repository setting {setting} must be a string; {type} given.',
                'setting' => 'repositoryClass',
                'type' => get_debug_type($repositoryClass),
            ]);
        }
        if (!class_exists($repositoryClass)) {
            throw new ManagerException([
                'Repository class {class} does not exist. Could it be misspelled?',
                'class' => $repositoryClass,
            ]);
        }
        if (!is_a($repositoryClass, Repository::class, true)) {
            throw new ManagerException([
                'Repository class {class} must extend {base}. Register a factory for other repository implementations.',
                'class' => $repositoryClass,
                'base' => Repository::class,
            ]);
        }
        if (!(new ReflectionClass($repositoryClass))->isInstantiable()) {
            throw new ManagerException([
                'Repository class {class} must be instantiable.',
                'class' => $repositoryClass,
            ]);
        }

        return $repositoryClass;
    }
}
