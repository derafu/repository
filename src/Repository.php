<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository;

use ArrayAccess;
use ArrayObject;
use BackedEnum;
use Derafu\Repository\Contract\RepositoryInterface;
use Derafu\Repository\Exception\EntityException;
use Derafu\Support\Arr;
use Derafu\Support\Factory;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use stdClass;
use Throwable;
use Traversable;

/**
 * Class for object/entity repositories.
 *
 * Provides standard methods to access and search objects/entities from a data
 * source.
 */
class Repository implements RepositoryInterface
{
    /**
     * Entity class where data obtained through the repository will be placed.
     *
     * @var string
     */
    protected string $entityClass = stdClass::class;

    /**
     * Rows of the repository, keyed by identifier.
     *
     * @var ArrayCollection<array-key, array<array-key, mixed>>
     */
    private ArrayCollection $data;

    /**
     * Repository constructor.
     *
     * @param string|array<array-key, mixed>|ArrayAccess<*, *>|ArrayObject<*, *> $source Data array or PHP
     * file path.
     * @param string|null $entityClass Entity class associated with the
     * repository.
     * @param string|null $idAttribute Name of the ID attribute that must be
     * ensured to exist in elements loaded from the repository.
     */
    public function __construct(
        string|array|ArrayAccess|ArrayObject $source,
        ?string $entityClass = null,
        private ?string $idAttribute = null
    ) {
        if ($entityClass !== null) {
            $this->entityClass = $entityClass;
        }

        $this->load($source);
    }

    /**
     * Loads repository data.
     *
     * @param string|array<array-key, mixed>|ArrayAccess<*, *>|ArrayObject<*, *> $source
     * @return void
     */
    protected function load(
        string|array|ArrayAccess|ArrayObject $source,
    ): void {
        $data = is_string($source) ? require $source : $source;
        if (!is_array($data)) {
            $data = $this->rowsFrom($data);
        }
        $data = $this->requireRows($data);
        if ($this->idAttribute) {
            $data = Arr::ensureIdInElements($data, $this->idAttribute);
        }
        $this->data = new ArrayCollection($data);
    }

