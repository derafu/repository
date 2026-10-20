<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository\Fixture\Factory\Entity;

use Derafu\Repository\Entity;
use Derafu\Repository\Mapping as DEM;

#[DEM\Entity]
class DefaultMapped extends Entity
{
}
