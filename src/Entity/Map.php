<?php

namespace App\Entity;

use App\Repository\MapRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MapRepository::class)]
class Map
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $imagePath = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'map', targetEntity: MapPokemon::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $pokemons;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->pokemons = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;
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

    /**
     * @return Collection<int, MapPokemon>
     */
    public function getPokemons(): Collection
    {
        return $this->pokemons;
    }

    public function addPokemon(MapPokemon $pokemon): static
    {
        if (!$this->pokemons->contains($pokemon)) {
            $this->pokemons->add($pokemon);
            $pokemon->setMap($this);
        }
        return $this;
    }

    public function removePokemon(MapPokemon $pokemon): static
    {
        if ($this->pokemons->removeElement($pokemon)) {
            // set the owning side to null (unless already changed)
            if ($pokemon->getMap() === $this) {
                $pokemon->setMap(null);
            }
        }
        return $this;
    }
}
