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

use Derafu\Array\Exception\MissingValueException;
use Derafu\Array\Exception\TypeMismatchException;
use Derafu\Repository\Entity;
use Derafu\Repository\Exception\EntityException;
use Derafu\Repository\Repository;
use Derafu\Repository\Translation\RepositoryTranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Entity::class)]
#[UsesClass(Repository::class)]
#[UsesClass(RepositoryTranslationResourceProvider::class)]
final class EntityTest extends TestCase
{
    public function testReaderPreservesAttributesAndSupportsTypedAndNestedReads(): void
    {
        $entity = new Entity();
        $entity->setAttribute('id', 7)
            ->setAttribute('amount', '19.90')
            ->setAttribute('optional', null)
            ->setAttribute('profile', ['name' => 'Ana', 'city' => 'Santiago'])
            ->setAttribute('profile.name', 'Literal name')
            ->setAttribute('roles', [7 => 'editor', 2 => 'reader']);
        $attributes = $entity->toArray();
        $reader = $entity->getReader();

        $this->assertSame(7, $reader->requireInt('id'));
        $this->assertSame(19.9, $reader->requireAsFloat('amount'));
        $this->assertSame('Literal name', $reader->requireString('profile.name'));
        $this->assertSame('Santiago', $reader->requireString('profile.city'));
        $this->assertSame([7 => 'editor', 2 => 'reader'], $reader->requireArray('roles'));
        $this->assertTrue($reader->hasKey('optional'));
        $this->assertFalse($reader->hasValue('optional'));
        $this->assertNull($reader->getString('optional'));
        $this->assertFalse($reader->hasKey('missing'));
        $this->assertSame($attributes, $entity->toArray());
    }

    public function testReadersKeepTheirSnapshotAfterAttributesChangeOrAreRemoved(): void
    {
        $entity = new Entity();
        $entity->setAttribute('name', 'Alpha')->setAttribute('obsolete', true);
        $before = $entity->getReader();

        $entity->setAttribute('name', 'Beta');
        $entity->unsetAttribute('obsolete');
        $entity->setAttribute('added', 3);
        $after = $entity->getReader();

        $this->assertSame('Alpha', $before->requireString('name'));
        $this->assertTrue($before->requireBool('obsolete'));
        $this->assertFalse($before->hasKey('added'));
        $this->assertSame('Beta', $after->requireString('name'));
        $this->assertFalse($after->hasKey('obsolete'));
        $this->assertSame(3, $after->requireInt('added'));
    }

    public function testReaderReportsMissingValuesWithAnArrayException(): void
    {
        $this->expectException(MissingValueException::class);
        $this->expectExceptionMessage('missing');

        (new Entity())->getReader()->requireString('missing');
    }

    public function testStrictReaderRejectsNumericTextWithoutChangingTheAttribute(): void
    {
        $entity = new Entity();
        $entity->setAttribute('id', '7');

        try {
            $entity->getReader()->requireInt('id');
            $this->fail('A strict integer read must reject numeric text.');
        } catch (TypeMismatchException $exception) {
            $this->assertStringContainsString('"id"', $exception->getMessage());
            $this->assertSame('7', $entity->getAttribute('id'));
        }
    }

    public function testGenericRepositoryEntitiesExposeTheirAttributesAndIdentity(): void
    {
        $repository = new Repository(['A' => ['id' => 'A', 'name' => 'Alpha']], Entity::class);
        $entity = $repository->find('A');
        $this->assertInstanceOf(Entity::class, $entity);
        $this->assertSame(['id' => 'A', 'name' => 'Alpha'], $entity->toArray());
        $this->assertSame(Entity::class . '@' . spl_object_id($entity), (string) $entity);
        $this->assertSame($entity, $entity->__call('setDisplayName', ['Updated']));
        $this->assertSame('Updated', $entity->__call('getDisplayName', []));
        $entity->__set('name', 'Beta');
        $this->assertSame('Beta', $entity->getAttribute('name'));
        $this->assertSame('Updated', $entity->toArray()['displayName']);
    }

    public function testMagicUnsetPreservesANullAttributeWhereasExplicitRemovalDeletesIt(): void
    {
        $entity = new Entity();
        $entity->setAttribute('name', 'Alpha');
        unset($entity->name);
        $this->assertTrue($entity->hasAttribute('name'));
        $this->assertNull($entity->getAttribute('name'));
        $this->assertSame(['name' => null], $entity->toArray());
        $entity->unsetAttribute('name');
        $this->assertFalse($entity->hasAttribute('name'));
        $this->assertSame([], $entity->toArray());
        $entity->unsetAttribute('missing');
        $this->assertSame([], $entity->toArray());
    }

    public function testUnknownMagicMethodHasATranslatableError(): void
    {
        try {
            (new Entity())->__call('unknown', []);
            $this->fail('Unknown instance methods must be rejected.');
        } catch (EntityException $exception) {
            $this->assertSame('Method ' . Entity::class . '::unknown() does not exist.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new RepositoryTranslationResourceProvider()]);
            $this->assertSame('El método ' . Entity::class . '::unknown() no existe.', $exception->trans($translator));
        }
    }
}
