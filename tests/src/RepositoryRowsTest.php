<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository;

use ArrayAccess;
use ArrayIterator;
use ArrayObject;
use Derafu\Repository\Exception\EntityException;
use Derafu\Repository\Repository;
use Derafu\Repository\Service\CriteriaEvaluator;
use Derafu\Repository\Translation\RepositoryTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\Common\Collections\Selectable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Repository::class)]
#[UsesClass(EntityException::class)]
#[UsesClass(CriteriaEvaluator::class)]
#[UsesClass(RepositoryTranslationResourceProvider::class)]
final class RepositoryRowsTest extends TestCase
{
    public function testListsAndSparseNumericAndTextKeysKeepTheirIdentity(): void
    {
        $rows = [
            0 => ['name' => 'First', 'price' => 100],
            7 => ['id' => 'explicit', 'name' => 'Sparse'],
            'product-1' => ['name' => 'Laptop', 'enabled' => false, 'extra' => null, 'nested' => ['a' => 1]],
        ];
        $expected = [
            0 => ['id' => 0, 'name' => 'First', 'price' => 100],
            7 => ['id' => 'explicit', 'name' => 'Sparse'],
            'product-1' => ['id' => 'product-1', 'name' => 'Laptop', 'enabled' => false, 'extra' => null, 'nested' => ['a' => 1]],
        ];
        foreach ([$rows, new ArrayObject($rows)] as $source) {
            $repository = new Repository($source, idAttribute: 'id');
            $this->assertSame($expected, $repository->all());
            $this->assertNull($repository->find(1));
            foreach ($expected as $key => $row) {
                $entity = $repository->find($key);
                $this->assertInstanceOf(stdClass::class, $entity);
                $this->assertSame($row, (array) $entity);
            }
        }

        $list = new Repository([['name' => 'First'], ['name' => 'Second']]);
        $this->assertSame([0, 1], array_keys($list->all()));
        $this->assertSame(['name' => 'Second'], (array) $list->find(1));
    }

    public function testNumericFieldKeysAreNotRejectedOrConvertedDuringLoading(): void
    {
        $row = [0 => 'first field', 3 => null, 'named' => true];
        $repository = new Repository([9 => $row]);
        $this->assertSame([9 => $row], $repository->all());
        $this->assertSame($row, (array) $repository->find(9));
    }

    public function testATraversableArrayAccessSourceIsLoadedAsRows(): void
    {
        $rows = [3 => ['name' => 'Third'], 'key' => ['name' => 'Keyed']];
        $repository = new Repository(new ArrayIterator($rows));
        $this->assertSame($rows, $repository->all());
        $this->assertSame(['name' => 'Keyed'], (array) $repository->find('key'));
    }

