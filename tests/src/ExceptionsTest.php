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

use Closure;
use Derafu\Repository\Entity;
use Derafu\Repository\Exception\EntityException;
use Derafu\Repository\Repository;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The errors of the package are translatable and say what they said before,
 * with the values that caused them in the message.
 */
#[CoversClass(Repository::class)]
#[CoversClass(Entity::class)]
final class ExceptionsTest extends TestCase
{
    /**
     * @return array<string, array{Closure, class-string<Throwable>, string}>
     */
    public static function errorsProvider(): array
    {
        return [
            'an id that is not a string or an int' => [
                fn () => (new Repository([['id' => 1]], idAttribute: 'id'))->find([]),
                InvalidArgumentException::class,
                'In method ' . Repository::class . ':find($id) an $id of type array was passed and only string and int are allowed.',
            ],
            'a negative offset' => [
                fn () => (new Repository([['id' => 1]], idAttribute: 'id'))->findBy([], null, 1, -1),
                InvalidArgumentException::class,
                'Offset cannot be negative.',
            ],
            'a negative limit' => [
                fn () => (new Repository([['id' => 1]], idAttribute: 'id'))->findBy([], null, -1, 0),
                InvalidArgumentException::class,
                'Limit cannot be negative.',
            ],
            'an attribute that does not exist' => [
                fn () => (new Entity())->setAttribute('id', 1)->getAttribute('name'),
                EntityException::class,
                'Attribute name does not exist in entity ' . Entity::class . '.',
            ],
            'a method that does not exist' => [
                fn () => (new Entity())->__call('doSomething', []),
                EntityException::class,
                'Method ' . Entity::class . '::doSomething() does not exist.',
            ],
            'a static method that does not exist' => [
                fn () => Entity::__callStatic('doSomething', []),
                EntityException::class,
                'Method ' . Entity::class . '::doSomething() does not exist.',
            ],
        ];
    }

    /**
     * @param class-string<Throwable> $class
     */
    #[DataProvider('errorsProvider')]
    public function testEveryErrorIsTranslatableAndSaysTheSame(Closure $error, string $class, string $message): void
    {
        $exception = null;
        try {
            $error();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf($class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }
}
