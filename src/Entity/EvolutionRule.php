<?php

namespace App\Entity;

use App\Repository\EvolutionRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvolutionRuleRepository::class)]
class EvolutionRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $basePokemon = null;

    #[ORM\Column(length: 100)]
    private ?string $evolvedPokemon = null;

    #[ORM\Column(length: 100)]
    private ?string $method = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBasePokemon(): ?string
    {
        return $this->basePokemon;
    }

    public function setBasePokemon(string $basePokemon): static
    {
        $this->basePokemon = $basePokemon;

        return $this;
    }

    public function getEvolvedPokemon(): ?string
    {
        return $this->evolvedPokemon;
    }

    public function setEvolvedPokemon(string $evolvedPokemon): static
    {
        $this->evolvedPokemon = $evolvedPokemon;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }
}
