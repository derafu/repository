<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Service\DataSource;

use BackedEnum;
use Derafu\Repository\Contract\DataSourceInterface;
use Derafu\Repository\Exception\DataProviderException;

/**
 * Data source that reads data from the cases of a backed enum.
 *
 * The enum does not need to know about this package in any way: it only
 * needs to be a backed enum. Each case is exposed as `value => name`, the
 * same shape a plain list of values already has, so DataProvider normalizes
 * it exactly like any other source (respecting the configured `idAttribute`
 * and `nameAttribute`).
 */
final class EnumDataSource implements DataSourceInterface
{
    /**
     * Data source constructor.
     *
     * @param string $enumClass Backed enum class to read cases from. It is
     * validated (and rejected with a clear exception) when read, so it is not
     * typed as `class-string`.
     */
    public function __construct(
        private readonly string $enumClass,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws DataProviderException
     */
    public function read(): array
    {
        $this->assertIsBackedEnum();

        $data = [];
        foreach (($this->enumClass)::cases() as $case) {
            $data[$case->value] = $case->name;
        }

        return $data;
    }

    /**
     * Ensures the configured enum class is a backed enum.
     *
     * @return void
     * @throws DataProviderException
     */
    private function assertIsBackedEnum(): void
    {
        if (!enum_exists($this->enumClass) || !is_a($this->enumClass, BackedEnum::class, true)) {
            throw new DataProviderException(sprintf(
                '%s must be a backed enum to be used as an enum data source.',
                $this->enumClass
            ));
        }
    }
}
