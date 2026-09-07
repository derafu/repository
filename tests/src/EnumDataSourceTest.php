<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository;

use Derafu\Repository\Contract\DataSourceResolverInterface;
use Derafu\Repository\Exception\DataProviderException;
use Derafu\Repository\Service\DataSource\DataSourceResolverRegistry;
use Derafu\Repository\Service\DataSource\EnumDataSource;
use Derafu\Repository\Service\DataSource\EnumDataSourceResolver;
use Derafu\Repository\Service\DataSource\FileDataSource;
use Derafu\Repository\Service\DataSource\FileDataSourceResolver;
use Derafu\Repository\Service\DataSource\FileFormat\FileFormatReaderRegistry;
use Derafu\TestsRepository\Fixture\PureStatus;
use Derafu\TestsRepository\Fixture\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnumDataSource::class)]
#[CoversClass(EnumDataSourceResolver::class)]
#[CoversClass(FileDataSourceResolver::class)]
#[CoversClass(DataSourceResolverRegistry::class)]
#[CoversClass(FileDataSource::class)]
#[CoversClass(FileFormatReaderRegistry::class)]
class EnumDataSourceTest extends TestCase
{
    public function testEnumDataSourceReadsBackedEnumCases(): void
    {
        $source = new EnumDataSource(Status::class);

        $this->assertSame(
            [
                'active' => 'Active',
                'inactive' => 'Inactive',
            ],
            $source->read()
        );
    }

    public function testEnumDataSourceThrowsForNonBackedEnum(): void
    {
        $this->expectException(DataProviderException::class);

        (new EnumDataSource(PureStatus::class))->read();
    }

    public function testEnumDataSourceThrowsForNonExistentClass(): void
    {
        $this->expectException(DataProviderException::class);

        (new EnumDataSource('Not\A\Real\Enum'))->read();
    }

    public function testEnumDataSourceResolverSupportsEnumPrefix(): void
    {
        $resolver = new EnumDataSourceResolver();

        $this->assertTrue($resolver->supports('enum:' . Status::class));
        $this->assertFalse($resolver->supports('/some/file.php'));
    }

    public function testEnumDataSourceResolverResolvesToEnumDataSource(): void
    {
        $resolver = new EnumDataSourceResolver();

        $source = $resolver->resolve('enum:' . Status::class);

        $this->assertInstanceOf(EnumDataSource::class, $source);
        $this->assertSame(
            ['active' => 'Active', 'inactive' => 'Inactive'],
            $source->read()
        );
    }

    public function testFileDataSourceResolverSupportsAnySource(): void
    {
        $resolver = new FileDataSourceResolver(new FileFormatReaderRegistry());

        $this->assertTrue($resolver->supports('anything'));
    }

    public function testFileDataSourceResolverResolvesToFileDataSource(): void
    {
        $resolver = new FileDataSourceResolver(new FileFormatReaderRegistry());

        $source = $resolver->resolve(
            dirname(__DIR__) . '/fixtures/file-formats/valid.php'
        );

        $this->assertInstanceOf(FileDataSource::class, $source);
    }

    public function testRegistryPrefersEnumResolverOverFileCatchAll(): void
    {
        $registry = new DataSourceResolverRegistry();

        $resolver = $registry->getResolver('enum:' . Status::class);

        $this->assertInstanceOf(EnumDataSourceResolver::class, $resolver);
    }

    public function testRegistryFallsBackToFileResolver(): void
    {
        $registry = new DataSourceResolverRegistry();

        $resolver = $registry->getResolver(
            dirname(__DIR__) . '/fixtures/file-formats/valid.php'
        );

        $this->assertInstanceOf(FileDataSourceResolver::class, $resolver);
    }

    public function testRegistryAcceptsAnExplicitListOfResolvers(): void
    {
        $registry = new DataSourceResolverRegistry([new EnumDataSourceResolver()]);

        $this->expectException(DataProviderException::class);

        $registry->getResolver('/some/file.php');
    }

    public function testRegistrySupportsCustomResolvers(): void
    {
        $customResolver = new class () implements DataSourceResolverInterface {
            public function supports(string $source): bool
            {
                return $source === 'custom';
            }

            public function resolve(string $source): EnumDataSource
            {
                return new EnumDataSource(Status::class);
            }
        };

        $registry = new DataSourceResolverRegistry([]);
        $registry->register($customResolver);

        $this->assertSame($customResolver, $registry->getResolver('custom'));
    }
}
