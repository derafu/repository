<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight File Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Service;

use ArrayObject;
use Derafu\Config\Trait\ConfigurableTrait;
use Derafu\Repository\Contract\DataProviderInterface;
use Derafu\Repository\Contract\DataSourceInterface;
use Derafu\Repository\Exception\DataProviderException;
use Derafu\Repository\Service\DataSource\FileDataSource;
use Derafu\Repository\Service\DataSource\FileFormat\FileFormatReaderRegistry;
use Derafu\Support\Arr;
use Psr\SimpleCache\CacheInterface;

/**
 * Data provider.
 */
class DataProvider implements DataProviderInterface
{
    use ConfigurableTrait;

    /**
     * Worker configuration schema.
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
     * List of entity repository data sources.
     *
     * It's a map that contains in the index the entity class associated with
     * the source and in the value the source.
     *
     * If the index is not a valid class it will be mapped to a default
     * standard entity class.
     *
     * The entity can provide a custom repository for its management.
     *
     * The same source (value) can be in different entities (index).
     *
     * A source can be provided either as a file path (string), resolved
     * through FileDataSource, or as a DataSourceInterface instance for any
     * other kind of origin (e.g. a PDO connection or an enum).
     *
     * @var array<string, string|DataSourceInterface>
     */
    private array $sources;

    /**
     * Instance to access a cache to search for data.
     *
     * @var CacheInterface|null
     */
    private ?CacheInterface $cache;

    /**
     * Registry used to resolve the file format reader of string sources.
     *
     * @var FileFormatReaderRegistry
     */
    private FileFormatReaderRegistry $fileFormatReaders;

    /**
     * In-memory data sources that have already had their data loaded.
     *
     * @var array<string,ArrayObject>
     */
    private array $loaded;

    /**
     * Worker constructor.
     *
     * @param array<string,string|DataSourceInterface> $sources Data sources
     * (ID and source).
     * @param CacheInterface|null $cache Cache instance.
     * @param array $config Configuration.
     * @param FileFormatReaderRegistry|null $fileFormatReaders Registry used
     * to resolve the file format reader of string sources. When `null`, a
     * registry with the built-in readers (PHP, JSON, YAML) is used.
     */
    public function __construct(
        array $sources = [],
        ?CacheInterface $cache = null,
        array $config = [],
        ?FileFormatReaderRegistry $fileFormatReaders = null
    ) {
        $this->sources = $sources;
        $this->cache = $cache;
        $this->fileFormatReaders = $fileFormatReaders ?? new FileFormatReaderRegistry();
        if (!empty($config)) {
            $this->setConfiguration($config);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSources(): array
    {
        return array_keys($this->sources);
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(string $source): ArrayObject
    {
        // If the source is not loaded it's loaded.
        if (!isset($this->loaded[$source])) {
            $data = $this->fetchData($source);

            $normalizationConfig = $this->getConfiguration()->get(
                'normalization'
            )->all();
            $data = $this->normalizeData($data, $normalizationConfig);

            $this->loaded[$source] = new ArrayObject($data);
        }

        // Return loaded data from the source.
        return $this->loaded[$source];
    }

    /**
     * Centralizes data loading for a source.
     *
     * This allows loading data from a cache, files or in the future other
     * sources where data might be located.
     *
     * @param string $source
     * @return array
     */
    private function fetchData(string $source): array
    {
        // Load source data from a cache.
        $data = $this->fetchDataFromCacheSource($source);
        if ($data !== null) {
            return $data;
        }

        // If there's no data source for the source an error is generated.
        if (!isset($this->sources[$source])) {
            throw new DataProviderException(sprintf(
                'No data source configured for %s.',
                $source
            ));
        }

        // Load source data through its data source.
        $data = $this->resolveDataSource($this->sources[$source])->read();

        // Save data in cache.
        if (isset($this->cache)) {
            $key = $this->createCacheKey($source);
            $this->cache->set($key, $data);
        }

        // Return found data.
        return $data;
    }

    /**
     * Loads source data from a cache (if available).
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
     * Resolves the data source instance that must be used to read a source.
     *
     * A string source is resolved as a file path through FileDataSource. Any
     * other source must already be a DataSourceInterface instance.
     *
     * @param string|DataSourceInterface $source
     * @return DataSourceInterface
     */
    private function resolveDataSource(string|DataSourceInterface $source): DataSourceInterface
    {
        if ($source instanceof DataSourceInterface) {
            return $source;
        }

        return new FileDataSource($source, $this->fileFormatReaders);
    }

    /**
     * Normalizes data in case it's an array of values and not an array of
     * arrays.
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
     * Creates the key from the data source identifier.
     *
     * @param string $source Data source identifier.
     * @return string Key to use with the cache.
     */
    private function createCacheKey(string $source): string
    {
        return 'derafu:repository:data_provider:' . $source;
    }
}
