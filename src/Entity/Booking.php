<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BookingsRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingsRepository::class)]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: House::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?House $house = null;

    #[ORM\Column(length: 15)]
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

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Type('string')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Comment cannot be longer than {{ limit }} characters'
    )]
    private ?string $comment = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?DateTimeImmutable $startDate = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Type('int')]
    private ?int $telegramChatId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Type('int')]
    private ?int $telegramUserId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Type('string')]
    private ?string $telegramUsername = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
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

    public function getHouse(): ?House
    {
        return $this->house;
    }

    public function setHouse(House $house): static
    {
        $this->house = $house;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
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
     * @return ?array{
     *     id: int,
     *     phone_number: string,
     *     house_id: int,
     *     comment: string,
     *     start_date: string,
     *     end_date: string,
     *     telegram_chat_id: int,
     *     telegram_user_id: int,
     *     telegram_username: string,
     * }
     */
    public function toArray(): ?array
    {
        return [
            'id'                => $this->getId(),
            'phone_number'      => $this->getPhoneNumber(),
            'house_id'          => $this->getHouse()->getId(),
            'comment'           => $this->getComment(),
            'start_date'        => $this->getStartDate()->format('Y-m-d'),
            'end_date'          => $this->getEndDate()->format('Y-m-d'),
            'telegram_chat_id'  => $this->getTelegramChatId(),
            'telegram_user_id'  => $this->getTelegramUserId(),
            'telegram_username' => $this->getTelegramUsername(),
        ];
    }
}
