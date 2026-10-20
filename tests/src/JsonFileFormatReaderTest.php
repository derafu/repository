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

use Derafu\Repository\Exception\DataProviderException;
use Derafu\Repository\Service\DataSource\FileFormat\JsonFileFormatReader;
use Derafu\Repository\Translation\RepositoryTranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonFileFormatReader::class)]
#[UsesClass(DataProviderException::class)]
#[UsesClass(RepositoryTranslationResourceProvider::class)]
final class JsonFileFormatReaderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'derafu_repository_json_');
        $this->assertIsString($path);
        $this->path = $path;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    public function testMissingFilesHaveATranslatableReadError(): void
    {
        unlink($this->path);
        try {
            (new JsonFileFormatReader())->read($this->path);
            $this->fail('A missing JSON file must be reported.');
        } catch (DataProviderException $exception) {
            $this->assertSame('Could not read JSON file ' . $this->path . '.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new RepositoryTranslationResourceProvider()]);
            $this->assertSame('No se pudo leer el archivo JSON ' . $this->path . '.', $exception->trans($translator));
        }
    }

    /** @return array<string, array{string}> */
    public static function invalidContents(): array
    {
        return [
            'malformed JSON' => ['{"unfinished":'],
            'null' => ['null'],
            'boolean' => ['false'],
            'number' => ['7'],
            'string' => ['"text"'],
        ];
    }

    #[DataProvider('invalidContents')]
    public function testNonArrayContentsKeepTheirExistingError(string $contents): void
    {
        file_put_contents($this->path, $contents);
        $this->expectException(DataProviderException::class);
        $this->expectExceptionMessage('The JSON file ' . $this->path . ' must decode to an array.');
        (new JsonFileFormatReader())->read($this->path);
    }

    /** @return array<string, array{string, array<array-key, mixed>}> */
    public static function validContents(): array
    {
        return [
            'empty array' => ['[]', []],
            'empty object' => ['{}', []],
            'list' => ['["Alpha", "Beta"]', ['Alpha', 'Beta']],
            'map with mixed keys and nested values' => ['{"7":{"name":"Alpha"},"item":{"enabled":false,"extra":null}}', [7 => ['name' => 'Alpha'], 'item' => ['enabled' => false, 'extra' => null]]],
        ];
    }

    /** @param array<array-key, mixed> $expected */
    #[DataProvider('validContents')]
    public function testArraysAndObjectsAreReadWithoutChangingTheirValues(string $contents, array $expected): void
    {
        file_put_contents($this->path, $contents);
        $this->assertSame($expected, (new JsonFileFormatReader())->read($this->path));
    }
}
