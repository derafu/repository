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

use ArrayObject;
use Derafu\Config\Configuration;
use Derafu\Config\Contract\ConfigurationInterface;
use Derafu\Repository\Contract\DataProviderInterface;
use Derafu\Repository\Entity;
use Derafu\Repository\Exception\ManagerException;
use Derafu\Repository\Factory\DataProviderRepositoryFactory;
use Derafu\Repository\Mapping as DEM;
use Derafu\Repository\Repository;
use Derafu\Repository\Service\CriteriaEvaluator;
use Derafu\Repository\Service\RepositoryManager;
use Derafu\Repository\Translation\RepositoryTranslationResourceProvider;
use Derafu\TestsRepository\Fixture\Factory\AbstractRepository;
use Derafu\TestsRepository\Fixture\Factory\Contract\MappedInterface;
use Derafu\TestsRepository\Fixture\Factory\Entity\DefaultMapped;
use Derafu\TestsRepository\Fixture\Factory\Entity\InvalidMapped;
use Derafu\TestsRepository\Fixture\Factory\Entity\Mapped;
use Derafu\TestsRepository\Fixture\Factory\MappedRepository;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[UsesClass(CriteriaEvaluator::class)]
#[CoversClass(DataProviderRepositoryFactory::class)]
#[UsesClass(Repository::class)]
#[UsesClass(Entity::class)]
#[CoversClass(DEM\Entity::class)]
#[UsesClass(RepositoryManager::class)]
#[UsesClass(RepositoryTranslationResourceProvider::class)]
final class DataProviderRepositoryFactoryTest extends TestCase
{
    /** @return array<string, array{string, class-string<Entity>, class-string<Repository>}> */
    public static function mappings(): array
    {
        return [
            'class with custom repository attribute' => [Mapped::class, Mapped::class, MappedRepository::class],
            'entity interface naming convention' => [MappedInterface::class, Mapped::class, MappedRepository::class],
            'attribute without repository uses default' => [DefaultMapped::class, DefaultMapped::class, Repository::class],
            'entity without mapping uses default' => [Entity::class, Entity::class, Repository::class],
            'plain identifier uses default entity' => ['catalog', Entity::class, Repository::class],
        ];
    }

    /**
     * @param class-string<Entity> $entityClass
     * @param class-string<Repository> $repositoryClass
     */
    #[DataProvider('mappings')]
    public function testMappingsAndNormalizationKeepExistingEntitiesAndRows(string $id, string $entityClass, string $repositoryClass): void
    {
        $provider = $this->provider($id);
        $factory = new DataProviderRepositoryFactory($provider, new CriteriaEvaluator(), $this->configuration());
        $repository = $factory->create($id);
        $this->assertInstanceOf($repositoryClass, $repository);
        $this->assertInstanceOf(Repository::class, $repository);
        $this->assertSame($entityClass, $repository->getClassName());
        $entity = $repository->find('A');
        $this->assertInstanceOf($entityClass, $entity);
        $this->assertSame('A', $entity->getAttribute('code'));
        $this->assertSame('Alpha', $entity->getAttribute('name'));
        $this->assertSame(['A' => ['code' => 'A', 'name' => 'Alpha']], $repository->all());
    }

    public function testFactoryConfigurationStillSelectsDefaultEntityAndRepository(): void
    {
        $provider = $this->provider('catalog');
        $factory = new DataProviderRepositoryFactory($provider, new CriteriaEvaluator());
        $manager = new RepositoryManager($factory, $provider);
        $factory->setConfiguration(['entityClass' => DefaultMapped::class, 'repositoryClass' => MappedRepository::class]);
        $repository = $manager->getRepository('catalog');
        $this->assertInstanceOf(MappedRepository::class, $repository);
        $this->assertInstanceOf(DefaultMapped::class, $repository->find('A'));
    }

    /** @return array<string, array{array<array-key, mixed>, string, string}> */
    public static function invalidConfigurations(): array
    {
        return [
            'non-string entity' => [['entityClass' => null], 'catalog', 'Repository setting entityClass must be a string; null given.'],
            'missing default entity' => [['entityClass' => 'MissingEntity'], 'catalog', 'Entity class MissingEntity does not exist. Could it be misspelled?'],
            'missing explicit entity' => [[], 'Missing\Entity\Item', 'Entity class Missing\Entity\Item does not exist. Could it be misspelled?'],
            'non-string repository' => [['repositoryClass' => 42], 'catalog', 'Repository setting repositoryClass must be a string; int given.'],
            'missing default repository' => [['repositoryClass' => 'MissingRepository'], 'catalog', 'Repository class MissingRepository does not exist. Could it be misspelled?'],
            'missing mapped repository' => [[], InvalidMapped::class, 'Repository class MissingRepository does not exist. Could it be misspelled?'],
            'unrelated class' => [['repositoryClass' => stdClass::class], 'catalog', 'Repository class stdClass must extend ' . Repository::class . '. Register a factory for other repository implementations.'],
            'abstract repository' => [['repositoryClass' => AbstractRepository::class], 'catalog', 'Repository class ' . AbstractRepository::class . ' must be instantiable.'],
        ];
    }

    /** @param array<array-key, mixed> $settings */
    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationIsRejectedBeforeFetchingRows(array $settings, string $id, string $message): void
    {
        $provider = $this->createMock(DataProviderInterface::class);
        $provider->expects($this->never())->method('fetch');
        $factory = new DataProviderRepositoryFactory($provider, new CriteriaEvaluator(), $this->configuration($settings));
        $this->expectException(ManagerException::class);
        $this->expectExceptionMessage($message);
        $factory->create($id);
    }

    public function testInvalidIdAttributeFromAnotherProviderIsRejectedAndTranslated(): void
    {
        $factory = new DataProviderRepositoryFactory($this->provider('catalog', null), new CriteriaEvaluator(), $this->configuration());
        try {
            $factory->create('catalog');
            $this->fail('An invalid ID attribute must be rejected.');
        } catch (ManagerException $exception) {
            $this->assertSame('Normalization setting idAttribute must be a string; null given.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new RepositoryTranslationResourceProvider()]);
            $this->assertSame('La opción de normalización idAttribute debe ser string; se recibió null.', $exception->trans($translator));
        }
    }

    public function testEmptyIdAttributeRemainsSupported(): void
    {
        $factory = new DataProviderRepositoryFactory($this->provider('catalog', ''), new CriteriaEvaluator(), $this->configuration());
        $repository = $factory->create('catalog');
        $this->assertInstanceOf(Repository::class, $repository);
        $this->assertSame(['A' => ['code' => 'A', 'name' => 'Alpha']], $repository->all());
    }

    /** @param array<array-key, mixed> $settings */
    private function configuration(array $settings = []): ConfigurationInterface
    {
        // Supplied configuration objects may have no schema; the factory must
        // validate the required settings itself.
        return new Configuration(array_replace([
            'entityClass' => Entity::class,
            'repositoryClass' => Repository::class,
        ], $settings));
    }

    private function provider(string $id, ?string $idAttribute = 'code'): DataProviderInterface
    {
        $provider = $this->createMock(DataProviderInterface::class);
        $provider->expects($this->once())->method('fetch')->with($id)->willReturn(
            new ArrayObject(['A' => ['code' => 'A', 'name' => 'Alpha']])
        );
        $provider->method('getConfiguration')->willReturn(new Configuration([
            'normalization' => ['idAttribute' => $idAttribute],
        ]));

        return $provider;
    }
}
