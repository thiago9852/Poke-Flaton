<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['username'], message: 'Este nome de usuário já está em uso.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $username = null;

    /**
     * @var array<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    /**
     * @var array<string>
     */
    #[ORM\Column]
    private array $unlockedTms = [];

    /**
     * @var array<string>
     */
    #[ORM\Column]
    private array $caughtPokemon = [];

    /**
     * @var array<string>
     */
    #[ORM\Column]
    private array $following = [];

    /**
     * @var array<string>
     */
    #[ORM\Column]
    private array $vivillonPatterns = [];

    /**
     * @var array<string> Up to 4 medal names to showcase on the trainer card
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $showcaseMedals = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cardTemplate = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apelido = null;

    #[ORM\Column(length: 255)]
    private ?string $regional = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->roles = ['ROLE_USER'];
        $this->unlockedTms = [];
        $this->caughtPokemon = [];
        $this->following = [];
        $this->vivillonPatterns = [];
        $this->showcaseMedals = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     *
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getUnlockedTms(): array
    {
        return $this->unlockedTms;
    }

    /**
     * @param array<string> $unlockedTms
     */
    public function setUnlockedTms(array $unlockedTms): static
    {
        $this->unlockedTms = $unlockedTms;
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getCaughtPokemon(): array
    {
        return $this->caughtPokemon;
    }

    /**
     * @param array<string> $caughtPokemon
     */
    public function setCaughtPokemon(array $caughtPokemon): static
    {
        $this->caughtPokemon = $caughtPokemon;
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getFollowing(): array
    {
        return $this->following;
    }

    /**
     * @param array<string> $following
     */
    public function setFollowing(array $following): static
    {
        $this->following = $following;
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getVivillonPatterns(): array
    {
        return $this->vivillonPatterns;
    }

    /**
     * @param array<string> $vivillonPatterns
     */
    public function setVivillonPatterns(array $vivillonPatterns): static
    {
        $this->vivillonPatterns = $vivillonPatterns;
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getShowcaseMedals(): array
    {
        if (!isset($this->showcaseMedals)) {
            $this->showcaseMedals = [];
        }

        return $this->showcaseMedals;
    }

    /**
     * @param array<string> $showcaseMedals
     */
    public function setShowcaseMedals(array $showcaseMedals): static
    {
        $this->showcaseMedals = array_slice($showcaseMedals, 0, 4);
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getCardTemplate(): ?string
    {
        return $this->cardTemplate;
    }

    public function setCardTemplate(?string $cardTemplate): static
    {
        $this->cardTemplate = $cardTemplate;
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

    public function getApelido(): ?string
    {
        return $this->apelido;
    }

    public function setApelido(?string $apelido): static
    {
        $this->apelido = $apelido;

        return $this;
    }

    public function getRegional(): ?string
    {
        return $this->regional;
    }

    public function setRegional(string $regional): static
    {
        $this->regional = $regional;

        return $this;
    }
}
