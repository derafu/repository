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

use Derafu\Repository\Contract\DataProviderInterface;
use Derafu\Repository\Contract\FileFormatReaderRegistryInterface;
use Derafu\Repository\Contract\RepositoryManagerInterface;
use Derafu\Repository\Entity;
use Derafu\Repository\Exception\DataProviderException;
use Derafu\Repository\Factory\DataProviderRepositoryFactory;
use Derafu\Repository\Mapping as DEM;
use Derafu\Repository\Repository;
use Derafu\Repository\Service\ArrayRepositorySourceProvider;
use Derafu\Repository\Service\CriteriaEvaluator;
use Derafu\Repository\Service\DataProvider;
use Derafu\Repository\Service\DataSource\DataSourceResolverRegistry;
use Derafu\Repository\Service\DataSource\EnumDataSource;
use Derafu\Repository\Service\DataSource\EnumDataSourceResolver;
use Derafu\Repository\Service\DataSource\FileDataSource;
use Derafu\Repository\Service\DataSource\FileDataSourceResolver;
use Derafu\Repository\Service\DataSource\FileFormat\FileFormatReaderRegistry;
use Derafu\Repository\Service\DataSource\FileFormat\JsonFileFormatReader;
use Derafu\Repository\Service\DataSource\FileFormat\PhpFileFormatReader;
use Derafu\Repository\Service\DataSource\FileFormat\YamlFileFormatReader;
use Derafu\Repository\Service\RepositoryManager;
use Derafu\Repository\Translation\RepositoryTranslationResourceProvider;
use Derafu\TestsRepository\Fixture\Factory\Entity\DefaultMapped;
use Derafu\TestsRepository\Fixture\Factory\MappedRepository;
use Derafu\TestsRepository\Fixture\Factory\SqliteRepository;
use Derafu\TestsRepository\Fixture\Services\RecordingCriteriaEvaluator;
use Derafu\TestsRepository\Fixture\Services\RepositoryTestKernel;
use Doctrine\Common\Collections\Criteria;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\Translator;

#[CoversClass(ArrayRepositorySourceProvider::class)]
#[CoversClass(DataProvider::class)]
#[CoversClass(RepositoryManager::class)]
#[CoversClass(DataProviderRepositoryFactory::class)]
#[UsesClass(DEM\Entity::class)]
#[UsesClass(Repository::class)]
#[UsesClass(Entity::class)]
#[UsesClass(CriteriaEvaluator::class)]
#[UsesClass(DataSourceResolverRegistry::class)]
#[UsesClass(EnumDataSource::class)]
#[UsesClass(EnumDataSourceResolver::class)]
#[UsesClass(FileDataSource::class)]
#[UsesClass(FileDataSourceResolver::class)]
#[UsesClass(FileFormatReaderRegistry::class)]
#[UsesClass(PhpFileFormatReader::class)]
#[UsesClass(JsonFileFormatReader::class)]
#[UsesClass(YamlFileFormatReader::class)]
#[UsesClass(RepositoryTranslationResourceProvider::class)]
final class ServicesTest extends TestCase
{
    private RepositoryTestKernel $kernel;

    /** @return ServiceLocator<object> */
    private function services(string $environment = 'default'): ServiceLocator
    {
        $this->kernel = new RepositoryTestKernel($environment);
        $services = $this->kernel->getContainer()->get('test.repository_services');
        self::assertInstanceOf(ServiceLocator::class, $services);

        return $services;
    }

    public function testDefaultConfigurationConstructsEveryServiceAndItsAliases(): void
    {
        $services = $this->services();
        foreach (array_keys($services->getProvidedServices()) as $id) {
            self::assertTrue(class_exists($id) || interface_exists($id));
            self::assertInstanceOf($id, $services->get($id));
        }
        self::assertSame($services->get(RepositoryManagerInterface::class), $services->get(RepositoryManager::class));
        self::assertSame($services->get(DataProviderInterface::class), $services->get(DataProvider::class));
        $manager = $services->get(RepositoryManagerInterface::class);
        self::assertInstanceOf(RepositoryManagerInterface::class, $manager);
        self::assertSame([], $manager->getAvailableRepositories());
        $provider = $services->get(DataProviderInterface::class);
        self::assertInstanceOf(DataProviderInterface::class, $provider);
        self::assertSame('id', $provider->getConfiguration()->get('normalization.idAttribute'));
        self::assertSame('name', $provider->getConfiguration()->get('normalization.nameAttribute'));
        $factory = $services->get(DataProviderRepositoryFactory::class);
        self::assertInstanceOf(DataProviderRepositoryFactory::class, $factory);
        self::assertSame(Repository::class, $factory->getConfiguration()->get('repositoryClass'));
    }

