<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository\Fixture\Services;

use Derafu\Repository\Contract\CriteriaEvaluatorInterface;
use Doctrine\Common\Collections\Criteria;

final class RecordingCriteriaEvaluator implements CriteriaEvaluatorInterface
{
    public int $filterCalls = 0;

    public function __construct(private readonly CriteriaEvaluatorInterface $inner)
    {
    }

    public function fields(Criteria $criteria): array
    {
        return $this->inner->fields($criteria);
    }

    public function filter(array $rows, Criteria $criteria): array
    {
        ++$this->filterCalls;

        return $this->inner->filter($rows, $criteria);
    }
}
