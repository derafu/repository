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
use Derafu\Repository\Contract\CriteriaEvaluatorInterface;
use Derafu\Repository\Contract\RepositoryInterface;
use Derafu\Repository\Exception\EntityException;
use Derafu\Repository\Service\CriteriaEvaluator;
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
     * Fields of the repository: the keys of its rows.
     *
     * A repository has no schema, so a field is known if some row has it.
     *
     * @var array<array-key, true>
     */
    private array $fields = [];

    /**
     * Evaluator of the criteria used by matching().
     */
    private CriteriaEvaluatorInterface $criteriaEvaluator;

    /**
     * Repository constructor.
     *
     * @param string|array<array-key, mixed>|ArrayAccess<*, *>|ArrayObject<*, *> $source Data array or PHP
     * file path.
     * @param string|null $entityClass Entity class associated with the
     * repository.
     * @param string|null $idAttribute Name of the ID attribute that must be
     * ensured to exist in elements loaded from the repository.
     * @param CriteriaEvaluatorInterface|null $criteriaEvaluator Evaluator of
     * the criteria used by matching().
     */
    public function __construct(
        string|array|ArrayAccess|ArrayObject $source,
        ?string $entityClass = null,
        private ?string $idAttribute = null,
        ?CriteriaEvaluatorInterface $criteriaEvaluator = null
    ) {
        if ($entityClass !== null) {
            $this->entityClass = $entityClass;
        }
        $this->criteriaEvaluator = $criteriaEvaluator ?? new CriteriaEvaluator();

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
        $this->fields = [];
        foreach ($data as $row) {
            foreach (array_keys($row) as $field) {
                $this->fields[$field] = true;
            }
        }
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
        $this->requireFields(array_keys($criteria));
        $orderBy = $orderBy ? $this->requireOrderBy($orderBy) : null;

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

        $this->requireFields(array_keys($criteria));

        $results = array_filter(
            $this->requireRows($this->data->toArray()),
            fn (array $item) => $this->matchCriteria($item, $criteria)
        );

        return count($results);
    }

    /**
     * {@inheritDoc}
     *
     * @return ArrayCollection<int, object>
     */
    public function matching(Criteria $criteria): ArrayCollection
    {
        // A field with dots reads inside a row, so its first part is the field
        // of the repository, unless a field has that very name.
        $this->requireFields(array_map(
            fn (string $field): string => isset($this->fields[$field])
                ? $field
                : explode('.', $field, 2)[0],
            $this->criteriaEvaluator->fields($criteria)
        ));

        $rows = $this->criteriaEvaluator->filter(
            $this->requireRows($this->data->toArray()),
            $criteria
        );

        $orderBy = [];
        foreach ($criteria->getOrderings() as $field => $direction) {
            $orderBy[$field] = $direction->name === 'Descending' ? 'DESC' : 'ASC';
        }
        if ($orderBy) {
            $rows = $this->applyOrderBy($rows, $orderBy);
        }

        // A maximum of zero results is none, as in a database.
        $limit = $criteria->getMaxResults();
        $rows = $limit === 0
            ? []
            : array_slice($rows, max(0, $criteria->getFirstResult() ?? 0), $limit);

        return new ArrayCollection(array_values(array_map($this->createEntity(...), $rows)));
    }

    /**
     * {@inheritDoc}
     */
    public function findByCriteria(Criteria $criteria): array
    {
        return $this->matching($criteria)->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function getClassName(): string
    {
        return $this->entityClass;
    }

    /**
     * Rejects a field that no row has.
     *
     * A repository without rows has no fields to check against, so it accepts
     * any field.
     *
     * @param list<int|string> $fields
     */
    private function requireFields(array $fields): void
    {
        if ($this->fields === []) {
            return;
        }

        foreach ($fields as $field) {
            if (!isset($this->fields[$field])) {
                throw new InvalidArgumentException([
                    'Unknown field {field} in repository {entity}.',
                    'field' => $field,
                    'entity' => $this->entityClass,
                ]);
            }
        }
    }

    /**
     * Validates the order and normalizes its directions.
     *
     * @param array<array-key, mixed> $orderBy Ordering ['field' => 'ASC|DESC'],
     * the direction in any case and with spaces around.
     * @return array<array-key, 'ASC'|'DESC'>
     */
    private function requireOrderBy(array $orderBy): array
    {
        $directions = [];
        foreach ($orderBy as $field => $direction) {
            $normalized = is_string($direction) ? strtoupper(trim($direction)) : null;
            if ($normalized !== 'ASC' && $normalized !== 'DESC') {
                throw new InvalidArgumentException([
                    'Invalid order direction {direction} for field {field}; only ASC and DESC are allowed.',
                    'direction' => is_string($direction) ? $direction : get_debug_type($direction),
                    'field' => $field,
                ]);
            }
            $this->requireFields([$field]);
            $directions[$field] = $normalized;
        }

        return $directions;
    }

    /**
     * Checks if an item meets the search criteria.
     *
     * A field that the item does not have is null. A null value matches only
     * null, and a list matches null if it contains it.
     *
     * @param array<array-key, mixed> $item
     * @param array<array-key, mixed> $criteria
     */
    protected function matchCriteria(array $item, array $criteria): bool
    {
        foreach ($criteria as $field => $value) {
            $values = is_array($value) ? $value : [$value];
            $notNull = array_values(array_filter(
                $values,
                static fn (mixed $value): bool => $value !== null
            ));
            $actual = $item[$field] ?? null;

            $matches = $actual === null
                ? count($notNull) !== count($values)
                : in_array($actual, $notNull);
            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Orders results according to specified criteria.
     *
     * A field that a result does not have is null, which is the smallest
     * value: first when ascending and last when descending.
     *
     * @param array<array-key, mixed> $results Results to order.
     * @param array<array-key, 'ASC'|'DESC'> $orderBy Ordering criteria ['field' => 'ASC|DESC'].
     * @return array<array-key, array<array-key, mixed>> Ordered results.
     */
    protected function applyOrderBy(array $results, array $orderBy): array
    {
        $results = $this->requireRows($results);
        uasort($results, function (array $a, array $b) use ($orderBy): int {
            foreach ($orderBy as $field => $direction) {
                $x = $a[$field] ?? null;
                $y = $b[$field] ?? null;

                $compare = match (true) {
                    $x === null && $y === null => 0,
                    $x === null => -1,
                    $y === null => 1,
                    default => $x <=> $y,
                };

                if ($compare !== 0) {
                    return $direction === 'DESC' ? -$compare : $compare;
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
