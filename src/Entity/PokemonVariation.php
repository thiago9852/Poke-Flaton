<?php

namespace App\Entity;

use App\Repository\PokemonVariationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PokemonVariationRepository::class)]
#[ORM\Table(name: 'pokemon_variation')]
class PokemonVariation
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $baseId = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getBaseId(): ?int
    {
        return $this->baseId;
    }

    public function setBaseId(int $baseId): static
    {
        $this->baseId = $baseId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
