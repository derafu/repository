<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight File Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Contract;

use ArrayObject;
use Derafu\Config\Contract\ConfigurableInterface;
use Derafu\Repository\Exception\DataProviderException;

/**
 * Interfaz para el proveedor de datos del repositorio.
 */
interface DataProviderInterface extends ConfigurableInterface
{
    /**
     * Busca y entrega los datos en un origen de datos.
     *
     * Los datos se entregan como un ArrayObject para ser compartidos entre los
     * diferentes consumidores de datos que podrían requerir el mismo origen.
     *
     * @param string $source Identificador del origen solicitado.
     * @return ArrayObject
     * @throws DataProviderException
     */
    public function fetch(string $source): ArrayObject;
}
