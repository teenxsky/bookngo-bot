<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\CitiesMessages;
use App\Entity\City;
use App\Repository\CitiesRepository;

class CitiesService
{
    public function __construct(
        private CitiesRepository $cityRepo,
    ) {
    }

    /**
     * @return City[]
     */
    public function findAllCities(): array
    {
        return $this->cityRepo->findAllCities();
    }

    /**
     * @param int $id
     * @return City|null
     */
    public function findCityById(int $id): ?City
    {
        return $this->cityRepo->findCityById($id);
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateCityExists(int $id): ?string
    {
        $city = $this->findCityById($id);
        if (!$city) {
            return CitiesMessages::NOT_FOUND;
        }
        return null;
    }

    /**
     * @param int $countryId
     * @return City[]
     */
    public function findCitiesByCountryId(int $countryId): array
    {
        return $this->cityRepo->findCitiesByCountryId($countryId);
    }

    /**
     * @param City $city
     * @param int $countryId
     * @return string|null
     */
    public function validateCityCountry(City $city, int $countryId): ?string
    {
        if ($city->getCountry()->getId() !== $countryId) {
            return CitiesMessages::WRONG_COUNTRY;
        }
        return null;
    }

    /**
     * @param City $city
     * @return void
     */
    public function addCity(City $city): void
    {
        $this->cityRepo->addCity($city);
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateCityUpdate(int $id): ?string
    {
        return $this->validateCityExists($id);
    }

    /**
     * @param City $updatedCity
     * @param int $id
     * @return void
     */
    public function updateCity(City $updatedCity, int $id): void
    {
        $existingCity = $this->findCityById($id);
        $existingCity
            ->setName($updatedCity->getName())
            ->setCountry($updatedCity->getCountry());

        $this->cityRepo->updateCity($existingCity);
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateCityDeletion(int $id): ?string
    {
        $validationError = $this->validateCityExists($id);
        if ($validationError) {
            return $validationError;
        }

        $city = $this->findCityById($id);
        if ($city->getHouses()->count() > 0) {
            return CitiesMessages::HAS_HOUSES;
        }

        return null;
    }

    /**
     * @param int $id
     * @return void
     */
    public function deleteCity(int $id): void
    {
        $this->cityRepo->deleteCityById($id);
    }
}
