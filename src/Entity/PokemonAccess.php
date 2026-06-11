<?php

namespace App\Entity;

use App\Repository\PokemonAccessRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PokemonAccessRepository::class)]
class PokemonAccess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $pokemonName = null;

    #[ORM\Column]
    private ?int $pokemonId = null;

    #[ORM\Column]
    private int $views = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastAccessedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPokemonName(): ?string
    {
        return $this->pokemonName;
    }

    public function setPokemonName(string $pokemonName): static
    {
        $this->pokemonName = $pokemonName;

        return $this;
    }

    public function getPokemonId(): ?int
    {
        return $this->pokemonId;
    }

    public function setPokemonId(int $pokemonId): static
    {
        $this->pokemonId = $pokemonId;

        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): static
    {
        $this->views = $views;

        return $this;
    }

    public function incrementViews(int $amount = 1): static
    {
        $this->views += $amount;

        return $this;
    }

    public function getLastAccessedAt(): ?\DateTimeInterface
    {
        return $this->lastAccessedAt;
    }

    public function setLastAccessedAt(?\DateTimeInterface $lastAccessedAt): static
    {
        $this->lastAccessedAt = $lastAccessedAt;

        return $this;
    }
}
