<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository;

use Derafu\Config\Configuration;
use Derafu\Repository\Contract\DataSourceInterface;
use Derafu\Repository\Exception\DataProviderException;
use Derafu\Repository\Service\DataProvider;
use Derafu\Repository\Service\DataSource\DataSourceResolverRegistry;
use Derafu\Repository\Translation\RepositoryTranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider as TestDataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use stdClass;
use Throwable;

#[CoversClass(DataProvider::class)]
#[UsesClass(DataSourceResolverRegistry::class)]
#[UsesClass(DataProviderException::class)]
#[UsesClass(RepositoryTranslationResourceProvider::class)]
final class DataProviderValidationTest extends TestCase
{
    private const string CACHE_KEY = 'derafu:repository:data_provider:items';

    /** @return array<string, array{mixed}> */
    public static function invalidCacheEntries(): array
    {
        return [
            'miss or expired entry' => [null],
            'false' => [false],
            'true' => [true],
            'zero' => [0],
            'integer' => [7],
            'string' => ['invalid cache entry'],
            'object' => [new stdClass()],
        ];
    }

    #[TestDataProvider('invalidCacheEntries')]
    public function testInvalidOrExpiredCacheEntriesReloadAndReplaceTheRawData(mixed $cached): void
    {
        $raw = ['first' => 'Alpha', 7 => ['name' => 'Beta']];
        $source = new CountingRepositorySource($raw);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('has');
        $cache->expects($this->once())->method('get')->with(self::CACHE_KEY)->willReturn($cached);
        $cache->expects($this->once())->method('set')->with(self::CACHE_KEY, $raw)->willReturn(true);
        $provider = $this->createProvider($source, $cache);

        $data = $provider->fetch('items');
        $this->assertSame([
            'first' => ['id' => 'first', 'name' => 'Alpha'],
            7 => ['id' => 7, 'name' => 'Beta'],
        ], $data->getArrayCopy());
        $this->assertSame($data, $provider->fetch('items'));
        $this->assertSame(1, $source->reads);
    }

    /** @return array<string, array{array<array-key, mixed>, array<array-key, mixed>}> */
    public static function validCacheEntries(): array
    {
        return [
            'empty array is a hit' => [[], []],
            'cached scalar values are normalized' => [['a' => 'Cached'], ['a' => ['id' => 'a', 'name' => 'Cached']]],
            'cached rows keep their own ID' => [[7 => ['id' => 'own', 'name' => 'Cached']], [7 => ['id' => 'own', 'name' => 'Cached']]],
        ];
    }

    /**
     * @param array<array-key, mixed> $cached
     * @param array<array-key, mixed> $expected
     */
    #[TestDataProvider('validCacheEntries')]
    public function testValidCacheEntriesDoNotReadOrOverwriteTheSource(array $cached, array $expected): void
    {
        $source = new CountingRepositorySource(['should' => 'not be read']);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('has');
        $cache->expects($this->once())->method('get')->with(self::CACHE_KEY)->willReturn($cached);
        $cache->expects($this->never())->method('set');
        $provider = $this->createProvider($source, $cache);

        $data = $provider->fetch('items');
        $this->assertSame($expected, $data->getArrayCopy());
        $this->assertSame($data, $provider->fetch('items'));
        $this->assertSame(0, $source->reads);
    }

    public function testCacheBackendErrorsArePropagatedWithoutReadingTheSource(): void
    {
        $failure = new RuntimeException('Cache backend unavailable.');
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('get')->willThrowException($failure);
        $cache->expects($this->never())->method('set');
        $source = new CountingRepositorySource([]);
        $provider = $this->createProvider($source, $cache);
        try {
            $provider->fetch('items');
            $this->fail('A cache backend error must be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
            $this->assertSame(0, $source->reads);
        }
    }

    public function testFailedReloadDoesNotOverwriteAnInvalidCacheEntry(): void
    {
        $failure = new DataProviderException('The source is unavailable.');
        $source = new CountingRepositorySource([], $failure);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('get')->willReturn('invalid');
        $cache->expects($this->never())->method('set');
        $provider = $this->createProvider($source, $cache);
        try {
            $provider->fetch('items');
            $this->fail('An unavailable source cannot repair a cache entry.');
        } catch (DataProviderException $exception) {
            $this->assertSame($failure, $exception);
            $this->assertSame(1, $source->reads);
        }
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidNormalizationAttributes(): array
    {
        $cases = [];
        foreach (['nameAttribute', 'idAttribute'] as $attribute) {
            foreach ([null, false, 7, new stdClass()] as $value) {
                $cases[$attribute . ': ' . get_debug_type($value)] = [$attribute, $value];
            }
        }

        return $cases;
    }

    #[TestDataProvider('invalidNormalizationAttributes')]
    public function testSuppliedConfigurationObjectsMustProvideStringAttributes(string $attribute, mixed $value): void
    {
        $source = new CountingRepositorySource(['a' => 'Alpha']);
        $provider = $this->createProvider($source);
        $settings = ['nameAttribute' => 'name', 'idAttribute' => 'id'];
        $settings[$attribute] = $value;
        $provider->setConfiguration(new Configuration(['normalization' => $settings]));
        try {
            $provider->fetch('items');
            $this->fail('Normalization attributes must be strings.');
        } catch (DataProviderException $exception) {
            $type = get_debug_type($value);
            $this->assertSame('Normalization setting ' . $attribute . ' must be a string; ' . $type . ' given.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new RepositoryTranslationResourceProvider()]);
            $this->assertSame('La opción de normalización ' . $attribute . ' debe ser string; se recibió ' . $type . '.', $exception->trans($translator));
        }
    }

    /** @return array<string, array{string, string}> */
    public static function normalizationNames(): array
    {
        return [
            'custom names' => ['title', 'code'],
            'empty name attribute remains valid' => ['', 'code'],
            'empty ID attribute remains valid' => ['title', ''],
        ];
    }

    #[TestDataProvider('normalizationNames')]
    public function testCustomAttributeNamesPreserveKeysAndValues(string $name, string $id): void
    {
        $provider = $this->createProvider(new CountingRepositorySource(['a' => 'Alpha', 7 => null]));
        $provider->setConfiguration(['normalization' => ['nameAttribute' => $name, 'idAttribute' => $id]]);
        $this->assertSame([
            'a' => [$id => 'a', $name => 'Alpha'],
            7 => [$id => 7, $name => null],
        ], $provider->fetch('items')->getArrayCopy());
    }

    private function createProvider(CountingRepositorySource $source, ?CacheInterface $cache = null): DataProvider
    {
        return new DataProvider(['items' => $source], $cache, sourceResolvers: new DataSourceResolverRegistry([]));
    }
}

final class CountingRepositorySource implements DataSourceInterface
{
    public int $reads = 0;

    /** @param array<array-key, mixed> $data */
    public function __construct(private readonly array $data, private readonly ?Throwable $failure = null)
    {
    }

    public function read(): array
    {
        $this->reads++;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->data;
    }
}
