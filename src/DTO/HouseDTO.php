<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\House;
use InvalidArgumentException;
use Override;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class HouseDTO extends BaseDTO
{
    #[Groups(['read'])]
    public ?int $id = null;

    #[Assert\NotNull]
    #[Assert\Type('string')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Address cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['read', 'write'])]
    public ?string $address = null;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\Range(min: 1, max: 20)]
    #[Groups(['read', 'write'])]
    public ?int $bedroomsCount = null;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\Range(min: 100, max: 100000)]
    #[Groups(['read', 'write'])]
    public ?int $pricePerNight = null;

    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    #[Groups(['read', 'write'])]
    public ?bool $hasAirConditioning = null;

    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    #[Groups(['read', 'write'])]
    public ?bool $hasWifi = null;

    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    #[Groups(['read', 'write'])]
    public ?bool $hasKitchen = null;

    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    #[Groups(['read', 'write'])]
    public ?bool $hasParking = null;

    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    #[Groups(['read', 'write'])]
    public ?bool $hasSeaView = null;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Groups(['read', 'write'])]
    public ?int $cityId = null;

    #[Assert\Url(
        message: 'The image URL {{ value }} is not a valid URL',
        requireTld: true
    )]
    #[Groups(['read', 'write'])]
    public ?string $imageUrl = null;

    /**
     * @return array
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'id'                   => $this->id,
            'address'              => $this->address,
            'bedrooms_count'       => $this->bedroomsCount,
            'price_per_night'      => $this->pricePerNight,
            'has_air_conditioning' => $this->hasAirConditioning,
            'has_wifi'             => $this->hasWifi,
            'has_kitchen'          => $this->hasKitchen,
            'has_parking'          => $this->hasParking,
            'has_sea_view'         => $this->hasSeaView,
            'city_id'              => $this->cityId,
            'image_url'            => $this->imageUrl,
        ];
    }

    /**
     * @param object $entity
     * @return self
     */
    #[Override]
    public static function createFromEntity(object $entity): self
    {
        if (!$entity instanceof House) {
            throw new InvalidArgumentException('Entity must be an instance of House');
        }

        $dto                     = new self();
        $dto->id                 = $entity->getId();
        $dto->address            = $entity->getAddress();
        $dto->bedroomsCount      = $entity->getBedroomsCount();
        $dto->pricePerNight      = $entity->getPricePerNight();
        $dto->hasAirConditioning = $entity->hasAirConditioning();
        $dto->hasWifi            = $entity->hasWifi();
        $dto->hasKitchen         = $entity->hasKitchen();
        $dto->hasParking         = $entity->hasParking();
        $dto->hasSeaView         = $entity->hasSeaView();
        $dto->cityId             = $entity->getCity()?->getId();
        $dto->imageUrl           = $entity->getImageUrl();

        return $dto;
    }

    /**
     * @param object[] $entities
     * @return self[]
     */
    #[Override]
    public static function createFromEntities(array $entities): array
    {
        return array_map(fn (House $entity) => self::createFromEntity($entity), $entities);
    }
}
