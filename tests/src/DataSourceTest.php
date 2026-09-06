<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight File Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository;

use ArrayObject;
use Derafu\Repository\Contract\DataSourceInterface;
use Derafu\Repository\Contract\FileFormatReaderInterface;
use Derafu\Repository\Exception\DataProviderException;
use Derafu\Repository\Service\DataProvider;
use Derafu\Repository\Service\DataSource\FileDataSource;
use Derafu\Repository\Service\DataSource\FileFormat\FileFormatReaderRegistry;
use Derafu\Repository\Service\DataSource\FileFormat\JsonFileFormatReader;
use Derafu\Repository\Service\DataSource\FileFormat\PhpFileFormatReader;
use Derafu\Repository\Service\DataSource\FileFormat\YamlFileFormatReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider as TestDataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileDataSource::class)]
#[CoversClass(FileFormatReaderRegistry::class)]
#[CoversClass(PhpFileFormatReader::class)]
#[CoversClass(JsonFileFormatReader::class)]
#[CoversClass(YamlFileFormatReader::class)]
#[CoversClass(DataProvider::class)]
class DataSourceTest extends TestCase
{
    private const EXPECTED_DATA = [
        'item-1' => ['name' => 'Alpha'],
        'item-2' => ['name' => 'Beta'],
    ];

    public static function provideValidFiles(): array
    {
        $dir = dirname(__DIR__) . '/fixtures/file-formats';

        return [
            'php' => [$dir . '/valid.php'],
            'json' => [$dir . '/valid.json'],
            'yaml' => [$dir . '/valid.yaml'],
        ];
    }

    #[TestDataProvider('provideValidFiles')]
    public function testFileDataSourceReadsEachSupportedFormat(string $filepath): void
    {
        $source = new FileDataSource($filepath, new FileFormatReaderRegistry());

        $this->assertSame(self::EXPECTED_DATA, $source->read());
    }

    public static function provideInvalidFiles(): array
    {
        $dir = dirname(__DIR__) . '/fixtures/file-formats';

        return [
            'php' => [$dir . '/invalid.php'],
            'json' => [$dir . '/invalid.json'],
            'yaml' => [$dir . '/invalid.yaml'],
        ];
    }

    #[TestDataProvider('provideInvalidFiles')]
    public function testFileDataSourceThrowsWhenContentIsNotAnArray(string $filepath): void
    {
        $this->expectException(DataProviderException::class);

        (new FileDataSource($filepath, new FileFormatReaderRegistry()))->read();
    }

    public function testFileFormatReaderRegistryThrowsForUnsupportedExtension(): void
    {
        $dir = dirname(__DIR__) . '/fixtures/file-formats';

        $this->expectException(DataProviderException::class);

        (new FileDataSource($dir . '/unsupported.txt', new FileFormatReaderRegistry()))->read();
    }

    public function testFileFormatReaderRegistryAcceptsAnExplicitListOfReaders(): void
    {
        $dir = dirname(__DIR__) . '/fixtures/file-formats';

        $registry = new FileFormatReaderRegistry([new PhpFileFormatReader()]);

        $this->assertSame(
            self::EXPECTED_DATA,
            (new FileDataSource($dir . '/valid.php', $registry))->read()
        );

        $this->expectException(DataProviderException::class);
        (new FileDataSource($dir . '/valid.json', $registry))->read();
    }

    public function testFileFormatReaderRegistrySupportsCustomReaders(): void
    {
        $dir = dirname(__DIR__) . '/fixtures/file-formats';

        $customReader = new class () implements FileFormatReaderInterface {
            public function supports(string $extension): bool
            {
                return $extension === 'txt';
            }

            public function read(string $filepath): array
            {
                return ['name' => trim(file_get_contents($filepath))];
            }
        };

        $registry = new FileFormatReaderRegistry();
        $registry->register($customReader);

        $source = new FileDataSource($dir . '/unsupported.txt', $registry);

        $this->assertSame(
            ['name' => 'not a supported file format'],
            $source->read()
        );
    }

    public function testDataProviderAcceptsAnyDataSourceInterfaceInstance(): void
    {
        $customSource = new class () implements DataSourceInterface {
            private const DATA = [
                'enum-case-1' => ['name' => 'Active'],
                'enum-case-2' => ['name' => 'Inactive'],
            ];

            public function read(): array
            {
                return self::DATA;
            }
        };

        $dataProvider = new DataProvider(['status' => $customSource]);
        $dataProvider->setConfiguration([
            'normalization' => [
                'idAttribute' => 'id',
                'nameAttribute' => 'name',
            ],
        ]);

        $data = $dataProvider->fetch('status');

        $this->assertInstanceOf(ArrayObject::class, $data);
        $this->assertCount(2, $data);
        $this->assertSame('Active', $data['enum-case-1']['name']);
    }
}
