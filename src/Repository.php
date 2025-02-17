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

use ArrayAccess;
use ArrayObject;
use Derafu\Container\Abstract\AbstractContainer;
use Derafu\Repository\Contract\RepositoryInterface;
use Derafu\Support\Arr;
use Derafu\Support\Factory;
use Doctrine\Common\Collections\Criteria;
use InvalidArgumentException;
use stdClass;

/**
 * Clase para repositorios de objetos/entidades.
 *
 * Proporciona métodos estándar para acceder y buscar objetos/entidades desde
 * una fuente de datos.
 */
class Repository extends AbstractContainer implements RepositoryInterface
{
    /**
     * Clase de la entidad donde se colocarán los datos que se obtengan a través
     * del repositorio.
     *
     * @var string
     */
    protected string $entityClass = stdClass::class;

    /**
     * Constructor del repositorio.
     *
     * @param string|array|ArrayAccess|ArrayObject $source Arreglo de datos o
     * ruta al archivo PHP.
     * @param string|null $entityClass Clase de la entidad asociada al repositorio.
     * @param string|null $idAttribute Nombre del atributo ID que se debe
     * asegurar que exista en los elementos cargados del repositorio.
     */
    public function __construct(
        string|array|ArrayAccess|ArrayObject $source,
        ?string $entityClass = null,
        ?string $idAttribute = null
    ) {
        if ($entityClass !== null) {
            $this->entityClass = $entityClass;
        }

        $this->load($source, $idAttribute);
    }

    /**
     * Carga los datos del repositorio.
     *
     * @param string|array|ArrayAccess|ArrayObject $source
     * @param string|null $idAttribute
     * @return void
     */
    protected function load(
        string|array|ArrayAccess|ArrayObject $source,
        ?string $idAttribute = null
    ): void {
        $data = is_string($source) ? require $source : $source;
        if (!is_array($data)) {
            $data = $this->createFrom($data)->toArray();
        }
        if ($idAttribute && is_array($data)) {
            $data = Arr::ensureIdInElements($source, $idAttribute);
        }
        $this->data = $this->createFrom($data);
    }

    /**
     * {@inheritDoc}
     */
    public function find($id, $lockMode = null, $lockVersion = null): ?object
    {
        if (!is_string($id) && !is_int($id)) {
            throw new InvalidArgumentException(sprintf(
                'En el método %s:find($id) se pasó un $id de tipo %s y solo se permiten string e int.',
                static::class,
                get_debug_type($id)
            ));
        }

        return isset($this->data[$id])
            ? $this->createEntity($this->data[$id])
            : null
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(): array
    {
        return array_values($this->data->map(
            fn ($item) => $this->createEntity($item)
        )->toArray());
    }

    /**
     * {@inheritDoc}
     */
    public function findBy(
        array $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $results = $this->data->filter(
            fn ($item) => $this->matchCriteria($item, $criteria)
        )->toArray();

        if ($orderBy) {
            $results = $this->applyOrderBy($results, $orderBy);
        }

        if ($offset !== null || $limit !== null) {
            $offset = $offset ?: 0;
            if ($offset < 0) {
                throw new InvalidArgumentException('Offset no puede ser negativo.');
            }

            if ($limit !== null && $limit < 0) {
                throw new InvalidArgumentException('Limit no puede ser negativo.');
            }

            $results = array_slice($results, $offset, $limit);
        }

        return array_values(array_map(
            fn ($item) => $this->createEntity($item),
            $results
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        $results = $this->findBy($criteria, $orderBy, 1);

        return empty($results) ? null : reset($results);
    }

    /**
     * {@inheritDoc}
     */
    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            return count($this->data);
        }

        $results = $this->data->filter(
            fn ($item) => $this->matchCriteria($item, $criteria)
        );

        return $results->count();
    }

    /**
     * {@inheritDoc}
     */
    public function findByCriteria(Criteria $criteria): array
    {
        return parent::matching($criteria)->map(
            fn ($item) => $this->createEntity($item)
        )->getValues();
    }

    /**
     * {@inheritDoc}
     */
    public function getClassName(): string
    {
        return $this->entityClass;
    }

    /**
     * Verifica si un item cumple con los criterios de búsqueda.
     */
    protected function matchCriteria(array $item, array $criteria): bool
    {
        foreach ($criteria as $field => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }
            if (!isset($item[$field]) || !in_array($item[$field], $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ordena los resultados según los criterios especificados.
     *
     * @param array $results Resultados a ordenar.
     * @param array $orderBy Criterios de ordenamiento ['campo' => 'ASC|DESC'].
     * @return array Resultados ordenados.
     */
    protected function applyOrderBy(array $results, array $orderBy): array
    {
        uasort($results, function ($a, $b) use ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                if (!isset($a[$field]) || !isset($b[$field])) {
                    continue;
                }

                $compare = $direction === 'DESC'
                    ? -1 * ($a[$field] <=> $b[$field])
                    : $a[$field] <=> $b[$field];

                if ($compare !== 0) {
                    return $compare;
                }
            }
            return 0;
        });

        return $results;
    }

    /**
     * Crea una entidad a partir de los datos.
     *
     * @param array $data Datos que se asignarán a la entidad.
     * @return object Instancia de la entidad con los datos cargados.
     */
    protected function createEntity(array $data): object
    {
        return Factory::create($data, $this->entityClass);
    }
}
