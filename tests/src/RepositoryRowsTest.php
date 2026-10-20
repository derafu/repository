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

use ArrayObject;
use Derafu\Repository\Exception\EntityException;
use Derafu\Repository\Repository;
use Derafu\Repository\Translation\RepositoryTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use Doctrine\Common\Collections\Criteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Repository::class)]
#[UsesClass(EntityException::class)]
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

    /** @return array<string, array{string, bool}> */
    public static function rowConsumers(): array
    {
        $cases = [];
        foreach (['find', 'findAll', 'findBy', 'findOneBy', 'count', 'findByCriteria'] as $method) {
            $cases[$method . ' after set'] = [$method, false];
            $cases[$method . ' after collection mutation'] = [$method, true];
        }

        return $cases;
    }

    #[DataProvider('rowConsumers')]
    public function testRowsAreCheckedAgainAfterInheritedMutation(string $method, bool $collectionMutation): void
    {
        $repository = new Repository(['good' => ['name' => 'Valid']]);
        if ($collectionMutation) {
            $repository->collection()->set('bad', 'invalid');
        } else {
            $repository->set('bad', 'invalid');
        }
        $snapshot = $repository->all();

        try {
            match ($method) {
                'find' => $repository->find('bad'),
                'findAll' => $repository->findAll(),
                'findBy' => $repository->findBy(['name' => 'Valid'], ['name' => 'ASC']),
                'findOneBy' => $repository->findOneBy(['name' => 'Valid']),
                'count' => $repository->count(['name' => 'Valid']),
                'findByCriteria' => $repository->findByCriteria(Criteria::create()->where(Criteria::expr()->eq('name', 'Valid'))),
                default => $this->fail('Unknown row consumer.'),
            };
            $this->fail('An invalid row introduced after loading must be rejected.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertSame('Repository row bad must be an array; string given.', $exception->getMessage());
            $this->assertSame($snapshot, $repository->all());
        }
    }

    public function testSortingMissingFieldsKeepsRowsAndTheirRelativeOrder(): void
    {
        $rows = [2 => ['name' => 'First'], 8 => ['name' => 'Second']];
        $repository = new Repository($rows);
        $results = $repository->findBy([], ['missing' => 'ASC']);
        $this->assertSame(array_values($rows), array_map(static fn (object $entity): array => (array) $entity, $results));
        $this->assertSame($rows, $repository->all());
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
