<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight File Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository;

use Derafu\Repository\Contract\EntityInterface;
use Derafu\Repository\Exception\EntityException;

/**
 * Clase genérica para el manejo de entidades de repositorios.
 *
 * Esta clase es útil cuando no se desea crear explícitamente cada clase de cada
 * entidad. Sin embargo, es desaconsejado su uso y se recomienda crear clases
 * para cada entidad que se requiera.
 */
class Entity implements EntityInterface
{
    /**
     * Atributos de la entidad.
     *
     * @var array
     */
    private array $attributes = [];

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return static::class . '@' . spl_object_id($this);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritDoc}
     */
    public function setAttribute(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAttribute(string $name): mixed
    {
        if (!$this->hasAttribute($name)) {
            throw new EntityException(sprintf(
                'No existe el atributo %s en la entidad %s.',
                $name,
                static::class
            ));
        }

        return $this->attributes[$name];
    }

    /**
     * {@inheritDoc}
     */
    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * {@inheritDoc}
     */
    public function unsetAttribute(string $name): void
    {
        unset($this->attributes[$name]);
    }

    /**
     * Método mágico para asignar el valor de un atributo como si estuviese
     * definido en la clase.
     *
     * Se ejecuta al escribir datos sobre propiedades inaccesibles (protegidas
     * o privadas) o inexistentes.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    /**
     * Método mágico para obtener el valor de un atributo como si estuviese
     * definido en la clase.
     *
     * Se utiliza para consultar datos a partir de propiedades inaccesibles
     * (protegidas o privadas) o inexistentes.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    /**
     * Método mágico para saber si un atributo existe, y tiene valor, como si
     * estuviese definido en la clase.
     *
     * Se lanza al llamar a isset() o a empty() sobre propiedades inaccesibles
     * (protegidas o privadas) o inexistentes.
     *
     * @param string $name
     * @return boolean
     */
    public function __isset(string $name): bool
    {
        return $this->hasAttribute($name);
    }

    /**
     * Método mágico para desasignar el valor de un atributo como si estuviese
     * definido en la clase.
     *
     * Se invoca cuando se usa unset() sobre propiedades inaccesibles
     * (protegidas o privadas) o inexistentes.
     *
     * @param string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        $this->setAttribute($name, null);
    }

    /**
     * Es lanzado al invocar un método inaccesible en un contexto de objeto.
     *
     * Específicamente procesa las llamadas a los "accessors" ("getters") y
     * "mutators" ("setters").
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        // Si es un "accessor" getXyz() se procesa.
        $pattern = '/^get([A-Z][a-zA-Z0-9]*)$/';
        if (preg_match($pattern, $name, $matches)) {
            return $this->getAttribute(lcfirst($matches[1]));
        }

        // Si es un "mutator" setXyz() se procesa.
        $pattern = '/^set([A-Z][a-zA-Z0-9]*)$/';
        if (preg_match($pattern, $name, $matches)) {
            return $this->setAttribute(lcfirst($matches[1]), ...$arguments);
        }

        // Si el método no existe se genera una excepción.
        throw new EntityException(sprintf(
            'El método %s::%s() no existe.',
            get_debug_type($this),
            $name,
        ));
    }

    /**
     * Es lanzado al invocar un método inaccesible en un contexto estático.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        // Si el método no existe se genera una excepción.
        throw new EntityException(sprintf(
            'El método %s::%s() no existe.',
            static::class,
            $name,
        ));
    }
}
