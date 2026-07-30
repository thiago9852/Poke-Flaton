<?php

namespace App\Entity;

use App\Repository\MapPortalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MapPortalRepository::class)]
class MapPortal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Map::class, inversedBy: 'portals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Map $parentMap = null;

    #[ORM\ManyToOne(targetEntity: Map::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Map $targetMap = null;

    #[ORM\Column(type: 'float')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    private ?float $longitude = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentMap(): ?Map
    {
        return $this->parentMap;
    }

    public function setParentMap(?Map $parentMap): static
    {
        $this->parentMap = $parentMap;
        return $this;
    }

    public function getTargetMap(): ?Map
    {
        return $this->targetMap;
    }

    public function setTargetMap(?Map $targetMap): static
    {
        $this->targetMap = $targetMap;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;
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