    public function testAnArrayAccessSourceThatIsNotTraversableIsRejected(): void
    {
        $source = new class () implements ArrayAccess {
            public function offsetExists(mixed $offset): bool
            {
                return false;
            }

            public function offsetGet(mixed $offset): mixed
            {
                return null;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
            }

            public function offsetUnset(mixed $offset): void
            {
            }
        };

        try {
            new Repository($source);
            $this->fail('An ArrayAccess source must be traversable.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertSame('ArrayAccess must implement Traversable to be convertible.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new RepositoryTranslationResourceProvider()]);
            $this->assertSame('ArrayAccess debe implementar Traversable para ser convertible.', $exception->trans($translator));
        }
    }

    /** @return array<string, array{mixed}> */
    public static function invalidRows(): array
    {
        return [
            'null' => [null],
            'false' => [false],
            'integer' => [7],
            'float' => [1.5],
            'string' => ['invalid'],
            'object' => [new stdClass()],
        ];
    }

    #[DataProvider('invalidRows')]
    public function testInvalidRowsAreRejectedBeforeAddingIds(mixed $row): void
    {
        foreach ([null, 'id'] as $idAttribute) {
            try {
                new Repository([9 => $row], idAttribute: $idAttribute);
                $this->fail('A repository row must be an array.');
            } catch (TranslatableInvalidArgumentException $exception) {
                $type = get_debug_type($row);
                $this->assertSame('Repository row 9 must be an array; ' . $type . ' given.', $exception->getMessage());
                $translator = TranslatorFactory::create('es', resourceProviders: [new RepositoryTranslationResourceProvider()]);
                $this->assertSame('La fila 9 del repositorio debe ser un arreglo; se recibió ' . $type . '.', $exception->trans($translator));
            }
        }
    }

    public function testResourcesAreRejectedWithoutClosingThem(): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        try {
            try {
                new Repository(['record' => $resource]);
                $this->fail('A resource is not a repository row.');
            } catch (TranslatableInvalidArgumentException $exception) {
                $this->assertSame('Repository row record must be an array; resource (stream) given.', $exception->getMessage());
                $this->assertSame(10, fwrite($resource, 'still open'));
            }
        } finally {
            fclose($resource);
        }
    }

    public function testSortingByAFieldSomeRowsLackKeepsThoseRowsInTheirRelativeOrder(): void
    {
        $rows = [
            2 => ['name' => 'First'],
            8 => ['name' => 'Second'],
            9 => ['name' => 'Third', 'rank' => 1],
        ];
        $repository = new Repository($rows);
        $names = static fn (array $entities): array => array_map(
            static fn (stdClass $entity): string => $entity->name,
            $entities
        );

        $this->assertSame(['First', 'Second', 'Third'], $names($repository->findBy([], ['rank' => 'ASC'])));
        $this->assertSame(['Third', 'First', 'Second'], $names($repository->findBy([], ['rank' => 'DESC'])));
        $this->assertSame($rows, $repository->all());
    }

    public function testACriteriaReadsInsideFieldsWithDotsAndChecksTheirFirstPart(): void
    {
        $repository = new Repository([
            1 => ['id' => 1, 'nested' => ['a' => 1]],
            2 => ['id' => 2, 'nested' => ['a' => 2]],
        ]);

        $found = $repository->findByCriteria(Criteria::create()->where(Criteria::expr()->eq('nested.a', 2)));
        $this->assertCount(1, $found);
        $this->assertInstanceOf(stdClass::class, $found[0]);
        $this->assertSame(2, $found[0]->id);

        $this->expectException(TranslatableInvalidArgumentException::class);
        $repository->findByCriteria(Criteria::create()->where(Criteria::expr()->eq('nope.a', 1)));
    }

    public function testMatchingGivesTheEntitiesThatFindByCriteriaGives(): void
    {
        $repository = new Repository([
            1 => ['id' => 1, 'name' => 'a'],
            2 => ['id' => 2, 'name' => 'b'],
            3 => ['id' => 3, 'name' => 'c'],
        ]);
        $criteria = Criteria::create()
            ->where(Criteria::expr()->gt('id', 1))
            ->orderBy(['id' => Order::Descending])
        ;

        $ids = static fn (array $entities): array => array_map(
            static fn (mixed $entity): mixed => $entity instanceof stdClass ? $entity->id : null,
            $entities
        );

        $this->assertInstanceOf(Selectable::class, $repository);
        $matching = $repository->matching($criteria);
        $this->assertSame([0, 1], $matching->getKeys());
        $this->assertSame([3, 2], $ids($matching->toArray()));
        $this->assertSame($ids($matching->toArray()), $ids($repository->findByCriteria($criteria)));
    }

    public function testAMissingEntityClassIsRejectedOnlyWhenAnEntityIsCreated(): void
    {
        $class = 'Derafu\\TestsRepository\\Fixture\\MissingEntity';
        $empty = new Repository([], $class);
        $this->assertSame($class, $empty->getClassName());
        $this->assertNull($empty->find('missing'));
        $this->assertSame([], $empty->findAll());

        $repository = new Repository(['record' => []], $class);
        try {
            $repository->find('record');
            $this->fail('A nonexistent entity class must be rejected.');
        } catch (EntityException $exception) {
            $this->assertSame('Entity class ' . $class . ' does not exist.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new RepositoryTranslationResourceProvider()]);
            $this->assertSame('La clase de entidad ' . $class . ' no existe.', $exception->trans($translator));
            $this->assertSame(['record' => []], $repository->all());
        }
    }
}
