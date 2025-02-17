<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight File Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Contract;

use Derafu\Config\Contract\ConfigurableInterface;
use Derafu\Repository\Exception\ManagerException;

/**
 * Interfaz para el administrador de entidades.
 */
interface ManagerInterface extends ConfigurableInterface
{
    /**
     * Entrega el repositorio asociado a una clase de entidad o identificador
     * del repositorio.
     *
     * @param string $repository Clase de entidad o identificador repositorio.
     * @return RepositoryInterface Repositorio solicitado.
     * @throws ManagerException
     */
    public function getRepository(string $repository): RepositoryInterface;
}
