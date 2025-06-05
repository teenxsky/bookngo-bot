<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Booking;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\House;
use App\Entity\User;
use InvalidArgumentException;

class DTOFactory
{
    /**
     * @param object $entity
     * @return BaseDTO
     */
    public function createFromEntity(object $entity): BaseDTO
    {
        return match (true) {
            $entity instanceof Booking => BookingDTO::createFromEntity($entity),
            $entity instanceof House   => HouseDTO::createFromEntity($entity),
            $entity instanceof City    => CityDTO::createFromEntity($entity),
            $entity instanceof Country => CountryDTO::createFromEntity($entity),
            $entity instanceof User    => UserDTO::createFromEntity($entity),
            default                    => throw new InvalidArgumentException(
                'No DTO class defined for entity of type ' . get_class($entity)
            ),
        };
    }

    /**
     * @param object[] $entities
     * @return BaseDTO[]
     */
    public function createFromEntities(array $entities): array
    {
        if (empty($entities)) {
            return [];
        }

        $firstEntity = reset($entities);

        return match (true) {
            $firstEntity instanceof Booking => BookingDTO::createFromEntities($entities),
            $firstEntity instanceof House   => HouseDTO::createFromEntities($entities),
            $firstEntity instanceof City    => CityDTO::createFromEntities($entities),
            $firstEntity instanceof Country => CountryDTO::createFromEntities($entities),
            $firstEntity instanceof User    => UserDTO::createFromEntities($entities),
            default                         => throw new InvalidArgumentException(
                'No DTO class defined for entity of type ' . get_class($firstEntity)
            ),
        };
    }

    /**
     * @param BaseDTO $dto
     * @param object $entity
     */
    public function mapToEntity(BaseDTO $dto, object $entity): void
    {
        match (true) {
            $dto instanceof BookingDTO && $entity instanceof Booking => $this->mapBookingDtoToEntity($dto, $entity),
            $dto instanceof HouseDTO   && $entity instanceof House   => $this->mapHouseDtoToEntity($dto, $entity),
            $dto instanceof CityDTO    && $entity instanceof City    => $this->mapCityDtoToEntity($dto, $entity),
            $dto instanceof CountryDTO && $entity instanceof Country => $this->mapCountryDtoToEntity($dto, $entity),
            $dto instanceof UserDTO    && $entity instanceof User    => $this->mapUserDtoToEntity($dto, $entity),
            default                                                  => throw new InvalidArgumentException(
                'Cannot map ' . get_class($dto) . ' to ' . get_class($entity)
            ),
        };
    }

    /**
     * @param BookingDTO $dto
     * @param Booking $entity
     */
    private function mapBookingDtoToEntity(BookingDTO $dto, Booking $entity): void
    {
        if ($dto->comment !== null) {
            $entity->setComment($dto->comment);
        }

        if ($dto->startDate !== null) {
            $entity->setStartDate($dto->startDate);
        }

        if ($dto->endDate !== null) {
            $entity->setEndDate($dto->endDate);
        }
    }

    /**
     * @param HouseDTO $dto
     * @param House $entity
     */
    private function mapHouseDtoToEntity(HouseDTO $dto, House $entity): void
    {
        if ($dto->address !== null) {
            $entity->setAddress($dto->address);
        }

        if ($dto->bedroomsCount !== null) {
            $entity->setBedroomsCount($dto->bedroomsCount);
        }

        if ($dto->pricePerNight !== null) {
            $entity->setPricePerNight($dto->pricePerNight);
        }

        if ($dto->hasAirConditioning !== null) {
            $entity->setHasAirConditioning($dto->hasAirConditioning);
        }

        if ($dto->hasWifi !== null) {
            $entity->setHasWifi($dto->hasWifi);
        }

        if ($dto->hasKitchen !== null) {
            $entity->setHasKitchen($dto->hasKitchen);
        }

        if ($dto->hasParking !== null) {
            $entity->setHasParking($dto->hasParking);
        }

        if ($dto->hasSeaView !== null) {
            $entity->setHasSeaView($dto->hasSeaView);
        }

        if ($dto->imageUrl !== null) {
            $entity->setImageUrl($dto->imageUrl);
        }
    }

    private function mapCityDtoToEntity(CityDTO $dto, City $entity): void
    {
        if ($dto->name !== null) {
            $entity->setName($dto->name);
        }
    }

    private function mapCountryDtoToEntity(CountryDTO $dto, Country $entity): void
    {
        if ($dto->name !== null) {
            $entity->setName($dto->name);
        }
    }

    private function mapUserDtoToEntity(UserDTO $dto, User $entity): void
    {
        if ($dto->phoneNumber !== null) {
            $entity->setPhoneNumber($dto->phoneNumber);
        }

        if ($dto->password !== null) {
            $entity->setPassword($dto->password);
        }

        if ($dto->telegramChatId !== null) {
            $entity->setTelegramChatId($dto->telegramChatId);
        }

        if ($dto->telegramUserId !== null) {
            $entity->setTelegramUserId($dto->telegramUserId);
        }

        if ($dto->telegramUsername !== null) {
            $entity->setTelegramUsername($dto->telegramUsername);
        }
    }
}
