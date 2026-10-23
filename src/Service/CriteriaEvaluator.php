<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Service;

use Closure;
use Derafu\Repository\Contract\CriteriaEvaluatorInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\Expr\ExpressionVisitor;
use Doctrine\Common\Collections\Expr\Value;

/**
 * Evaluates a criteria over rows the way a database does.
 *
 * Each expression becomes a function of a row that answers true, false or
 * null (unknown), as SQL does when a value is null. A row meets the criteria
 * only if the answer is true.
 */
final class CriteriaEvaluator extends ExpressionVisitor implements CriteriaEvaluatorInterface
{
    /**
     * {@inheritDoc}
     */
    public function fields(Criteria $criteria): array
    {
        $fields = [];

        $expression = $criteria->getWhereExpression();
        if ($expression !== null) {
            $this->collectFields($expression, $fields);
        }
        foreach (array_keys($criteria->getOrderings()) as $field) {
            $fields[] = (string) $field;
        }

        return array_values(array_unique($fields));
    }

    /**
     * {@inheritDoc}
     */
    public function filter(array $rows, Criteria $criteria): array
    {
        $expression = $criteria->getWhereExpression();
        if ($expression === null) {
            return $rows;
        }

        /** @var Closure(array<array-key, mixed>): ?bool $meets */
        $meets = $this->dispatch($expression);

        return array_filter($rows, static fn (array $row): bool => $meets($row) === true);
    }

    /**
     * {@inheritDoc}
     *
     * @return Closure(array<array-key, mixed>): ?bool
     */
    public function walkComparison(Comparison $comparison): Closure
    {
        $field = $comparison->getField();
        $operator = $comparison->getOperator();
        $expected = $comparison->getValue()->getValue();

        $compare = match ($operator) {
            Comparison::EQ => self::equals(...),
            Comparison::NEQ => self::differs(...),
            Comparison::LT => static fn (mixed $actual, mixed $expected): ?bool => self::ordered($actual, $expected, static fn (int $order): bool => $order < 0),
            Comparison::LTE => static fn (mixed $actual, mixed $expected): ?bool => self::ordered($actual, $expected, static fn (int $order): bool => $order <= 0),
            Comparison::GT => static fn (mixed $actual, mixed $expected): ?bool => self::ordered($actual, $expected, static fn (int $order): bool => $order > 0),
            Comparison::GTE => static fn (mixed $actual, mixed $expected): ?bool => self::ordered($actual, $expected, static fn (int $order): bool => $order >= 0),
            Comparison::IN => self::isIn(...),
            Comparison::NIN => self::isNotIn(...),
            Comparison::CONTAINS => static fn (mixed $actual, mixed $expected): ?bool => self::text($actual, $expected, str_contains(...)),
            Comparison::STARTS_WITH => static fn (mixed $actual, mixed $expected): ?bool => self::text($actual, $expected, str_starts_with(...)),
            Comparison::ENDS_WITH => static fn (mixed $actual, mixed $expected): ?bool => self::text($actual, $expected, str_ends_with(...)),
            Comparison::MEMBER_OF => self::isMember(...),
            default => throw self::unsupported($operator),
        };

        return static fn (array $row): ?bool => $compare(self::value($row, $field), $expected);
    }

    /**
     * {@inheritDoc}
     *
     * @return Closure(array<array-key, mixed>): ?bool
     */
    public function walkCompositeExpression(CompositeExpression $expr): Closure
    {
        $type = $expr->getType();
        $children = array_map($this->dispatch(...), $expr->getExpressionList());

        /** @var list<Closure(array<array-key, mixed>): ?bool> $children */
        return match ($type) {
            CompositeExpression::TYPE_AND => static function (array $row) use ($children): ?bool {
                $unknown = false;
                foreach ($children as $child) {
                    $answer = $child($row);
                    if ($answer === false) {
                        return false;
                    }
                    $unknown = $unknown || $answer === null;
                }

                return $unknown ? null : true;
            },
            CompositeExpression::TYPE_OR => static function (array $row) use ($children): ?bool {
                $unknown = false;
                foreach ($children as $child) {
                    $answer = $child($row);
                    if ($answer === true) {
                        return true;
                    }
                    $unknown = $unknown || $answer === null;
                }

                return $unknown ? null : false;
            },
            CompositeExpression::TYPE_NOT => static function (array $row) use ($children): ?bool {
                $answer = $children[0]($row);

                return $answer === null ? null : !$answer;
            },
            default => throw self::unsupported($type),
        };
    }

    /**
     * {@inheritDoc}
     */
    public function walkValue(Value $value): mixed
    {
        return $value->getValue();
    }

    /**
     * @param list<string> $fields
     */
    private function collectFields(Expression $expression, array &$fields): void
    {
        if ($expression instanceof Comparison) {
            $fields[] = $expression->getField();
        } elseif ($expression instanceof CompositeExpression) {
            foreach ($expression->getExpressionList() as $child) {
                $this->collectFields($child, $fields);
            }
        }
    }

    /**
     * Reads a field of a row. A field that the row does not have is null, and
     * a field with dots reads inside the arrays of the row.
     *
     * @param array<array-key, mixed> $row
     */
    private static function value(array $row, string $field): mixed
    {
        if (array_key_exists($field, $row)) {
            return $row[$field];
        }

        $value = $row;
        foreach (explode('.', $field) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private static function equals(mixed $actual, mixed $expected): ?bool
    {
        if ($expected === null) {
            return $actual === null;
        }

        return $actual === null ? null : $actual == $expected;
    }

    private static function differs(mixed $actual, mixed $expected): ?bool
    {
        if ($expected === null) {
            return $actual !== null;
        }

        return $actual === null ? null : $actual != $expected;
    }

    /**
     * @param Closure(int): bool $meets
     */
    private static function ordered(mixed $actual, mixed $expected, Closure $meets): ?bool
    {
        if ($actual === null || $expected === null) {
            return null;
        }

        return $meets($actual <=> $expected);
    }

    private static function isIn(mixed $actual, mixed $expected): ?bool
    {
        $values = is_array($expected) ? $expected : [$expected];
        $notNull = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
        if ($values === []) {
            return false;
        }
        if ($actual === null) {
            return count($notNull) !== count($values) ? true : null;
        }

        return in_array($actual, $notNull);
    }

    private static function isNotIn(mixed $actual, mixed $expected): ?bool
    {
        $values = is_array($expected) ? $expected : [$expected];
        $notNull = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
        if ($actual === null) {
            return null;
        }
        if (in_array($actual, $notNull)) {
            return false;
        }

        return count($notNull) !== count($values) ? null : true;
    }

    /**
     * Tests a text ignoring the case of its ASCII letters, as LIKE does in
     * SQLite. The other letters must be the same.
     *
     * @param Closure(string, string): bool $test
     */
    private static function text(mixed $actual, mixed $expected, Closure $test): ?bool
    {
        if ($actual === null || $expected === null) {
            return null;
        }
        if (!is_scalar($actual) || !is_scalar($expected)) {
            return false;
        }

        return $test(strtolower((string) $actual), strtolower((string) $expected));
    }

    private static function isMember(mixed $actual, mixed $expected): ?bool
    {
        if ($actual === null) {
            return null;
        }

        return is_array($actual) && in_array($expected, $actual);
    }

    private static function unsupported(string $operator): InvalidArgumentException
    {
        return new InvalidArgumentException([
            'Unsupported criteria operator {operator}.',
            'operator' => $operator,
        ]);
    }
}
