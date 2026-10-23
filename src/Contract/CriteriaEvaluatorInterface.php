<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Contract;

use Doctrine\Common\Collections\Criteria;

/**
 * Interface for the evaluation of a criteria over rows.
 *
 * It evaluates like a database does: a comparison with a null value is not
 * true, except the ones that ask for null, and a field that a row does not
 * have is null. The operators that search a text ignore the case of its ASCII
 * letters.
 */
interface CriteriaEvaluatorInterface
{
    /**
     * Returns the fields that the criteria uses, in its conditions and in its
     * order.
     *
     * @param Criteria $criteria Criteria to inspect.
     * @return list<string>
     */
    public function fields(Criteria $criteria): array;

    /**
     * Returns the rows that meet the conditions of the criteria, keeping their
     * keys.
     *
     * The order and the page of the criteria are not applied.
     *
     * @param array<array-key, array<array-key, mixed>> $rows Rows to filter.
     * @param Criteria $criteria Criteria that the rows must meet.
     * @return array<array-key, array<array-key, mixed>>
     */
    public function filter(array $rows, Criteria $criteria): array;
}
