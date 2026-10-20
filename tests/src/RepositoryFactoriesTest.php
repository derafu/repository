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

use Derafu\Container\Contract\ContainerInterface;
use Derafu\Repository\Contract\DataProviderInterface;
use Derafu\Repository\Contract\RepositoryFactoryInterface;
use Derafu\Repository\Contract\RepositoryInterface;
use Derafu\Repository\Exception\ManagerException;
use Derafu\Repository\Factory\DataProviderRepositoryFactory;
use Derafu\Repository\Repository;
use Derafu\Repository\Service\DataProvider;
use Derafu\Repository\Service\DataSource\DataSourceResolverRegistry;
use Derafu\Repository\Service\DataSource\EnumDataSourceResolver;
use Derafu\Repository\Service\DataSource\FileDataSource;
use Derafu\Repository\Service\DataSource\FileDataSourceResolver;
use Derafu\Repository\Service\DataSource\FileFormat\FileFormatReaderRegistry;
use Derafu\Repository\Service\DataSource\FileFormat\PhpFileFormatReader;
use Derafu\Repository\Service\RepositoryManager;
use Derafu\Repository\Translation\RepositoryTranslationResourceProvider;
use Derafu\TestsRepository\Fixture\Factory\SqliteRepository;
use Derafu\Translation\TranslatorFactory;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(RepositoryManager::class)]
#[UsesClass(DataProviderRepositoryFactory::class)]
#[UsesClass(Repository::class)]
#[UsesClass(DataProvider::class)]
#[UsesClass(DataSourceResolverRegistry::class)]
#[UsesClass(EnumDataSourceResolver::class)]
#[UsesClass(FileDataSource::class)]
#[UsesClass(FileDataSourceResolver::class)]
#[UsesClass(FileFormatReaderRegistry::class)]
#[UsesClass(PhpFileFormatReader::class)]
#[UsesClass(RepositoryTranslationResourceProvider::class)]
final class RepositoryFactoriesTest extends TestCase
{
    public function testRegisteredFactoryCreatesALiveDatabaseRepositoryWithoutADataProvider(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite is required for the live database adapter test.');
        }
        $connection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $connection->exec('CREATE TABLE catalog (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $connection->exec("INSERT INTO catalog VALUES (1, 'Alpha'), (2, 'Beta')");
        $factory = new class ($connection) implements RepositoryFactoryInterface {
            public int $creations = 0;

            public ?string $requestedId = null;

            public function __construct(private readonly PDO $connection)
            {
            }

            public function create(string $repository): RepositoryInterface
            {
                $this->creations++;
                $this->requestedId = $repository;

                return new SqliteRepository($this->connection);
            }
        };
        // An explicit factory must not require this interface or entity to exist.
        $id = 'App\Contract\DatabaseCatalogInterface';
        $manager = new RepositoryManager(factories: [$id => $factory]);
        $this->assertSame([$id], $manager->getAvailableRepositories());
        $this->assertSame(0, $factory->creations);
        $repository = $manager->getRepository($id);
        $this->assertNotInstanceOf(ContainerInterface::class, $repository);
        $this->assertSame($id, $factory->requestedId);
        $this->assertSame(stdClass::class, $repository->getClassName());
        $this->assertSame(['id' => 1, 'name' => 'Alpha'], $this->row($repository->find(1)));
        $this->assertNull($repository->find(9));
        $this->assertCount(2, $repository->findAll());
        $this->assertSame(2, $repository->count());
        $this->assertSame(1, $repository->count(['name' => 'Beta']));
        $this->assertSame([['id' => 2, 'name' => 'Beta']], array_map(get_object_vars(...), $repository->findBy([], ['id' => 'DESC'], 1)));
        $this->assertSame([['id' => 2, 'name' => 'Beta']], array_map(get_object_vars(...), $repository->findBy([], ['id' => 'ASC'], 1, 1)));
        $this->assertSame(['id' => 2, 'name' => 'Beta'], $this->row($repository->findOneBy(['name' => 'Beta'])));
        $connection->exec("INSERT INTO catalog VALUES (3, 'Gamma')");
        $this->assertSame($repository, $manager->getRepository($id));
        $this->assertSame(3, $repository->count());
        $this->assertSame(['id' => 3, 'name' => 'Gamma'], $this->row($repository->find(3)));
        $this->assertSame(1, $factory->creations);
    }

