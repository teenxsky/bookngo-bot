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
     * @return array{
     *    house:House|null,
     *    error:string|null
     * }
     */
    public function findHouseById(int $id): array
    {
        $house = $this->housesRepo->findHouseById($id);
        if (!$house) {
            return [
              'house' => null,
              'error' => HousesMessages::NOT_FOUND
            ];
        }
        return [
          'house' => $house,
          'error' => null
        ];
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
    public function deleteHouse(int $id): ?string
    {
        $result = $this->findHouseById($id);
        $house  = $result['house'];
        $error  = $result['error'];

        if ($error !== null) {
            return $error;
        }

        if (!$this->checkHouseAvailability($house)) {
            return HousesMessages::BOOKED;
        }

        $this->housesRepo->deleteHouseById($id);
        return null;
    }

    /**
     * @return House[]
     */
    public function findAllHouses(): array
    {
        return $this->housesRepo->findAllHouses();
    }

    /**
     * @param House $replacingHouse
     * @param int $id
     * @return string|null
     */
    public function replaceHouse(House $replacingHouse, int $id): ?string
    {
        $error = $this->findHouseById($id)['error'];
        if ($error !== null) {
            return $error;
        }

        $replacingHouse->setId($id);
        $this->housesRepo->updateHouse($replacingHouse);
        return null;
    }

    /**
     * @param House $updatedHouse
     * @param int $id
     * @return string|null
     */
    public function updateHouseFields(House $updatedHouse, int $id): ?string
    {
        $result = $this->findHouseById($id);
        if ($result['error'] !== null) {
            return $result['error'];
        }

        $existingHouse = $result['house'];
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
        return null;
    }
}
