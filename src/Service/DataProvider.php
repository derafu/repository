<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Service;

use ArrayObject;
use Derafu\Array\ArrayTable;
use Derafu\Config\Trait\ConfigurableTrait;
use Derafu\Repository\Contract\DataProviderInterface;
use Derafu\Repository\Contract\DataSourceInterface;
use Derafu\Repository\Contract\DataSourceResolverRegistryInterface;
use Derafu\Repository\Contract\RepositorySourceProviderInterface;
use Derafu\Repository\Exception\DataProviderException;
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
     * @var array<string, array<string, mixed>>
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
     * A source can be provided either as a raw string, resolved through the
     * source resolver registry (a file path or a scheme-prefixed string like
     * `enum:App\Enum\Status`), or as a DataSourceInterface instance for any
     * other kind of origin (e.g. a PDO connection).
     *
     * @var array<string, string|DataSourceInterface>
     */
    private array $sources = [];

    /**
     * Instance to access a cache to search for data.
     *
     * @var CacheInterface|null
     */
    private ?CacheInterface $cache;

    /**
     * Registry used to resolve the data source of string sources.
     *
     * @var DataSourceResolverRegistryInterface
     */
    private DataSourceResolverRegistryInterface $sourceResolvers;

    /**
     * In-memory data sources that have already had their data loaded.
     *
     * @var array<string, ArrayObject<array-key, mixed>>
     */
    private array $loaded;

    /**
     * @param iterable<RepositorySourceProviderInterface> $sourceProviders
     * @param array<array-key, mixed> $config
     */
    public function __construct(
        iterable $sourceProviders,
        DataSourceResolverRegistryInterface $sourceResolvers,
        ?CacheInterface $cache = null,
        array $config = [],
    ) {
        $this->cache = $cache;
        $this->sourceResolvers = $sourceResolvers;
        foreach ($sourceProviders as $provider) {
            foreach ($provider->getSources() as $identifier => $source) {
                if (array_key_exists($identifier, $this->sources)) {
                    throw new DataProviderException([
                        'Repository source "{source}" is registered more than once.',
                        'source' => $identifier,
                    ]);
                }
                $this->sources[$identifier] = $source;
            }
        }
        if ($config !== []) {
            $this->setConfiguration($config);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigurationSchema(): array
    {
        return $this->configurationSchema;
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

            $configuration = $this->getConfiguration();
            $normalizationConfig = [
                'nameAttribute' => $this->requireNormalizationAttribute(
                    $configuration->get('normalization.nameAttribute'),
                    'nameAttribute'
                ),
                'idAttribute' => $this->requireNormalizationAttribute(
                    $configuration->get('normalization.idAttribute'),
                    'idAttribute'
                ),
            ];
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
     * @return array<array-key, mixed>
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
            throw new DataProviderException([
                'No data source configured for {source}.',
                'source' => $source,
            ]);
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
     * @return array<array-key, mixed>|null
     */
    private function fetchDataFromCacheSource(string $source): ?array
    {
        $key = $this->createCacheKey($source);

        if (isset($this->cache)) {
            $data = $this->cache->get($key);

            return is_array($data) ? $data : null;
        }

        return null;
    }

    /**
     * Resolves the data source instance that must be used to read a source.
     *
     * A string source is resolved through the source resolver registry. Any
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

        return $this->sourceResolvers->getResolver($source)->resolve($source);
    }

    /**
     * Normalizes data in case it's an array of values and not an array of
     * arrays.
     *
     * @param array<array-key, mixed> $data
     * @param array{nameAttribute: string, idAttribute: string} $config
     * @return array<int|string, array<array-key, mixed>>
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

        return ArrayTable::withKeyColumn($data, $config['idAttribute']);
    }

    /**
     * Validates a configured normalization attribute without converting it.
     */
    private function requireNormalizationAttribute(mixed $value, string $attribute): string
    {
        if (!is_string($value)) {
            throw new DataProviderException([
                'Normalization setting {attribute} must be a string; {type} given.',
                'attribute' => $attribute,
                'type' => get_debug_type($value),
            ]);
        }

        return $value;
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
