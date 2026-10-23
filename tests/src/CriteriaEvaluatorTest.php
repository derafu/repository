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

use Derafu\Repository\Service\CriteriaEvaluator;
use Derafu\Repository\Translation\RepositoryTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\Expr\Value;
use Doctrine\Common\Collections\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * A criteria is evaluated over rows like a database does: a comparison with
 * null is not true, except the ones that ask for null, and a field that a row
 * does not have is null.
 */
#[CoversClass(CriteriaEvaluator::class)]
#[UsesClass(RepositoryTranslationResourceProvider::class)]
final class CriteriaEvaluatorTest extends TestCase
{
    private const array ROWS = [
        1 => ['n' => 1, 's' => 'alpha', 't' => ['a' => 1], 'tags' => ['x', 'y']],
        2 => ['n' => 2, 's' => 'beta', 't' => ['a' => 2], 'tags' => ['z']],
        3 => ['n' => null, 's' => null],
        4 => ['n' => 4],
        5 => ['a.b' => 'dot'],
    ];

    /**
     * @param list<int> $expected Keys of the rows that meet the expression.
     */
    #[DataProvider('expressions')]
    public function testFiltersLikeADatabase(Expression $expression, array $expected): void
    {
        $rows = (new CriteriaEvaluator())->filter(self::ROWS, Criteria::create()->where($expression));

        $this->assertSame($expected, array_keys($rows));
    }

    /**
     * @return array<string, array{Expression, list<int>}>
     */
    public static function expressions(): array
    {
        $e = Criteria::expr();

        return [
            'eq' => [$e->eq('n', 1), [1]],
            'eq null is null or absent' => [$e->eq('n', null), [3, 5]],
            'eq null on a field that some rows lack' => [$e->eq('s', null), [3, 4, 5]],
            'neq leaves out null' => [$e->neq('n', 1), [2, 4]],
            'neq null is not null' => [$e->neq('n', null), [1, 2, 4]],
            'lt' => [$e->lt('n', 2), [1]],
            'lte' => [$e->lte('n', 2), [1, 2]],
            'gt leaves out null' => [$e->gt('n', 1), [2, 4]],
            'gte' => [$e->gte('n', 2), [2, 4]],
            'comparison with a null value is never true' => [$e->gt('n', null), []],
            'in' => [$e->in('n', [1, 4]), [1, 4]],
            'in an empty list' => [$e->in('n', []), []],
            'in a list with null' => [$e->in('s', ['alpha', null]), [1, 3, 4, 5]],
            'in a list without null leaves out null' => [$e->in('s', ['alpha']), [1]],
            'notIn leaves out null' => [$e->notIn('n', [1]), [2, 4]],
            'notIn an empty list leaves out null' => [$e->notIn('n', []), [1, 2, 4]],
            'notIn a list with null is never true' => [$e->notIn('s', ['alpha', null]), []],
            'contains' => [$e->contains('s', 'et'), [2]],
            'contains on a field that is not text' => [$e->contains('tags', 'x'), []],
            'contains a null value is never true' => [$e->contains('s', null), []],
            'startsWith' => [$e->startsWith('s', 'al'), [1]],
            'endsWith' => [$e->endsWith('s', 'ta'), [2]],
            'memberOf' => [$e->memberOf('tags', 'x'), [1]],
            'memberOf on a field that is not a list' => [$e->memberOf('n', 'x'), []],
            'field with dots reads inside the row' => [$e->eq('t.a', 2), [2]],
            'field with dots that is missing is null' => [$e->eq('t.b', null), [1, 2, 3, 4, 5]],
            'field with dots through a value that is not a list is null' => [$e->eq('n.x', null), [1, 2, 3, 4, 5]],
            'field whose name has dots' => [$e->eq('a.b', 'dot'), [5]],
            'and' => [$e->andX($e->gte('n', 1), $e->lt('n', 4)), [1, 2]],
            'and with an unknown answer is not true' => [$e->andX($e->gt('n', 0), $e->eq('s', 'alpha')), [1]],
            'or' => [$e->orX($e->eq('n', 1), $e->eq('s', null)), [1, 3, 4, 5]],
            'or with an unknown answer is not true' => [$e->orX($e->eq('n', 99), $e->gt('n', 3)), [4]],
            'not leaves out the unknown' => [$e->not($e->eq('n', 1)), [2, 4]],
        ];
    }

    public function testWithoutConditionsAllTheRowsMeetTheCriteria(): void
    {
        $criteria = Criteria::create()->orderBy(['n' => Order::Ascending]);

        $this->assertSame(self::ROWS, (new CriteriaEvaluator())->filter(self::ROWS, $criteria));
    }

    public function testListsTheFieldsOfTheConditionsAndOfTheOrderOnce(): void
    {
        $e = Criteria::expr();
        $criteria = Criteria::create()
            ->where($e->andX($e->eq('a', 1), $e->orX($e->gt('b', 2), $e->eq('a', 3))))
            ->orderBy(['c' => Order::Ascending, 'a' => Order::Descending]);
        $evaluator = new CriteriaEvaluator();

        $this->assertSame(['a', 'b', 'c'], $evaluator->fields($criteria));
        $this->assertSame(['c'], $evaluator->fields(Criteria::create()->orderBy(['c' => Order::Ascending])));
        $this->assertSame([], $evaluator->fields(Criteria::create()));
    }

    public function testAValueIsWalkedAsItself(): void
    {
        $this->assertSame(5, (new CriteriaEvaluator())->walkValue(new Value(5)));
    }

    /**
     * @return array<string, array{Expression, string}>
     */
    public static function unsupported(): array
    {
        return [
            'comparison operator' => [new Comparison('n', 'LIKE', new Value('a')), 'LIKE'],
            'composite type' => [new CompositeExpression('XOR', [Criteria::expr()->eq('n', 1)]), 'XOR'],
        ];
    }

    #[DataProvider('unsupported')]
    public function testAnUnsupportedOperatorIsRejectedWithATranslatableError(Expression $expression, string $operator): void
    {
        $translator = TranslatorFactory::create('es', resourceProviders: [new RepositoryTranslationResourceProvider()]);

        try {
            (new CriteriaEvaluator())->filter([], Criteria::create()->where($expression));
            $this->fail('An unsupported operator must be rejected.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertSame('Unsupported criteria operator ' . $operator . '.', $exception->getMessage());
            $this->assertSame('Operador de criterio ' . $operator . ' no soportado.', $exception->trans($translator));
        }
    }
}
