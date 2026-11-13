<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_username', columns: ['username'])]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ROLE_USER = 'ROLE_USER';
    public const string ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $firstName;

    #[ORM\Column(length: 80)]
    private string $lastName;

    /** Always stored in lower case. */
    #[ORM\Column(length: 30)]
    private string $username;

    #[ORM\Column]
    private string $password = '';

    /** Unused. Reserved for a future extension; never collected or shown. */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    /** Set after an admin reset: the user has to choose a new password on the next login. */
    #[ORM\Column(options: ['default' => false])]
    private bool $mustChangePassword = false;

    /**
     * Every access token carries the version it was issued under and is refused once the
     * version has moved on. Bumped whenever the credentials change, so that a stolen or
     * forgotten session ends immediately instead of when its token expires.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $sessionVersion = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $firstName, string $lastName, string $username)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->setUsername($username);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = self::normalizeUsername($username);
    }

    public static function normalizeUsername(string $username): string
    {
        return mb_strtolower(trim($username));
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        \assert('' !== $this->username);

        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, self::ROLE_USER]));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void
    {
        $this->roles = array_values(array_unique($roles));
    }

    public function isAdmin(): bool
    {
        return \in_array(self::ROLE_ADMIN, $this->roles, true);
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChangePassword): void
    {
        $this->mustChangePassword = $mustChangePassword;
    }

    public function getSessionVersion(): int
    {
        return $this->sessionVersion;
    }

    /** Ends every session that exists right now. Tokens issued afterwards are valid again. */
    public function invalidateSessions(): void
    {
        ++$this->sessionVersion;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function eraseCredentials(): void
    {
    }
}
