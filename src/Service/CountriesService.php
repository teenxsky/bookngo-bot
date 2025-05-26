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
     * @return array{
     *    country: Country|null,
     *    error: string|null
     * }
     */
    public function findCountryById(int $id): array
    {
        $country = $this->countryRepo->findCountryById($id);
        if (!$country) {
            return [
                'country' => null,
                'error'   => CountriesMessages::NOT_FOUND
            ];
        }
        return [
            'country' => $country,
            'error'   => null
        ];
    }

    public function addCountry(Country $country): void
    {
        $this->countryRepo->addCountry($country);
    }

    /**
     * @param Country $updatedCountry
     * @param int $id
     * @return string|null
     */
    public function updateCountry(Country $updatedCountry, int $id): ?string
    {
        $result = $this->findCountryById($id);
        if ($result['error'] !== null) {
            return $result['error'];
        }

        $existingCountry = $result['country'];
        $existingCountry->setName($updatedCountry->getName());

        $this->countryRepo->updateCountry($existingCountry);
        return null;
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function deleteCountry(int $id): ?string
    {
        $result = $this->findCountryById($id);
        if ($result['error'] !== null) {
            return $result['error'];
        }

        if ($result['country']->getCities()->count() > 0) {
            return CountriesMessages::HAS_CITIES;
        }

        $this->countryRepo->deleteCountryById($id);
        return null;
    }
}
