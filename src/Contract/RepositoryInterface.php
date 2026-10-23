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

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\Common\Collections\Selectable;

/**
 * Interface for object/entity repositories.
 *
 * Provides standard methods to access and search objects/entities from a data
 * source.
 *
 * A repository answers like a database does. A comparison with a null value is
 * not true, except those that ask for null, and an object that does not have a
 * field has it as null. A field that the repository does not know is an error.
 *
 * @extends Selectable<array-key, object>
 */
interface RepositoryInterface extends Selectable
{
    /**
     * Finds an object by its identifier.
     *
     * @param mixed $id Object identifier
     * @param mixed $lockMode Not used in this implementation.
     * @param mixed $lockVersion Not used in this implementation.
     * @return object|null The found object or null if it doesn't exist
     */
    public function find(mixed $id, $lockMode = null, $lockVersion = null): ?object;

    /**
     * Finds all objects in the repository.
     *
     * @return object[] Array of found objects
     */
    public function findAll(): array;

    /**
     * Finds objects according to specific criteria.
     *
     * The fields of the criteria are combined with AND. A list of values
     * matches any of them. A null value matches the objects whose field is
     * null, or that do not have it, and a list that contains null matches them
     * too. Any other value does not match a null field.
     *
     * The ordering fields are applied in sequence. A direction is ASC or DESC,
     * in any case. A null field is the smallest value: it goes first when
     * ascending and last when descending.
     *
     * @param array<array-key, mixed> $criteria Search criteria in format ['field' => 'value'].
     * @param array<array-key, mixed>|null $orderBy Ordering criteria ['field' => 'ASC|DESC'].
     * @param int|null $limit Maximum number of results to return.
     * @param int|null $offset Number of results to skip.
     * @return object[] Array of objects that meet the criteria.
     * @throws \Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException
     * If a field is not known by the repository, if a direction is not ASC or
     * DESC, or if the limit or the offset is negative.
     */
    public function findBy(
        array $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array;

    /**
     * Finds a single object according to specific criteria.
     *
     * The criteria and the order are those of `findBy()`.
     *
     * @param array<array-key, mixed> $criteria Search criteria in format ['field' => 'value'].
     * @param array<array-key, mixed>|null $orderBy Ordering criteria ['field' => 'ASC|DESC'].
     * @return object|null The first object that meets the criteria or null if
     * it doesn't exist.
     * @throws \Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException
     * If a field is not known by the repository or if a direction is not ASC
     * or DESC.
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    /**
     * Returns the total number of objects in the repository.
     *
     * The criteria are those of `findBy()`.
     *
     * @param array<array-key, mixed> $criteria Criteria when counting in format ['field' => 'value'].
     * @return int Number of objects.
     * @throws \Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException
     * If a field is not known by the repository.
     */
    public function count(array $criteria = []): int;

    /**
     * Finds the objects that meet a `Criteria`, as a collection.
     *
     * The `Criteria` defines the conditions, the order and the page of the
     * results. The collection contains only objects that meet the conditions,
     * and its keys are the positions in the result.
     *
     * @param Criteria $criteria The `Criteria` object that defines the
     * conditions, order and limits of the results.
     * @return ReadableCollection<array-key, object>&Selectable<array-key, object>
     * @throws \Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException
     * If the `Criteria` uses a field that is not known by the repository or an
     * operator that is not supported.
     * @see \Doctrine\Common\Collections\Criteria
     */
    public function matching(Criteria $criteria): ReadableCollection&Selectable;

    /**
     * Finds the objects that meet a `Criteria`, as an array.
     *
     * It gives the objects of `matching()`.
     *
     * @param Criteria $criteria The `Criteria` object that defines the
     * conditions, order and limits of the results.
     * @return array<array-key, mixed> An array with the objects that meet the criterion.
     * @throws \Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException
     * If the `Criteria` uses a field that is not known by the repository or an
     * operator that is not supported.
     */
    public function findByCriteria(Criteria $criteria): array;

    /**
     * Returns the name of the class that the repository manages.
     *
     * @return string
     */
    public function getClassName(): string;
}