    /**
     * Returns all the rows of the repository, keyed by identifier.
     *
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return $this->data->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function find(mixed $id, $lockMode = null, $lockVersion = null): ?object
    {
        if (!is_string($id) && !is_int($id)) {
            throw new InvalidArgumentException([
                'In method {class}:find($id) an $id of type {type} was passed and only string and int are allowed.',
                'class' => static::class,
                'type' => get_debug_type($id),
            ]);
        }

        return isset($this->data[$id])
            ? $this->createEntity($this->requireRow($this->data[$id], $id))
            : null
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(): array
    {
        return array_values(array_map(
            $this->createEntity(...),
            $this->requireRows($this->data->toArray())
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function findBy(
        array $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $results = array_filter(
            $this->requireRows($this->data->toArray()),
            fn (array $item) => $this->matchCriteria($item, $criteria)
        );

        if ($orderBy) {
            $results = $this->applyOrderBy($results, $orderBy);
        }

        if ($offset !== null || $limit !== null) {
            $offset = $offset ?: 0;
            if ($offset < 0) {
                throw new InvalidArgumentException('Offset cannot be negative.');
            }

            if ($limit !== null && $limit < 0) {
                throw new InvalidArgumentException('Limit cannot be negative.');
            }

            $results = array_slice($results, $offset, $limit);
        }

        return array_values(array_map(
            fn ($item) => $this->createEntity($item),
            $results
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        $results = $this->findBy($criteria, $orderBy, 1);

        return empty($results) ? null : reset($results);
    }

    /**
     * {@inheritDoc}
     */
    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            return count($this->data);
        }

        $results = array_filter(
            $this->requireRows($this->data->toArray()),
            fn (array $item) => $this->matchCriteria($item, $criteria)
        );

        return count($results);
    }

    /**
     * {@inheritDoc}
     */
    public function findByCriteria(Criteria $criteria): array
    {
        $this->requireRows($this->data->toArray());
        $rows = $this->requireRows($this->data->matching($criteria)->toArray());

        return array_values(array_map($this->createEntity(...), $rows));
    }

    /**
     * {@inheritDoc}
     */
    public function getClassName(): string
    {
        return $this->entityClass;
    }

    /**
     * Checks if an item meets the search criteria.
     *
     * @param array<array-key, mixed> $item
     * @param array<array-key, mixed> $criteria
     */
    protected function matchCriteria(array $item, array $criteria): bool
    {
        foreach ($criteria as $field => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }
            if (!isset($item[$field]) || !in_array($item[$field], $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Orders results according to specified criteria.
     *
     * @param array<array-key, mixed> $results Results to order.
     * @param array<array-key, mixed> $orderBy Ordering criteria ['field' => 'ASC|DESC'].
     * @return array<array-key, array<array-key, mixed>> Ordered results.
     */
    protected function applyOrderBy(array $results, array $orderBy): array
    {
        $results = $this->requireRows($results);
        uasort($results, function ($a, $b) use ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                if (!isset($a[$field]) || !isset($b[$field])) {
                    continue;
                }

                $compare = $direction === 'DESC'
                    ? -1 * ($a[$field] <=> $b[$field])
                    : $a[$field] <=> $b[$field];

                if ($compare !== 0) {
                    return $compare;
                }
            }
            return 0;
        });

        return $results;
    }

    /**
     * Creates an entity from data.
     *
     * If the entity class is a (backed) enum, the case is resolved from the
     * id attribute instead of being hydrated, as enums cannot be instantiated
     * with `new`.
     *
     * @param array<array-key, mixed> $data Data that will be assigned to the entity.
     * @return object Entity instance with loaded data.
     * @throws EntityException If an enum entity cannot be resolved.
     */
    protected function createEntity(array $data): object
    {
        if (is_a($this->entityClass, BackedEnum::class, true)) {
            $attribute = $this->idAttribute ?? '';
            $value = $data[$attribute] ?? null;
            if (!is_int($value) && !is_string($value)) {
                throw new EntityException([
                    'The ID attribute "{attribute}" of enum entity {entity} must be an int or string; {type} given.',
                    'attribute' => $attribute,
                    'entity' => $this->entityClass,
                    'type' => get_debug_type($value),
                ]);
            }

            try {
                return ($this->entityClass)::from($value);
            } catch (Throwable $e) {
                throw new EntityException([
                    'Could not create enum entity {entity}: {error}',
                    'entity' => $this->entityClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (enum_exists($this->entityClass)) {
            throw new EntityException([
                '{entity} must be a backed enum to be used as a repository entity.',
                'entity' => $this->entityClass,
            ]);
        }

        $entityClass = $this->entityClass;
        if (!class_exists($entityClass)) {
            throw new EntityException([
                'Entity class {entity} does not exist.',
                'entity' => $entityClass,
            ]);
        }

        return Factory::create($data, $entityClass);
    }

    /**
     * Converts a source that is not an array into an array of rows.
     *
     * @param ArrayAccess<*, *>|ArrayObject<*, *> $data
     * @return array<array-key, mixed>
     */
    private function rowsFrom(ArrayAccess|ArrayObject $data): array
    {
        if ($data instanceof ArrayObject) {
            return (array) $data;
        }

        if (!$data instanceof Traversable) {
            throw new InvalidArgumentException(
                'ArrayAccess must implement Traversable to be convertible.'
            );
        }

        return iterator_to_array($data);
    }

    /**
     * Validates rows without changing their keys or values.
     *
     * @param array<array-key, mixed> $rows
     * @return array<array-key, array<array-key, mixed>>
     */
    private function requireRows(array $rows): array
    {
        $validated = [];
        foreach ($rows as $key => $row) {
            $validated[$key] = $this->requireRow($row, $key);
        }

        return $validated;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function requireRow(mixed $row, int|string $key): array
    {
        if (!is_array($row)) {
            throw new InvalidArgumentException([
                'Repository row {key} must be an array; {type} given.',
                'key' => $key,
                'type' => get_debug_type($row),
            ]);
        }

        return $row;
    }
}
