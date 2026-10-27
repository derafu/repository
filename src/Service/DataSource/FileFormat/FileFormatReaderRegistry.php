<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Repository\Service\DataSource\FileFormat;

use Derafu\Repository\Contract\FileFormatReaderInterface;
use Derafu\Repository\Contract\FileFormatReaderRegistryInterface;
use Derafu\Repository\Exception\DataProviderException;

/**
 * Registry of file format readers.
 *
 * Resolves the reader that must be used for a given file extension. New
 * formats can be supported by registering additional readers, without
 * modifying this class or any of the built-in readers.
 */
final class FileFormatReaderRegistry implements FileFormatReaderRegistryInterface
{
    /**
     * Registered readers.
     *
     * @var FileFormatReaderInterface[]
     */
    private array $readers;

    /**
     * @param iterable<FileFormatReaderInterface> $readers
     */
    public function __construct(iterable $readers)
    {
        $this->readers = [...$readers];
    }

    /**
     * Registers an additional reader.
     *
     * @param FileFormatReaderInterface $reader
     * @return void
     */
    public function register(FileFormatReaderInterface $reader): void
    {
        $this->readers[] = $reader;
    }

    /**
     * Returns the reader that supports the given file extension.
     *
     * @param string $extension
     * @return FileFormatReaderInterface
     * @throws DataProviderException
     */
    public function getReader(string $extension): FileFormatReaderInterface
    {
        foreach ($this->readers as $reader) {
            if ($reader->supports($extension)) {
                return $reader;
            }
        }

        throw new DataProviderException([
            'No file format reader is registered for the "{extension}" extension.',
            'extension' => $extension,
        ]);
    }
}
