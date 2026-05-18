<?php

namespace App\Entity;

use App\Repository\PokemonSuggestionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PokemonSuggestionRepository::class)]
class PokemonSuggestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $pokemonName = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null; // 'nature', 'ability', 'item'

    #[ORM\Column(length: 100)]
    private ?string $value = null;

    #[ORM\Column]
    private int $votes = 0;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getVotes(): int
    {
        return $this->votes;
    }

    public function setVotes(int $votes): static
    {
        $this->votes = $votes;

        return $this;
    }

    public function incrementVotes(int $amount = 1): static
    {
        $this->votes += $amount;

        return $this;
    }
}
