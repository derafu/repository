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
