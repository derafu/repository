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
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Where a repository over rows differs from Doctrine's repository on purpose:
 * Doctrine knows the fields from its mapping, a repository only from its rows.
 * Each case also runs Doctrine, so the test notices if its side changes.
 */
#[CoversClass(Repository::class)]
#[UsesClass(CriteriaEvaluator::class)]
final class DivergenceTest extends DoctrineTestCase
{
    public function testAnEmptyRepositoryAcceptsAnyField(): void
    {
        $operation = static fn (EntityRepository|Repository $r): array => $r->findBy(['nope' => 1]);

        [, $databaseError] = self::execute($operation, $this->database);
        $this->assertNotNull($databaseError, 'Doctrine rejects a field that is not mapped.');

        $this->assertSame([], $operation(new Repository([])));
    }

    public function testAFieldThatNoRowHasIsUnknown(): void
    {
        $rows = array_map(
            static fn (array $row): array => array_diff_key($row, ['tag' => true]),
            self::ROWS
        );
        $operation = static fn (EntityRepository|Repository $r): array => $r->findBy(['tag' => null]);

        [$expected, $databaseError] = self::execute($operation, $this->database);
        $this->assertNull($databaseError, 'Doctrine knows the column although no row fills it.');
        $this->assertSame([3, 5], $expected);

        $this->expectException(TranslatableInvalidArgumentException::class);
        $operation(new Repository($rows));
    }

    public function testTheTextOfASearchIsNotAPattern(): void
    {
        $contains = static fn (string $text): Closure => static fn (EntityRepository|Repository $r): array => self::matching(
            $r,
            Criteria::create()->where(Criteria::expr()->contains('name', $text))
        );

        // Doctrine does not escape % and _ in the text, so they work as the
        // wildcards of LIKE. A repository looks for the text as it is.
        foreach (['%', '_'] as $text) {
            [$expected] = self::execute($contains($text), $this->database);
            $this->assertIsArray($expected);
            $this->assertCount(count(self::ROWS), $expected);
            $this->assertSame([], $contains($text)($this->file));
        }

        [$expected] = self::execute($contains('a_p'), $this->database);
        $this->assertSame([1], $expected);
        $this->assertSame([], $contains('a_p')($this->file));
    }

    public function testANegativeLimitIsRejected(): void
    {
        $operation = static fn (EntityRepository|Repository $r): array => $r->findBy([], null, -1);

        // SQLite takes a negative limit as no limit, other databases reject
        // it, and a repository does too.
        [$expected, $databaseError] = self::execute($operation, $this->database);
        $this->assertNull($databaseError);
        $this->assertIsArray($expected);
        $this->assertCount(count(self::ROWS), $expected);

        $this->expectException(TranslatableInvalidArgumentException::class);
        $operation($this->file);
    }
}
