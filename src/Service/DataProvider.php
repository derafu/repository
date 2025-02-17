<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight File Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Worker;

use ArrayObject;
use Derafu\Config\Trait\ConfigurableTrait;
use Derafu\Repository\Contract\DataProviderInterface;
use Derafu\Repository\Exception\DataProviderException;
use Derafu\Support\Arr;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Proveedor de datos.
 */
class DataProvider implements DataProviderInterface
{
    use ConfigurableTrait;

    /**
     * Esquema de configuración del worker.
     *
     * @var array
     */
    protected array $configurationSchema = [
        'normalization' => [
            'types' => 'array',
            'default' => [],
            'schema' => [
                'idAttribute' => [
                    'types' => 'string',
                    'default' => 'id',
                ],
                'nameAttribute' => [
                    'types' => 'string',
                    'default' => 'name',
                ],
            ],
        ],
    ];

    /**
     * Listado de fuentes de datos de repositorios de entidades.
     *
     * Es una mapa que contiene en el índice la clase de la entidad asociada al
     * origen y en el valor el origen.
     *
     * Si el índice no es una clase válida se mapeará a una clase de entidad
     * estándar por defecto.
     *
     * La entidad puede proporcionar un repositorio personalizado para su
     * gestión.
     *
     * Un mismo origen (valor) pueden estar en diferentes entidades (índice).
     *
     * @var array<string, string>
     */
    private array $sources;

    /**
     * Instancia para acceder a una caché a buscar los datos.
     *
     * @var CacheInterface|null
     */
    private ?CacheInterface $cache;

    /**
     * Orígenes de datos en memoria que ya han sido cargado sus datos.
     *
     * @var array<string,ArrayObject>
     */
    private array $loaded;

    /**
     * Constructor del worker.
     *
     * @param array<string,string> $sources Origenes de datos (ID y origen).
     */
    public function __construct(array $sources = [], ?CacheInterface $cache = null)
    {
        $this->sources = $sources;
        $this->cache = $cache;
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(string $source): ArrayObject
    {
        // Si el origen no está cargado se carga.
        if (!isset($this->loaded[$source])) {
            $data = $this->fetchData($source);

            $normalizationConfig = $this->getConfiguration()->get(
                'normalization'
            );
            $data = $this->normalizeData($data, $normalizationConfig);

            $this->loaded[$source] = new ArrayObject($data);
        }

        // Entregar los datos cargados del origen.
        return $this->loaded[$source];
    }

    /**
     * Centraliza la carga de datos para un origen.
     *
     * Esto permite cargar los datos desde una caché, archivos o en el futuro
     * otros orígenes donde puedan estar los datos.
     *
     * @param string $source
     * @return array
     */
    private function fetchData(string $source): array
    {
        // Cargar los datos del origen desde una caché.
        $data = $this->fetchDataFromCacheSource($source);
        if ($data !== null) {
            return $data;
        }

        // Si no hay fuente de datos para el origen se genera un error.
        if (!isset($this->sources[$source])) {
            throw new DataProviderException(sprintf(
                'No existe un origen de datos configurado para de %s.',
                $source
            ));
        }

        // Cargar los datos del origen desde un archivo.
        $data = $this->fetchDataFromFileSource($source);

        // Guardar los datos en caché.
        if (isset($this->cache)) {
            $key = $this->createCacheKey($source);
            $this->cache->set($key, $data);
        }

        // Entregar los datos encontrados.
        return $data;
    }

    /**
     * Carga los datos del origen desde una caché (si está disponible).
     *
     * @param string $source
     * @return array|null
     */
    private function fetchDataFromCacheSource(string $source): ?array
    {
        $key = $this->createCacheKey($source);

        if (isset($this->cache) && $this->cache->has($key)) {
            return $this->cache->get($key);
        }

        return null;
    }

    /**
     * Carga los datos del origen desde un archivo.
     *
     * El archivo puede ser: .php, .json o .yaml
     *
     * @param string $source
     * @return array
     */
    private function fetchDataFromFileSource(string $source): array
    {
        $filepath = $this->sources[$source];

        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'php':
                $data = require $filepath;
                break;
            case 'json':
                $data = json_decode(file_get_contents($filepath), true);
                break;
            case 'yaml':
                $data = Yaml::parseFile(file_get_contents($filepath));
                break;
            default:
                $data = $this->handleExtension($source, $filepath, $extension);
        }

        if (!is_array($data)) {
            throw new DataProviderException(sprintf(
                'Los datos del origen %s no son válidos para ser usados como origen de datos. Ruta: %s.',
                $source,
                $filepath
            ));
        }

        return $data;
    }

    /**
     * Maneja el caso cuando una extensión de archivo no es soportada.
     *
     * Funciona como "hook" para personalizar mediante herencia el
     * comportamiento.
     *
     * @param string $source
     * @param string $filepath
     * @param string $extension
     * @return array
     */
    protected function handleExtension(
        string $source,
        string $filepath,
        string $extension
    ): array {
        throw new DataProviderException(sprintf(
            'Formato de archivo %s del origen de datos %s no es soportado. Ruta: %s.',
            $extension,
            $source,
            $filepath
        ));
    }

    /**
     * Normaliza los datos en caso que sea un arreglo de valores y no un arreglo
     * de arreglos.
     *
     * @param array $data
     * @return array<int|string, array>
     */
    private function normalizeData(array $data, array $config): array
    {
        $nameAttribute = $config['nameAttribute'];

        $data = array_map(function ($entity) use ($nameAttribute) {
            if (!is_array($entity)) {
                return [
                    $nameAttribute => $entity,
                ];
            }
            return $entity;
        }, $data);

        return Arr::ensureIdInElements($data, $config['idAttribute']);
    }

    /**
     * Crea la llave a partir del identificador del origen de datos.
     *
     * @param string $source Identificador del origen de datos.
     * @return string Llave para utilizar con la caché.
     */
    private function createCacheKey(string $source): string
    {
        return 'derafu:repository:data_provider:' . $source;
    }
}