    public function testConfigurationConstructsTheTranslationProvider(): void
    {
        $provider = $this->services()->get(RepositoryTranslationResourceProvider::class);
        self::assertInstanceOf(RepositoryTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        self::assertCount(1, $directories);
        self::assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
    }

    public function testTaggedResourcesTranslateARealUnsupportedFormatException(): void
    {
        $readers = $this->services()->get(FileFormatReaderRegistryInterface::class);
        self::assertInstanceOf(FileFormatReaderRegistryInterface::class, $readers);
        $translator = $this->kernel->getContainer()->get('test.translator');
        self::assertInstanceOf(Translator::class, $translator);
        try {
            $readers->getReader('txt');
            self::fail('An unsupported file format was accepted.');
        } catch (DataProviderException $exception) {
            self::assertSame('No hay un lector de formato de archivo registrado para la extensión "txt".', $exception->trans($translator));
        }
    }

    public function testIndependentProvidersAndParameterSourcesAreCombinedWithoutCopying(): void
    {
        $manager = $this->services('combined')->get(RepositoryManagerInterface::class);
        self::assertInstanceOf(RepositoryManagerInterface::class, $manager);
        self::assertEqualsCanonicalizing(['parameter', 'core', 'statuses', 'pro', 'yaml', 'ini', 'database_source', 'resolved_source', 'live'], $manager->getAvailableRepositories());
        foreach (['parameter' => ['A', 'Core item'], 'core' => ['A', 'Core item'], 'pro' => ['B', 'Pro item'], 'yaml' => ['C', 'YAML item'], 'ini' => ['D', 'INI item'], 'database_source' => [1, 'Database item'], 'resolved_source' => [1, 'Database item']] as $id => [$key, $name]) {
            $repository = $manager->getRepository($id);
            self::assertInstanceOf(MappedRepository::class, $repository);
            $entity = $repository->find($key);
            self::assertInstanceOf(DefaultMapped::class, $entity, $id);
            self::assertSame($key, $entity->getAttribute('codigo'));
            self::assertSame($name, $entity->getAttribute('glosa'));
            self::assertSame($repository, $manager->getRepository($id));
        }
        $status = $manager->getRepository('statuses')->find('active');
        self::assertInstanceOf(Entity::class, $status);
        self::assertSame('Active', $status->getAttribute('glosa'));
    }

    public function testTaggedFactoryCreatesALiveRepositoryAndTakesPrecedenceOverTheDefault(): void
    {
        $manager = $this->services('combined')->get(RepositoryManagerInterface::class);
        self::assertInstanceOf(RepositoryManagerInterface::class, $manager);
        $repository = $manager->getRepository('live');
        self::assertInstanceOf(SqliteRepository::class, $repository);
        $item = $repository->find(1);
        self::assertInstanceOf(stdClass::class, $item);
        self::assertSame('Database item', $item->name);
        $connection = $this->kernel->getContainer()->get('test.connection');
        self::assertInstanceOf(PDO::class, $connection);
        $connection->exec("INSERT INTO catalog VALUES (2, 'Another item')");
        $item = $repository->find(2);
        self::assertInstanceOf(stdClass::class, $item);
        self::assertSame('Another item', $item->name);
        self::assertSame($repository, $manager->getRepository('live'));
    }

    public function testDefaultFactoryUsesTheConfiguredCriteriaEvaluator(): void
    {
        $manager = $this->services('combined')->get(RepositoryManagerInterface::class);
        self::assertInstanceOf(RepositoryManagerInterface::class, $manager);
        $evaluator = $this->kernel->getContainer()->get(RecordingCriteriaEvaluator::class);
        self::assertInstanceOf(RecordingCriteriaEvaluator::class, $evaluator);
        self::assertSame(0, $evaluator->filterCalls);
        $matches = $manager->getRepository('core')->findByCriteria(Criteria::create()->where(Criteria::expr()->eq('glosa', 'Core item')));
        self::assertCount(1, $matches);
        self::assertGreaterThan(0, $evaluator->filterCalls);
    }

    public function testDuplicateSourcesAreRejectedAndTheExceptionIsTranslatable(): void
    {
        $services = $this->services('duplicate');
        $translator = $this->kernel->getContainer()->get('test.translator');
        self::assertInstanceOf(Translator::class, $translator);
        try {
            $services->get(DataProviderInterface::class);
            self::fail('Duplicate repository sources must be rejected.');
        } catch (DataProviderException $exception) {
            self::assertSame('Repository source "core" is registered more than once.', $exception->getMessage());
            self::assertSame('La fuente de repositorio "core" está registrada más de una vez.', $exception->trans($translator));
        }
    }
}
