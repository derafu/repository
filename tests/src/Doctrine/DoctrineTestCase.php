<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository\Doctrine;

use Closure;
use Derafu\Repository\Repository;
use Derafu\TestsRepository\Doctrine\Fixture\ArrayCachePool;
use Derafu\TestsRepository\Doctrine\Fixture\Item;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PDO;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

/**
 * Doctrine's repository over a table, and a repository over rows, with the
 * same data. Rows that omit a field stand for NULL in the database.
 */
abstract class DoctrineTestCase extends TestCase
{
    protected const array ROWS = [
        1 => ['id' => 1, 'name' => 'alpha', 'price' => 10, 'active' => true, 'stock' => 0, 'tag' => ''],
        2 => ['id' => 2, 'name' => 'beta', 'price' => 20, 'active' => true, 'note' => 'n2', 'stock' => 5, 'tag' => 't'],
        3 => ['id' => 3, 'name' => 'gamma', 'price' => 30, 'active' => false, 'note' => null, 'stock' => null, 'tag' => null],
        4 => ['id' => 4, 'name' => 'delta', 'price' => 40, 'active' => true, 'note' => 'n4', 'stock' => 0, 'tag' => ''],
        5 => ['id' => 5, 'name' => 'epsilon', 'price' => 50, 'active' => false],
        6 => ['id' => 6, 'name' => 'Ñandú', 'price' => 60, 'active' => true, 'note' => 'n6', 'stock' => 6, 'tag' => 'u'],
    ];

    /** @var EntityRepository<Item> */
    protected EntityRepository $database;

    protected Repository $file;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite is required to compare with Doctrine.');
        }

        $config = ORMSetup::createConfig(false, null, new ArrayCachePool());
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Fixture']));
        $config->enableNativeLazyObjects(true);
        $entityManager = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config
        );
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Item::class)]);
        foreach (self::ROWS as $row) {
            $entityManager->persist(new Item(
                $row['id'],
                $row['name'],
                $row['price'],
                $row['active'],
                $row['note'] ?? null,
                $row['stock'] ?? null,
                $row['tag'] ?? null
            ));
        }
        $entityManager->flush();
        $entityManager->clear();

        $this->database = $entityManager->getRepository(Item::class);
        $this->file = new Repository(self::ROWS);
    }

    /**
     * @param EntityRepository<Item>|Repository $repository
     * @return array<array-key, mixed>
     */
    protected static function matching(EntityRepository|Repository $repository, Criteria $criteria): array
    {
        return $repository->matching($criteria)->toArray();
    }

    /**
     * @param Closure(EntityRepository<Item>|Repository): mixed $operation
     * @param EntityRepository<Item>|Repository $repository
     * @return array{mixed, Throwable|null}
     */
    protected static function execute(Closure $operation, EntityRepository|Repository $repository): array
    {
        try {
            return [self::normalize($operation($repository)), null];
        } catch (Throwable $error) {
            return [null, $error];
        }
    }

    /**
     * Reduces entities, which differ between the two sides, to their id.
     */
    protected static function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }
        if ($value instanceof Item) {
            return $value->getId();
        }
        if ($value instanceof stdClass) {
            return $value->id;
        }

        return $value;
    }
}
