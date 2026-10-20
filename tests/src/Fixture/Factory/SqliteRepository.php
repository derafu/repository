<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository\Fixture\Factory;

use Derafu\Repository\Contract\RepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use InvalidArgumentException;
use PDO;
use stdClass;

/**
 * Test adapter for a two-column catalogue, independent of ContainerInterface.
 *
 * It deliberately has a different constructor from the in-memory repository.
 */
final class SqliteRepository implements RepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function find($id, $lockMode = null, $lockVersion = null): ?object
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function findAll(): array
    {
        return $this->findBy([]);
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $values] = $this->where($criteria);
        $sql = 'SELECT id, name FROM catalog' . $where;
        if ($orderBy !== null) {
            $order = [];
            foreach ($orderBy as $field => $direction) {
                $column = $this->column($field);
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    throw new InvalidArgumentException('Invalid sort direction.');
                }
                $order[] = $column . ' ' . $direction;
            }
            if ($order !== []) {
                $sql .= ' ORDER BY ' . implode(', ', $order);
            }
        }
        if ($limit !== null || $offset !== null) {
            $sql .= ' LIMIT ' . ($limit ?? -1) . ' OFFSET ' . ($offset ?? 0);
        }
        $statement = $this->connection->prepare($sql);
        $statement->execute($values);

        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        return $this->findBy($criteria, $orderBy, 1)[0] ?? null;
    }

    public function count(array $criteria = []): int
    {
        [$where, $values] = $this->where($criteria);
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM catalog' . $where);
        $statement->execute($values);
        $count = $statement->fetchColumn();
        if (!is_int($count) && !is_string($count)) {
            throw new InvalidArgumentException('Expected an integer count.');
        }

        return (int) $count;
    }

    public function findByCriteria(Criteria $criteria): array
    {
        // This test adapter uses collection criteria only for this optional path.
        return (new ArrayCollection($this->findAll()))->matching($criteria)->toArray();
    }

    public function getClassName(): string
    {
        return stdClass::class;
    }

    /**
     * @param array<array-key, mixed> $criteria
     * @return array{string, list<int|string>}
     */
    private function where(array $criteria): array
    {
        $conditions = [];
        $values = [];
        foreach ($criteria as $field => $value) {
            if (!is_int($value) && !is_string($value)) {
                throw new InvalidArgumentException('Expected an integer or string criterion.');
            }
            $conditions[] = $this->column($field) . ' = ?';
            $values[] = $value;
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $values];
    }

    private function column(int|string $field): string
    {
        if ($field !== 'id' && $field !== 'name') {
            throw new InvalidArgumentException('Unknown catalogue column.');
        }

        return $field;
    }
}
