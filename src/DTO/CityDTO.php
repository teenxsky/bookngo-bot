<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\City;
use InvalidArgumentException;
use Override;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class CityDTO extends BaseDTO
{
    #[Groups(['read'])]
    public ?int $id = null;

    #[Assert\NotNull]
    #[Assert\Type('string')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'City name cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['read', 'write'])]
    public ?string $name = null;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Groups(['read', 'write'])]
    public ?int $countryId = null;

    /**
     *
     * @return array
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'country_id' => $this->countryId,
        ];
    }

    /**
     * @param object $entity
     * @return self
     */
    #[Override]
    public static function createFromEntity(object $entity): self
    {
        if (!$entity instanceof City) {
            throw new InvalidArgumentException('Entity must be an instance of City');
        }

        $dto            = new self();
        $dto->id        = $entity->getId();
        $dto->name      = $entity->getName();
        $dto->countryId = $entity->getCountry()?->getId();

        return $dto;
    }

    /**
     * @param object[] $entities
     * @return self[]
     */
    #[Override]
    public static function createFromEntities(array $entities): array
    {
        return array_map(fn (City $entity) => self::createFromEntity($entity), $entities);
    }
}
