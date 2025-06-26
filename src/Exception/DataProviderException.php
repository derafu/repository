<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight File Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Exception;

use Derafu\Translation\Exception\Core\TranslatableLogicException;

/**
 * Excepción personalizada para proveedores de datos.
 */
class DataProviderException extends TranslatableLogicException
{
}
