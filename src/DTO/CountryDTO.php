<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Country;
use InvalidArgumentException;
use Override;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class CountryDTO extends BaseDTO
{
    #[Groups(['read'])]
    public ?int $id = null;

    #[Assert\NotNull]
    #[Assert\Type('string')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Country name cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['read', 'write'])]
    public ?string $name = null;

    /**
     * @return array
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
        ];
    }

    /**
     * @param object $entity
     * @return self
     */
    #[Override]
    public static function createFromEntity(object $entity): self
    {
        if (!$entity instanceof Country) {
            throw new InvalidArgumentException('Entity must be an instance of Country');
        }

        $dto       = new self();
        $dto->id   = $entity->getId();
        $dto->name = $entity->getName();

        return $dto;
    }

    /**
     * @param object[] $entities
     * @return self[]
     */
    #[Override]
    public static function createFromEntities(array $entities): array
    {
        return array_map(fn (Country $entity) => self::createFromEntity($entity), $entities);
    }
}
