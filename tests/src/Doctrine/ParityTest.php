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
use Derafu\Repository\Service\CriteriaEvaluator;
use Derafu\TestsRepository\Doctrine\Fixture\Item;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * What a repository over rows must answer exactly like Doctrine's repository.
 * Doctrine is the oracle: each operation runs against both and the results
 * must be equal, order included, or both must fail.
 */
#[CoversClass(Repository::class)]
#[UsesClass(CriteriaEvaluator::class)]
final class ParityTest extends DoctrineTestCase
{
    /**
     * @param Closure(EntityRepository<Item>|Repository): mixed $operation
     */
    #[DataProvider('operations')]
    public function testBehavesLikeDoctrine(Closure $operation, bool $fails): void
    {
        [$expected, $expectedError] = self::execute($operation, $this->database);
        [$actual, $actualError] = self::execute($operation, $this->file);

        // Guards the oracle: a case that declares an error must fail in
        // Doctrine, and one that does not must succeed.
        $this->assertSame($fails, $expectedError !== null, 'Doctrine must behave as the case declares.');

        if ($fails) {
            $this->assertInstanceOf(TranslatableInvalidArgumentException::class, $actualError);

            return;
        }

        $this->assertNull($actualError, $actualError?->getMessage() ?? '');
        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{Closure(EntityRepository<Item>|Repository): mixed, bool}>
     */
    public static function operations(): array
    {
        return [
            'findBy filters, orders, limits and offsets' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['active' => true], ['price' => 'DESC'], 2, 1),
                false,
            ],
            'findBy with a list of values' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['name' => ['alpha', 'beta']]),
                false,
            ],
            'findBy with an empty list' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['name' => []]),
                false,
            ],
            'findBy null matches NULL and absent' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['note' => null]),
                false,
            ],
            'findBy list with null matches NULL and the values' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['note' => ['n2', null]]),
                false,
            ],
            'findBy without criteria ordered' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy([], ['price' => 'ASC']),
                false,
            ],
            'findBy with an unknown field' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['nope' => 1]),
                true,
            ],
            'findBy ordered by lowercase direction' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy([], ['price' => 'desc']),
                false,
            ],
            'findBy ordered by direction with spaces' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy([], ['price' => ' desc ']),
                false,
            ],
            'findBy ordered by an invalid direction' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy([], ['price' => 'foo']),
                true,
            ],
            'findBy null does not match zero' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['stock' => null]),
                false,
            ],
            'findBy null does not match the empty string' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['tag' => null]),
                false,
            ],
            'findBy zero matches only zero' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['stock' => 0]),
                false,
            ],
            'findBy the empty string matches only the empty string' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['tag' => '']),
                false,
            ],
            'findBy list with zero and null' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy(['stock' => [0, null]]),
                false,
            ],
            'findBy ordered by a field absent in some rows, ascending' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy([], ['note' => 'ASC', 'id' => 'ASC']),
                false,
            ],
            'findBy ordered by a field absent in some rows, descending' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy([], ['note' => 'DESC', 'id' => 'ASC']),
                false,
            ],
            'findBy ordered by an unknown field' => [
                static fn (EntityRepository|Repository $r): array => $r->findBy([], ['nope' => 'ASC']),
                true,
            ],
            'findOneBy without results' => [
                static fn (EntityRepository|Repository $r): ?object => $r->findOneBy(['name' => 'zzz']),
                false,
            ],
            'findOneBy ordered' => [
                static fn (EntityRepository|Repository $r): ?object => $r->findOneBy(['active' => true], ['price' => 'desc']),
                false,
            ],
            'findOneBy with an unknown field' => [
                static fn (EntityRepository|Repository $r): ?object => $r->findOneBy(['nope' => 1]),
                true,
            ],
            'find by a string id' => [
                static fn (EntityRepository|Repository $r): ?object => $r->find('2'),
                false,
            ],
            'find a missing id' => [
                static fn (EntityRepository|Repository $r): ?object => $r->find(99),
                false,
            ],
            'count with a filter' => [
                static fn (EntityRepository|Repository $r): int => $r->count(['active' => true]),
                false,
            ],
            'count null' => [
                static fn (EntityRepository|Repository $r): int => $r->count(['note' => null]),
                false,
            ],
            'count with an unknown field' => [
                static fn (EntityRepository|Repository $r): int => $r->count(['nope' => 1]),
                true,
            ],
            'criteria with comparison, order and limit' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()
                        ->where(Criteria::expr()->gt('price', 10))
                        ->orderBy(['price' => Order::Descending])
                        ->setMaxResults(2)
                ),
                false,
            ],
            'criteria with contains' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()
                        ->where(Criteria::expr()->contains('name', 'a'))
                        ->orderBy(['name' => Order::Ascending])
                ),
                false,
            ],
            'criteria with isNull' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->isNull('note'))
                ),
                false,
            ],
            'criteria with and' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->andX(Criteria::expr()->gte('price', 20), Criteria::expr()->lt('price', 50)))
                ),
                false,
            ],
            'criteria with or' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->orX(Criteria::expr()->eq('name', 'alpha'), Criteria::expr()->eq('price', 50)))
                ),
                false,
            ],
            'criteria with not leaves out null' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->not(Criteria::expr()->eq('note', 'n2')))
                ),
                false,
            ],
            'criteria with eq null' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->eq('note', null))
                ),
                false,
            ],
            'criteria with neq null' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->neq('note', null))
                ),
                false,
            ],
            'criteria with isNotNull' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->isNotNull('note'))
                ),
                false,
            ],
            'criteria with neq leaves out null' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->neq('note', 'n2'))
                ),
                false,
            ],
            'criteria with in' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->in('note', ['n2', 'n4']))
                ),
                false,
            ],
            'criteria with notIn leaves out null' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->notIn('note', ['n2']))
                ),
                false,
            ],
            'criteria with greater than leaves out null' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->gt('stock', 0))
                ),
                false,
            ],
            'criteria with less than or equal leaves out null' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->lte('stock', 0))
                ),
                false,
            ],
            'criteria with startsWith' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->startsWith('name', 'al'))
                ),
                false,
            ],
            'criteria with endsWith' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->endsWith('name', 'ta'))
                ),
                false,
            ],
            'criteria with contains in another case' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->contains('name', 'A'))
                ),
                false,
            ],
            'criteria with startsWith in another case' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->startsWith('name', 'AL'))
                ),
                false,
            ],
            'criteria with endsWith in another case' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->endsWith('name', 'TA'))
                ),
                false,
            ],
            'criteria with contains does not fold non-ASCII letters' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->contains('name', 'ñ'))
                ),
                false,
            ],
            'criteria with contains matches non-ASCII letters as they are' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->contains('name', 'Ñ'))
                ),
                false,
            ],
            'criteria with a maximum of zero results' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->setMaxResults(0)
                ),
                false,
            ],
            'criteria with only a first result' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->orderBy(['id' => Order::Ascending])->setFirstResult(2)
                ),
                false,
            ],
            'criteria ordered by a field absent in some rows, ascending' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->orderBy(['note' => Order::Ascending, 'id' => Order::Ascending])
                ),
                false,
            ],
            'criteria ordered by a field absent in some rows, descending' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->orderBy(['note' => Order::Descending, 'id' => Order::Ascending])
                ),
                false,
            ],
            'criteria with an unknown field' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->where(Criteria::expr()->eq('nope', 1))
                ),
                true,
            ],
            'criteria ordered by an unknown field' => [
                static fn (EntityRepository|Repository $r): array => self::matching(
                    $r,
                    Criteria::create()->orderBy(['nope' => Order::Ascending])
                ),
                true,
            ],
        ];
    }
}
