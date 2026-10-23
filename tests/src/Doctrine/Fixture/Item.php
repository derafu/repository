<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository\Doctrine\Fixture;

use Doctrine\ORM\Mapping as ORM;

/**
 * Database side of the Doctrine tests: the same rows are also given, as plain
 * arrays, to a repository.
 */
#[ORM\Entity]
#[ORM\Table(name: 'item')]
class Item
{
    public function __construct(
        #[ORM\Id, ORM\Column(type: 'integer')]
        private int $id,
        #[ORM\Column(type: 'string')]
        private string $name,
        #[ORM\Column(type: 'integer')]
        private int $price,
        #[ORM\Column(type: 'boolean')]
        private bool $active,
        #[ORM\Column(type: 'string', nullable: true)]
        private ?string $note = null,
        #[ORM\Column(type: 'integer', nullable: true)]
        private ?int $stock = null,
        #[ORM\Column(type: 'string', nullable: true)]
        private ?string $tag = null,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }
}
