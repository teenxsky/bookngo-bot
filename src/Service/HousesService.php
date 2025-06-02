<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\HousesMessages;
use App\Entity\House;
use App\Repository\HousesRepository;
use DateTimeImmutable;
use DateTimeInterface;

class HousesService
{
    public function __construct(
        private HousesRepository $housesRepo
    ) {
    }

    /**
     * @param mixed $cityId
     * @param DateTimeInterface $startDate
     * @param DateTimeInterface $endDate
     * @return House[]
     */
    public function findAvailableHouses(
        ?int $cityId,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate
    ): array {
        return $this->housesRepo->findAvailableHouses(
            $cityId,
            DateTimeImmutable::createFromInterface($startDate),
            DateTimeImmutable::createFromInterface($endDate)
        );
    }

    /**
     * @param int $id
     * @return House|null
     */
    public function findHouseById(int $id): ?House
    {
        return $this->housesRepo->findHouseById($id);
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateHouseExists(int $id): ?string
    {
        $house = $this->findHouseById($id);
        if (!$house) {
            return HousesMessages::NOT_FOUND;
        }
        return null;
    }

    /**
     * @param House $house
     * @param int $cityId
     * @return string|null
     */
    public function validateHouseCity(House $house, int $cityId): ?string
    {
        if ($house->getCity()->getId() !== $cityId) {
            return HousesMessages::WRONG_CITY;
        }
        return null;
    }

    /**
     * @param House $house
     * @return void
     */
    public function addHouse(House $house): void
    {
        $this->housesRepo->addHouse($house);
    }

    /**
     * @param House $house
     * @return bool
     */
    public function checkHouseAvailability(House $house): bool
    {
        return $this->housesRepo->checkHouseAvailability($house);
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateHouseDeletion(int $id): ?string
    {
        $validationError = $this->validateHouseExists($id);
        if ($validationError) {
            return $validationError;
        }

        $house = $this->findHouseById($id);
        if (!$this->checkHouseAvailability($house)) {
            return HousesMessages::BOOKED;
        }

        return null;
    }

    /**
     * @param int $id
     * @return void
     */
    public function deleteHouse(int $id): void
    {
        $this->housesRepo->deleteHouseById($id);
    }

    /**
     * @return House[]
     */
    public function findAllHouses(): array
    {
        return $this->housesRepo->findAllHouses();
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateHouseReplacement(int $id): ?string
    {
        return $this->validateHouseExists($id);
    }

    /**
     * @param House $replacingHouse
     * @param int $id
     * @return void
     */
    public function replaceHouse(House $replacingHouse, int $id): void
    {
        $replacingHouse->setId($id);
        $this->housesRepo->updateHouse($replacingHouse);
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateHouseUpdate(int $id): ?string
    {
        return $this->validateHouseExists($id);
    }

    /**
     * @param House $updatedHouse
     * @param int $id
     * @return void
     */
    public function updateHouseFields(House $updatedHouse, int $id): void
    {
        $existingHouse = $this->findHouseById($id);
        $existingHouse
            ->setBedroomsCount(
                $updatedHouse->getBedroomsCount() ?? $existingHouse->getBedroomsCount()
            )
            ->setPricePerNight(
                $updatedHouse->getPricePerNight() ?? $existingHouse->getPricePerNight()
            )
            ->setHasAirConditioning(
                $updatedHouse->hasAirConditioning() ?? $existingHouse->hasAirConditioning()
            )
            ->setHasWifi(
                $updatedHouse->hasWifi() ?? $existingHouse->hasWifi()
            )
            ->setHasKitchen(
                $updatedHouse->hasKitchen() ?? $existingHouse->hasKitchen()
            )
            ->setHasParking(
                $updatedHouse->hasParking() ?? $existingHouse->hasParking()
            )
            ->setHasSeaView(
                $updatedHouse->hasSeaView() ?? $existingHouse->hasSeaView()
            );

        $this->housesRepo->updateHouse($existingHouse);
    }
}
