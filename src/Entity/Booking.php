<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BookingsRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingsRepository::class)]
#[ORM\Table(name: 'bookings')]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: House::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?House $house = null;

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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

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
            'phone_number'      => $this->getUser()?->getPhoneNumber() ?? null,
            'house_id'          => $this->getHouse()?->getId()         ?? null,
            'comment'           => $this->getComment(),
            'start_date'        => $this->getStartDate()->format('Y-m-d'),
            'end_date'          => $this->getEndDate()->format('Y-m-d'),
            'telegram_chat_id'  => $this->getUser()?->getTelegramChatId()   ?? null,
            'telegram_user_id'  => $this->getUser()?->getTelegramUserId()   ?? null,
            'telegram_username' => $this->getUser()?->getTelegramUsername() ?? null,
        ];
    }
}
