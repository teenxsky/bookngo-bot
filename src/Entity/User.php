<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(
    fields: ['phoneNumber']
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'user_id')]
    private ?int $id = null;

    #[ORM\Column(name: 'phone_number', length: 15, unique: true, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(name: 'roles', type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(name: 'password', nullable: true)]
    private ?string $password = null;

    #[ORM\Column(name: 'token_version', type: 'integer', options: ['default' => 0])]
    private int $tokenVersion = 0;

    #[ORM\Column(name: 'telegram_chat_id', nullable: true)]
    private ?int $telegramChatId = null;

    #[ORM\Column(name: 'telegram_user_id', nullable: true)]
    private ?int $telegramUserId = null;

    #[ORM\Column(name: 'telegram_username', length: 255, nullable: true)]
    private ?string $telegramUsername = null;

    #[ORM\OneToMany(
        mappedBy: 'user',
        targetEntity: Booking::class,
        orphanRemoval: true,
        cascade: ['remove']
    )]
    private Collection $bookings;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings[] = $booking;
            $booking->setUser($this);
        }

        return $this;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getUser() === $this) {
                $booking->setUser(null);
            }
        }

        return $this;
    }

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

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
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

    public function __toString(): string
    {
        return $this->getTelegramUsername() ?? $this->phoneNumber ?? 'Unknown';
    }
}
