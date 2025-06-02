<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Booking;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class BookingDTO extends BaseDTO
{
    #[Groups(['read'])]
    public ?int $id = null;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Groups(['read', 'write'])]
    public ?int $houseId = null;

    #[Assert\Length(
        max: 255,
        maxMessage: 'Comment cannot be longer than {{ limit }} characters'
    )]
    #[Assert\Type('string')]
    #[Groups(['read', 'write'])]
    public ?string $comment = null;

    #[Assert\NotNull]
    #[Assert\Type(DateTimeImmutable::class)]
    #[Groups(['read', 'write'])]
    public ?DateTimeImmutable $startDate = null;

    #[Assert\NotNull]
    #[Assert\Type(DateTimeImmutable::class)]
    #[Groups(['read', 'write'])]
    public ?DateTimeImmutable $endDate = null;

    #[Groups(['read'])]
    public ?string $phoneNumber = null;
    #[Groups(['read'])]
    public ?int $telegramChatId = null;
    #[Groups(['read'])]
    public ?int $telegramUserId = null;
    #[Groups(['read'])]
    public ?string $telegramUsername = null;

    /**
     * @return array
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'house_id'          => $this->houseId,
            'comment'           => $this->comment,
            'start_date'        => $this->startDate ? $this->startDate->format('Y-m-d') : null,
            'end_date'          => $this->endDate ? $this->endDate->format('Y-m-d') : null,
            'phone_number'      => $this->phoneNumber,
            'telegram_chat_id'  => $this->telegramChatId,
            'telegram_user_id'  => $this->telegramUserId,
            'telegram_username' => $this->telegramUsername,
        ];
    }

    /**
     * @param object $entity
     * @return self
     */
    #[Override]
    public static function createFromEntity(object $entity): self
    {
        if (!$entity instanceof Booking) {
            throw new InvalidArgumentException('Entity must be an instance of Booking');
        }

        $dto            = new self();
        $dto->id        = $entity->getId();
        $dto->houseId   = $entity->getHouse()?->getId();
        $dto->comment   = $entity->getComment();
        $dto->startDate = $entity->getStartDate();
        $dto->endDate   = $entity->getEndDate();

        if ($entity->getUser()) {
            $dto->phoneNumber      = $entity->getUser()->getPhoneNumber();
            $dto->telegramChatId   = $entity->getUser()->getTelegramChatId();
            $dto->telegramUserId   = $entity->getUser()->getTelegramUserId();
            $dto->telegramUsername = $entity->getUser()->getTelegramUsername();
        }

        return $dto;
    }

    /**
     * @param object[] $entities
     * @return self[]
     */
    #[Override]
    public static function createFromEntities(array $entities): array
    {
        return array_map(fn (Booking $entity) => self::createFromEntity($entity), $entities);
    }
}