    public function testExplicitFactoryOverridesDataProviderWithoutReadingOrResolvingEntities(): void
    {
        $repository = $this->createStub(RepositoryInterface::class);
        $factory = $this->createMock(RepositoryFactoryInterface::class);
        $factory->expects($this->once())->method('create')->with('items')->willReturn($repository);
        $provider = $this->createMock(DataProviderInterface::class);
        $provider->expects($this->never())->method('fetch');
        $provider->expects($this->never())->method('getConfiguration');
        $provider->expects($this->once())->method('getSources')->willReturn(['items', 'files']);
        $manager = new RepositoryManager($provider, ['items' => $factory, 'database' => $factory]);
        $this->assertSame(['items', 'files', 'database'], $manager->getAvailableRepositories());
        $this->assertSame($repository, $manager->getRepository('items'));
        $manager->getRepository('items'); // The factory expectation allows only one creation.
    }

    public function testExplicitAndFileRepositoriesCoexist(): void
    {
        $databaseRepository = $this->createStub(RepositoryInterface::class);
        $factory = $this->createMock(RepositoryFactoryInterface::class);
        $factory->expects($this->once())->method('create')->with('database')->willReturn($databaseRepository);
        $provider = new DataProvider(['files' => dirname(__DIR__) . '/fixtures/products.php']);
        $manager = new RepositoryManager($provider, ['database' => $factory]);
        $this->assertSame(['files', 'database'], $manager->getAvailableRepositories());
        $this->assertSame($databaseRepository, $manager->getRepository('database'));
        $files = $manager->getRepository('files');
        $this->assertInstanceOf(Repository::class, $files);
        $this->assertSame(4, $files->count());
        $this->assertSame($files, $manager->getRepository('files'));
    }

    public function testMissingRegistrationWithoutDataProviderHasATranslatableError(): void
    {
        $manager = new RepositoryManager();
        $this->assertSame([], $manager->getAvailableRepositories());
        try {
            $manager->getRepository('unknown');
            $this->fail('A missing repository must be rejected.');
        } catch (ManagerException $exception) {
            $this->assertSame('No repository factory configured for unknown.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new RepositoryTranslationResourceProvider()]);
            $this->assertSame('No hay una fábrica de repositorios configurada para unknown.', $exception->trans($translator));
        }
    }

    public function testFailedFactoryIsNotCachedAndCanBeRetried(): void
    {
        $repository = $this->createStub(RepositoryInterface::class);
        $factory = new class ($repository) implements RepositoryFactoryInterface {
            public int $attempts = 0;

            public function __construct(private readonly RepositoryInterface $repository)
            {
            }

            public function create(string $repository): RepositoryInterface
            {
                if (++$this->attempts === 1) {
                    throw new RuntimeException('Connection unavailable.');
                }

                return $this->repository;
            }
        };
        $manager = new RepositoryManager(factories: ['database' => $factory]);
        try {
            $manager->getRepository('database');
            $this->fail('The initial factory failure must be propagated.');
        } catch (ManagerException $exception) {
            $this->assertSame('Connection unavailable.', $exception->getMessage());
        }
        $this->assertSame($repository, $manager->getRepository('database'));
        $manager->getRepository('database');
        $this->assertSame(2, $factory->attempts);
    }

    /** @return array<string, mixed> */
    private function row(?object $row): array
    {
        $this->assertInstanceOf(stdClass::class, $row);

        return get_object_vars($row);
    }
}
