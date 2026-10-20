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

use BackedEnum;
use Derafu\Repository\Exception\EntityException;
use Derafu\Repository\Repository;
use Derafu\Repository\Translation\RepositoryTranslationResourceProvider;
use Derafu\TestsRepository\Fixture\IntegerStatus;
use Derafu\TestsRepository\Fixture\Status;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Repository::class)]
#[UsesClass(EntityException::class)]
#[UsesClass(RepositoryTranslationResourceProvider::class)]
final class EnumIdentifierTest extends TestCase
{
    /** @return array<string, array{mixed}> */
    public static function invalidIdentifiers(): array
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'integer-valued float' => [1.0],
            'fractional float' => [1.5],
            'array' => [['active']],
            'object' => [new stdClass()],
        ];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testInvalidIdTypesHaveATranslatableEntityException(mixed $value): void
    {
        $repository = new Repository(['row' => ['codigo' => $value]], Status::class, idAttribute: 'codigo');
        $snapshot = $repository->all();

        try {
            $repository->find('row');
            $this->fail('An enum identifier must be an int or string.');
        } catch (EntityException $exception) {
            $type = get_debug_type($value);
            $this->assertSame(
                'The ID attribute "codigo" of enum entity ' . Status::class . ' must be an int or string; ' . $type . ' given.',
                $exception->getMessage()
            );
            $translator = TranslatorFactory::create('es', resourceProviders: [new RepositoryTranslationResourceProvider()]);
            $this->assertSame(
                'El atributo ID "codigo" de la entidad enum ' . Status::class . ' debe ser int o string; se recibió ' . $type . '.',
                $exception->trans($translator)
            );
            $this->assertSame($snapshot, $repository->all());
        }
    }

    public function testAResourceIdIsRejectedWithoutBeingClosed(): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        try {
            $repository = new Repository(['row' => ['id' => $resource]], Status::class, idAttribute: 'id');
            try {
                $repository->find('row');
                $this->fail('A resource cannot identify an enum case.');
            } catch (EntityException $exception) {
                $this->assertStringContainsString('resource (stream) given.', $exception->getMessage());
                $this->assertSame(10, fwrite($resource, 'still open'));
            }
        } finally {
            fclose($resource);
        }
    }

    public function testConfiguredMissingIdsAreStillFilledFromRowKeys(): void
    {
        $strings = new Repository(['active' => ['name' => 'Active']], Status::class, idAttribute: 'codigo');
        $this->assertSame(Status::Active, $strings->find('active'));
        $this->assertSame(['active' => ['codigo' => 'active', 'name' => 'Active']], $strings->all());

        $integers = new Repository([0 => [], 1 => []], IntegerStatus::class, idAttribute: 'id');
        $this->assertSame(IntegerStatus::Inactive, $integers->find(0));
        $this->assertSame(IntegerStatus::Active, $integers->find(1));
        $this->assertSame([IntegerStatus::Inactive, IntegerStatus::Active], $integers->findAll());
        $this->assertSame([0 => ['id' => 0], 1 => ['id' => 1]], $integers->all());
    }

    public function testAMissingUnconfiguredIdStillFailsRatherThanGuessingAnAttribute(): void
    {
        $repository = new Repository(['row' => ['id' => 'active']], Status::class);
        try {
            $repository->find('row');
            $this->fail('The ID attribute must not be guessed.');
        } catch (EntityException $exception) {
            $this->assertStringContainsString('ID attribute ""', $exception->getMessage());
            $this->assertStringContainsString('null given.', $exception->getMessage());
        }
    }

    public function testAnEmptyAttributeNameKeepsItsExistingMeaning(): void
    {
        $repository = new Repository(['row' => ['' => 'active']], Status::class);
        $this->assertSame(Status::Active, $repository->find('row'));
    }

    /** @return array<string, array{class-string<BackedEnum>, int|string}> */
    public static function rejectedEnumValues(): array
    {
        return [
            'unknown string value' => [Status::class, 'missing'],
            'unknown integer value' => [IntegerStatus::class, 99],
            'wrong backing type for string enum' => [Status::class, 1],
            'wrong backing type for integer enum' => [IntegerStatus::class, '1'],
        ];
    }

    /** @param class-string<BackedEnum> $class */
    #[DataProvider('rejectedEnumValues')]
    public function testEnumRejectionsKeepTheExistingExceptionWrapper(string $class, int|string $value): void
    {
        $repository = new Repository(['row' => ['id' => $value]], $class, idAttribute: 'id');
        try {
            $repository->find('row');
            $this->fail('An incompatible value must be rejected by the enum.');
        } catch (EntityException $exception) {
            $this->assertStringStartsWith('Could not create enum entity ' . $class . ': ', $exception->getMessage());
            $this->assertSame(['row' => ['id' => $value]], $repository->all());
        }
    }
}
