<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\CountriesMessages;
use App\Entity\Country;
use App\Repository\CountriesRepository;

class CountriesService
{
    public function __construct(
        private CountriesRepository $countryRepo
    ) {
    }

    /**
     * @return Country[]
     */
    public function findAllCountries(): array
    {
        return $this->countryRepo->findAllCountries();
    }

    /**
     * @param int $id
     * @return Country|null
     */
    public function findCountryById(int $id): ?Country
    {
        return $this->countryRepo->findCountryById($id);
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateCountryExists(int $id): ?string
    {
        $country = $this->findCountryById($id);
        if (!$country) {
            return CountriesMessages::NOT_FOUND;
        }
        return null;
    }

    public function addCountry(Country $country): void
    {
        $this->countryRepo->addCountry($country);
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateCountryUpdate(int $id): ?string
    {
        return $this->validateCountryExists($id);
    }

    /**
     * @param Country $updatedCountry
     * @param int $id
     * @return void
     */
    public function updateCountry(Country $updatedCountry, int $id): void
    {
        $existingCountry = $this->findCountryById($id);
        $existingCountry->setName($updatedCountry->getName());

        $this->countryRepo->updateCountry($existingCountry);
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateCountryDeletion(int $id): ?string
    {
        $validationError = $this->validateCountryExists($id);
        if ($validationError) {
            return $validationError;
        }

        $country = $this->findCountryById($id);
        if ($country->getCities()->count() > 0) {
            return CountriesMessages::HAS_CITIES;
        }

        return null;
    }

    /**
     * @param int $id
     * @return void
     */
    public function deleteCountry(int $id): void
    {
        $this->countryRepo->deleteCountryById($id);
    }
}
