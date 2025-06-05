<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\User;
use InvalidArgumentException;
use Override;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class UserDTO implements BaseDTO
{
    public ?int $id = null;

    #[Assert\NotNull]
    #[Assert\Regex(
        pattern: '/^\+(?:[0-9]{1,3})(?:[0-9]{7,14})$/',
        message: 'Invalid phone number format'
    )]
    #[Assert\Length(
        min: 7,
        max: 15,
        minMessage: 'Phone number must be at least {{ limit }} characters long',
        maxMessage: 'Phone number cannot be longer than {{ limit }} characters'
    )]
    #[Assert\Type('string')]
    #[Groups(['read', 'write'])]
    public ?string $phoneNumber = null;

    #[Assert\NotNull]
    #[Groups(['write'])]
    public ?string $password = null;

    #[Groups(['read'])]
    public array $roles = ['ROLE_USER'];

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
            'phone_number'      => $this->phoneNumber,
            'roles'             => $this->roles,
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
        if (!$entity instanceof User) {
            throw new InvalidArgumentException('Entity must be an instance of User');
        }

        $dto                   = new self();
        $dto->id               = $entity->getId();
        $dto->phoneNumber      = $entity->getPhoneNumber();
        $dto->roles            = $entity->getRoles();
        $dto->telegramChatId   = $entity->getTelegramChatId();
        $dto->telegramUserId   = $entity->getTelegramUserId();
        $dto->telegramUsername = $entity->getTelegramUsername();

        return $dto;
    }

    /**
     * @param object[] $entities
     * @return self[]
     */
    #[Override]
    public static function createFromEntities(array $entities): array
    {
        return array_map(fn (User $entity) => self::createFromEntity($entity), $entities);
    }
}
