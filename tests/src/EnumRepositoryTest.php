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

use Derafu\Repository\Exception\EntityException;
use Derafu\Repository\Repository;
use Derafu\Repository\Service\DataProvider;
use Derafu\Repository\Service\DataSource\DataSourceResolverRegistry;
use Derafu\Repository\Service\DataSource\EnumDataSource;
use Derafu\Repository\Service\DataSource\EnumDataSourceResolver;
use Derafu\Repository\Service\DataSource\FileDataSourceResolver;
use Derafu\Repository\Service\DataSource\FileFormat\FileFormatReaderRegistry;
use Derafu\Repository\Service\RepositoryManager;
use Derafu\TestsRepository\Fixture\PureStatus;
use Derafu\TestsRepository\Fixture\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Repository::class)]
#[CoversClass(DataProvider::class)]
#[CoversClass(RepositoryManager::class)]
#[CoversClass(DataSourceResolverRegistry::class)]
#[CoversClass(EnumDataSource::class)]
#[CoversClass(EnumDataSourceResolver::class)]
#[CoversClass(FileDataSourceResolver::class)]
#[CoversClass(FileFormatReaderRegistry::class)]
class EnumRepositoryTest extends TestCase
{
    public function testFindReturnsTheActualEnumCaseInstance(): void
    {
        $repository = new Repository(
            [
                'active' => ['id' => 'active', 'name' => 'Active'],
                'inactive' => ['id' => 'inactive', 'name' => 'Inactive'],
            ],
            Status::class,
            idAttribute: 'id'
        );

        $this->assertSame(Status::Active, $repository->find('active'));
        $this->assertSame(Status::Inactive, $repository->find('inactive'));
    }

    public function testFindAllReturnsEnumCaseInstances(): void
    {
        $repository = new Repository(
            [
                'active' => ['id' => 'active', 'name' => 'Active'],
                'inactive' => ['id' => 'inactive', 'name' => 'Inactive'],
            ],
            Status::class,
            idAttribute: 'id'
        );

        $this->assertSame([Status::Active, Status::Inactive], $repository->findAll());
    }

    public function testFindWorksWithACustomIdAttributeName(): void
    {
        $repository = new Repository(
            [
                'active' => ['codigo' => 'active', 'name' => 'Active'],
                'inactive' => ['codigo' => 'inactive', 'name' => 'Inactive'],
            ],
            Status::class,
            idAttribute: 'codigo'
        );

        $this->assertSame(Status::Active, $repository->find('active'));
    }

    public function testFindThrowsForNonBackedEnumEntity(): void
    {
        $repository = new Repository(
            ['active' => ['id' => 'active', 'name' => 'Active']],
            PureStatus::class,
            idAttribute: 'id'
        );

        $this->expectException(EntityException::class);

        $repository->find('active');
    }

    public function testFindThrowsWhenTheIdAttributeIsNotAvailable(): void
    {
        $repository = new Repository(
            ['active' => ['name' => 'Active']],
            Status::class
        );

        $this->expectException(EntityException::class);

        $repository->find('active');
    }

    /**
     * Exercises the full chain: DataProvider (with an `enum:` source and a
     * non-default idAttribute) -> RepositoryManager -> Repository, to prove
     * the configured idAttribute is what actually gets used to resolve the
     * enum case, not a hardcoded default.
     */
    public function testEndToEndThroughRepositoryManagerWithACustomIdAttribute(): void
    {
        $dataProvider = new DataProvider(
            [Status::class => 'enum:' . Status::class],
            config: [
                'normalization' => ['idAttribute' => 'codigo'],
            ]
        );
        $manager = new RepositoryManager($dataProvider);

        $repository = $manager->getRepository(Status::class);

        $this->assertContains(Status::class, $manager->getAvailableRepositories());
        $this->assertSame(Status::class, $repository->getClassName());
        $this->assertSame(Status::Active, $repository->find('active'));
        $this->assertSame([Status::Active, Status::Inactive], $repository->findAll());
    }
}
