<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\City;
use App\Entity\Country;
use App\Repository\CountriesRepository;
use App\Service\CountriesService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CountriesServiceTest extends KernelTestCase
{
    private CountriesService $countryService;
    /** @var CountriesRepository $countryRepository */
    private CountriesRepository $countryRepository;
    private EntityManagerInterface $entityManager;

    /** @var Country[] */
    private array $testCountries = [];

    #[Override]
    public function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());

        $this->entityManager = static::getContainer()
            ->get('doctrine')
            ->getManager();
        $this->countryRepository = $this->entityManager->getRepository(
            Country::class
        );

        $this->countryService = new CountriesService(
            $this->countryRepository
        );

        $this->truncateTables();
        $this->createTestData();
    }

    private function truncateTables(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement(
            'TRUNCATE TABLE countries RESTART IDENTITY CASCADE'
        );
        $connection->executeStatement(
            'TRUNCATE TABLE cities RESTART IDENTITY CASCADE'
        );
    }

    private function createTestData(): void
    {
        $this->testCountries[] = (new Country())
            ->setName('Test Country 1');

        $this->testCountries[] = (new Country())
            ->setName('Test Country 2');

        $testCity = (new City())
            ->setName('Test City')
            ->setCountry($this->testCountries[0]);
        $this->entityManager->persist($testCity);
        $this->testCountries[0]->addCity($testCity);

        foreach ($this->testCountries as $country) {
            $this->entityManager->persist($country);
        }
        $this->entityManager->flush();
    }

    private function assertCountriesEqual(Country $expected, Country $actual): void
    {
        $this->assertEquals($expected->getId(), $actual->getId());
        $this->assertEquals($expected->getName(), $actual->getName());
    }

    public function testFindAllCountries(): void
    {
        $countries = $this->countryService->findAllCountries();
        $this->assertCount(2, $countries);

        $this->assertCountriesEqual($this->testCountries[0], $countries[0]);
        $this->assertCountriesEqual($this->testCountries[1], $countries[1]);
    }

    public function testFindCountryById(): void
    {
        $expectedCountry = $this->testCountries[0];
        $country         = $this->countryService->findCountryById($expectedCountry->getId());

        $this->assertNotNull($country);
        $this->assertCountriesEqual($expectedCountry, $country);

        $nonExistentCountry = $this->countryService->findCountryById(999);
        $this->assertNull($nonExistentCountry);

        $validationError = $this->countryService->validateCountryExists(999);
        $this->assertNotNull($validationError);
    }

    public function testAddCountry(): void
    {
        $newCountry = (new Country())
            ->setName('New Test Country');

        $this->countryService->addCountry($newCountry);

        $countries = $this->countryService->findAllCountries();
        $this->assertCount(
            count($this->testCountries) + 1,
            $countries
        );

        $addedCountry = $this->countryRepository->findCountryById(
            count($this->testCountries) + 1
        );
        $this->assertNotNull($addedCountry);
        $this->assertEquals(
            $newCountry->getName(),
            $addedCountry->getName()
        );
    }

    public function testUpdateCountry(): void
    {
        $country        = $this->testCountries[0];
        $updatedCountry = (new Country())
            ->setName('Updated Country Name');

        $validationError = $this->countryService->validateCountryUpdate($country->getId());
        $this->assertNull($validationError);

        $this->countryService->updateCountry($updatedCountry, $country->getId());

        $updatedCountryResult = $this->countryService->findCountryById($country->getId());
        $this->assertEquals(
            $updatedCountry->getName(),
            $updatedCountryResult->getName()
        );
    }

    public function testUpdateNonExistentCountry(): void
    {
        $validationError = $this->countryService->validateCountryUpdate(999);
        $this->assertNotNull($validationError);
    }

    public function testDeleteCountry(): void
    {
        $countryWithCities = $this->testCountries[0];
        $validationError   = $this->countryService->validateCountryDeletion($countryWithCities->getId());
        $this->assertNotNull($validationError);

        $countryWithoutCities = $this->testCountries[1];
        $validationError      = $this->countryService->validateCountryDeletion($countryWithoutCities->getId());
        $this->assertNull($validationError);

        $this->countryService->deleteCountry($countryWithoutCities->getId());

        $countries = $this->countryService->findAllCountries();
        $this->assertCount(1, $countries);
    }

    public function testDeleteNonExistentCountry(): void
    {
        $validationError = $this->countryService->validateCountryDeletion(999);
        $this->assertNotNull($validationError);
    }
}
