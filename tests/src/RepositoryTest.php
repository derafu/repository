<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository;

use ArrayObject;
use Derafu\Repository\Repository;
use Derafu\Repository\Service\CriteriaEvaluator;
use Doctrine\Common\Collections\Criteria;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

#[CoversClass(Repository::class)]
#[UsesClass(CriteriaEvaluator::class)]
class RepositoryTest extends TestCase
{
    /** @return array<string, array{array<array-key, mixed>, array{method: string, args: list<mixed>, expected?: mixed, expectedException?: class-string<Throwable>}}> */
    public static function provideTestCases(): array
    {
        $tests = require dirname(__DIR__) . '/fixtures/repository.php';

        $testCases = [];

        foreach ($tests as $name => $test) {
            foreach ($test['cases'] as $caseName => $case) {
                $testCases[$name . ':' . $caseName] = [
                    $test['data'],
                    $case,
                ];
            }
        }

        return $testCases;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array{method: string, args: list<mixed>, expected?: mixed, expectedException?: class-string<Throwable>} $case
     */
    #[DataProvider('provideTestCases')]
    public function testRepositoryCase(array $data, array $case): void
    {
        $repository = new Repository($data, idAttribute: 'id');

        $method = $case['method'];
        $args = $case['args'];
        $expected = $case['expected'] ?? null;
        $expectedException = $case['expectedException'] ?? null;

        if ($expectedException !== null) {
            $this->expectException($expectedException);
        }

        switch ($method) {
            case 'find':
                $result = $repository->find(...$args);
                break;
            case 'findAll':
                $result = $repository->findAll();
                break;
            case 'findBy':
                $criteria = $args[0];
                $this->assertIsArray($criteria);
                $orderBy = $args[1] ?? null;
                $limit = $args[2] ?? null;
                $offset = $args[3] ?? null;
                if ($orderBy !== null) {
                    $this->assertIsArray($orderBy);
                }
                if ($limit !== null) {
                    $this->assertIsInt($limit);
                }
                if ($offset !== null) {
                    $this->assertIsInt($offset);
                }
                $result = $repository->findBy($criteria, $orderBy, $limit, $offset);
                break;
            case 'findOneBy':
                $criteria = $args[0];
                $this->assertIsArray($criteria);
                $orderBy = $args[1] ?? null;
                if ($orderBy !== null) {
                    $this->assertIsArray($orderBy);
                }
                $result = $repository->findOneBy($criteria, $orderBy);
                break;
            case 'count':
                $criteria = $args[0] ?? [];
                $this->assertIsArray($criteria);
                $result = $repository->count($criteria);
                break;
            case 'findByCriteria':
                $criteria = $args[0];
                $this->assertInstanceOf(Criteria::class, $criteria);
                $result = $repository->findByCriteria($criteria);
                break;
            default:
                throw new InvalidArgumentException(sprintf(
                    'Método %s no soportado.',
                    $method
                ));
        }

        if ($expectedException === null) {

            // Asegurar que el resultado sea arreglo, y si tiene objetos dentro que
            // también sean arreglos.
            $json = json_encode($result);
            $this->assertIsString($json);
            $result = json_decode($json, true);

            $this->assertSame($expected, $result);

        }
    }

    /**
     * Prueba de repositorio con datos vacíos.
     */
    public function testEmptyRepository(): void
    {
        $repository = new Repository([], idAttribute: 'id');

        $this->assertSame(0, $repository->count());
        $this->assertSame([], $repository->findAll());
        $this->assertNull($repository->find('non-existent'));
        $this->assertNull($repository->findOneBy(['key' => 'value']));
        $this->assertSame([], $repository->findBy(['key' => 'value']));
    }

    /**
     * Prueba de repositorio con tipos de datos mixtos.
     */
    public function testMixedDataTypes(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Text', 'value' => 10],
            ['id' => 2, 'name' => 42, 'value' => true],
            ['id' => 3, 'name' => null, 'value' => 3.14],
        ];

        $repository = new Repository($data, idAttribute: 'id');

        $this->assertSame(3, $repository->count());

        $foundItem = $repository->find(1); // find() busca por el índice de los datos.

        $this->assertInstanceOf(stdClass::class, $foundItem);
        $this->assertSame(42, $foundItem->name);
    }

    /**
     * Prueba de repositorio con datos provenientes de un archivo PHP y con
     * idAttribute definido.
     */
    public function testRepositoryFromFileWithIdAttribute(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'repository') . '.php';
        file_put_contents($file, '<?php return [' . var_export(['name' => 'a'], true) . ', ' . var_export(['name' => 'b'], true) . '];');

        try {
            $repository = new Repository($file, idAttribute: 'id');

            $this->assertSame(2, $repository->count());

            $foundItem = $repository->find(0);

            $this->assertInstanceOf(stdClass::class, $foundItem);
            $this->assertSame('a', $foundItem->name);
        } finally {
            unlink($file);
        }
    }

    /**
     * Prueba de repositorio con datos provenientes de un ArrayObject y con
     * idAttribute definido.
     */
    public function testRepositoryFromArrayObjectWithIdAttribute(): void
    {
        $data = new ArrayObject([
            ['name' => 'a'],
            ['name' => 'b'],
        ]);

        $repository = new Repository($data, idAttribute: 'id');

        $this->assertSame(2, $repository->count());

        $foundItem = $repository->find(0);

        $this->assertInstanceOf(stdClass::class, $foundItem);
        $this->assertSame('a', $foundItem->name);
    }
}
