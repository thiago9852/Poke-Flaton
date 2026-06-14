<?php

namespace App\Entity;

use App\Repository\CardTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardTemplateRepository::class)]
class CardTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\Column(length: 255)]
    private ?string $requirement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reqMedal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reqTier = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $reqGoldCount = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $reqRankType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $reqRankPos = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDefault = false;

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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getRequirement(): ?string
    {
        return $this->requirement;
    }

    public function setRequirement(string $requirement): static
    {
        $this->requirement = $requirement;

        return $this;
    }

    public function getReqMedal(): ?string
    {
        return $this->reqMedal;
    }

    public function setReqMedal(?string $reqMedal): static
    {
        $this->reqMedal = $reqMedal;

        return $this;
    }

    public function getReqTier(): ?string
    {
        return $this->reqTier;
    }

    public function setReqTier(?string $reqTier): static
    {
        $this->reqTier = $reqTier;

        return $this;
    }

    public function getReqGoldCount(): ?int
    {
        return $this->reqGoldCount;
    }

    public function setReqGoldCount(?int $reqGoldCount): static
    {
        $this->reqGoldCount = $reqGoldCount;

        return $this;
    }

    public function getReqRankType(): ?string
    {
        return $this->reqRankType;
    }

    public function setReqRankType(?string $reqRankType): static
    {
        $this->reqRankType = $reqRankType;

        return $this;
    }

    public function getReqRankPos(): ?int
    {
        return $this->reqRankPos;
    }

    public function setReqRankPos(?int $reqRankPos): static
    {
        $this->reqRankPos = $reqRankPos;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }
}
