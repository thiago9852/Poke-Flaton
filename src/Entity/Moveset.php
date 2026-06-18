<?php

namespace App\Entity;

use App\Repository\MovesetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovesetRepository::class)]
class Moveset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $pokemonName = null;

    #[ORM\Column]
    private ?int $pokemonId = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null; // 'padrao', 'pvp', 'dg'

    #[ORM\Column(type: Types::JSON)]
    private array $moves = []; // array 4 - 10 moves

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ability = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $heldItem = null;

    #[ORM\Column(length: 100)]
    private ?string $nature = null;

    #[ORM\Column]
    private int $votes = 0;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $author = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->votes = 0;
    }

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getMoves(): array
    {
        return $this->moves;
    }

    public function setMoves(array $moves): static
    {
        $this->moves = $moves;
        return $this;
    }

    public function getAbility(): ?string
    {
        return $this->ability;
    }

    public function setAbility(?string $ability): static
    {
        $this->ability = $ability;
        return $this;
    }

    public function getHeldItem(): ?string
    {
        return $this->heldItem;
    }

    public function setHeldItem(?string $heldItem): static
    {
        $this->heldItem = $heldItem;
        return $this;
    }

    public function getNature(): ?string
    {
        return $this->nature;
    }

    public function setNature(string $nature): static
    {
        $this->nature = $nature;
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

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function incrementVotes(): static
    {
        $this->votes++;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
