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
     * @return array{
     *    city: City|null,
     *    error: string|null
     * }
     */
    public function findCityById(int $id): array
    {
        $city = $this->cityRepo->findCityById($id);
        if (!$city) {
            return [
                'city'  => null,
                'error' => CitiesMessages::NOT_FOUND
            ];
        }
        return [
            'city'  => $city,
            'error' => null
        ];
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
     * @param City $updatedCity
     * @param int $id
     * @return string|null
     */
    public function updateCity(City $updatedCity, int $id): ?string
    {
        $result = $this->findCityById($id);
        if ($result['error'] !== null) {
            return $result['error'];
        }

        $existingCity = $result['city'];
        $existingCity
            ->setName($updatedCity->getName())
            ->setCountry($updatedCity->getCountry());

        $this->cityRepo->updateCity($existingCity);
        return null;
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function deleteCity(int $id): ?string
    {
        $result = $this->findCityById($id);
        if ($result['error'] !== null) {
            return $result['error'];
        }

        if ($result['city']->getHouses()->count() > 0) {
            return CitiesMessages::HAS_HOUSES;
        }

        $this->cityRepo->deleteCityById($id);
        return null;
    }
}
