<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'user_id')]
    private ?int $id = null;

    #[ORM\Column(name: 'phone_number', length: 15, unique: true, nullable: true)]
    #[Assert\NotNull]
    #[Assert\Regex(
        pattern: '/^\+?[0-9]{1,3}?[0-9]{7,14}$/',
        message: 'Invalid phone number format'
    )]
    #[Assert\Length(
        min: 7,
        max: 15,
        minMessage: 'Phone number must be at least {{ limit }} characters long',
        maxMessage: 'Phone number cannot be longer than {{ limit }} characters'
    )]
    #[Assert\Type('string')]
    private ?string $phoneNumber = null;

    #[ORM\Column(name: 'roles', type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(name: 'password', nullable: true)]
    #[Assert\NotNull]
    private ?string $password = null;

    #[ORM\Column(name: 'token_version', type: 'integer', options: ['default' => 0])]
    private int $tokenVersion = 0;

    #[ORM\Column(name: 'telegram_chat_id', nullable: true)]
    #[Assert\Type('int')]
    private ?int $telegramChatId = null;

    #[ORM\Column(name: 'telegram_user_id', nullable: true)]
    #[Assert\Type('int')]
    private ?int $telegramUserId = null;

    #[ORM\Column(name: 'telegram_username', length: 255, nullable: true)]
    #[Assert\Type('string')]
    private ?string $telegramUsername = null;

    public function getTelegramChatId(): ?int
    {
        return $this->telegramChatId;
    }

    public function setTelegramChatId(?int $telegramChatId): static
    {
        $this->telegramChatId = $telegramChatId;
        return $this;
    }

    public function getTelegramUserId(): ?int
    {
        return $this->telegramUserId;
    }

    public function setTelegramUserId(?int $telegramUserId): static
    {
        $this->telegramUserId = $telegramUserId;
        return $this;
    }

    public function getTelegramUsername(): ?string
    {
        return $this->telegramUsername;
    }

    public function setTelegramUsername(?string $telegramUsername): static
    {
        $this->telegramUsername = $telegramUsername;
        return $this;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setTokenVersion(int $tokenVersion): static
    {
        $this->tokenVersion = $tokenVersion;

        return $this;
    }

    public function getTokenVersion(): int
    {
        return $this->tokenVersion;
    }

    public function incrementTokenVersion(): void
    {
        $this->tokenVersion++;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    #[Override]
    public function getUserIdentifier(): string
    {
        return (string) $this->phoneNumber;
    }

    /**
     * @see UserInterface
     * @return list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    #[Override]
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
    #[Override]
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return array{id: int|null, phone_number: string|null, roles: array, telegram_chat_id: int|null, telegram_user_id: int|null, telegram_username: string|null}
     */
    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'phone_number'      => $this->phoneNumber,
            'roles'             => $this->roles,
            'telegram_chat_id'  => $this->telegramChatId,
            'telegram_user_id'  => $this->telegramUserId,
            'telegram_username' => $this->telegramUsername,
        ];
    }
}
