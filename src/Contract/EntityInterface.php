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

use Stringable;

/**
 * Interfaz para las entidades de repositorios.
 */
interface EntityInterface extends Stringable
{
    /**
     * Entrega las propiedades de la entidad como un arreglo.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Asignar un atributo a la entidad.
     *
     * @param string $name
     * @param mixed $value
     * @return static
     */
    public function setAttribute(string $name, mixed $value): static;

    /**
     * Obtener un atributo de la entidad.
     *
     * @param string $name
     * @return mixed
     */
    public function getAttribute(string $name): mixed;

    /**
     * Permite saber si existe o no un atributo definido para la entidad.
     *
     * @return bool
     */
    public function hasAttribute(string $name): bool;

    /**
     * Permite desasignar el valor de un atributo de la entidad.
     *
     * @param string $name
     * @return void
     */
    public function unsetAttribute(string $name): void;
}
