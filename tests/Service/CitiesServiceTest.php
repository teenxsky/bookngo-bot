<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\House;
use App\Repository\CitiesRepository;
use App\Repository\CountriesRepository;
use App\Service\CitiesService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CitiesServiceTest extends KernelTestCase
{
    private CitiesService $citiesService;

    /** @var CitiesRepository $citiesRepository */
    private CitiesRepository $citiesRepository;
    /** @var CountriesRepository $countriesRepository */
    private CountriesRepository $countriesRepository;

    private EntityManagerInterface $entityManager;

    /** @var City[] */
    private array $testCities = [];
    private Country $testCountry1;
    private Country $testCountry2;

    #[Override]
    public function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());

        $this->entityManager = static::getContainer()
            ->get('doctrine')
            ->getManager();
        $this->citiesRepository = $this->entityManager->getRepository(
            City::class
        );
        $this->countriesRepository = $this->entityManager->getRepository(
            Country::class
        );

        $this->citiesService = new CitiesService(
            $this->citiesRepository
        );

        $this->truncateTables();
        $this->createTestData();
    }

    private function truncateTables(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE cities RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE countries RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE houses RESTART IDENTITY CASCADE');
    }

    private function createTestData(): void
    {
        $this->testCountry1 = (new Country())
            ->setName('Test Country 1');

        $this->testCountry2 = (new Country())
            ->setName('Test Country 2');

        $this->entityManager->persist($this->testCountry1);
        $this->entityManager->persist($this->testCountry2);

        $this->testCities[] = (new City())
            ->setName('Test City 1')
            ->setCountry($this->testCountry1);

        $this->testCities[] = (new City())
            ->setName('Test City 2')
            ->setCountry($this->testCountry1);

        $house = (new House())
            ->setAddress('Test House')
            ->setBedroomsCount(2)
            ->setPricePerNight(1000)
            ->setHasAirConditioning(true)
            ->setHasWifi(true)
            ->setHasKitchen(true)
            ->setHasParking(false)
            ->setHasSeaView(true)
            ->setImageUrl('http://example.com/house1.jpg')
            ->setCity($this->testCities[0]);
        $this->testCities[0]->addHouse($house);

        foreach ($this->testCities as $city) {
            $this->entityManager->persist($city);
        }
        $this->entityManager->persist($house);
        $this->entityManager->flush();
    }

    private function assertCitiesEqual(City $expected, City $actual): void
    {
        $this->assertEquals($expected->getId(), $actual->getId());
        $this->assertEquals($expected->getName(), $actual->getName());
        $this->assertEquals($expected->getCountry()->getId(), $actual->getCountry()->getId());
    }

    public function testFindAllCities(): void
    {
        $cities = $this->citiesService->findAllCities();
        $this->assertCount(
            count($this->testCities),
            $cities
        );

        $this->assertCitiesEqual(
            $this->testCities[0],
            $cities[0]
        );
        $this->assertCitiesEqual(
            $this->testCities[1],
            $cities[1]
        );
    }

    public function testFindCityById(): void
    {
        $expectedCity = $this->testCities[0];
        $city         = $this->citiesService->findCityById($expectedCity->getId());

        $this->assertNotNull($city);
        $this->assertCitiesEqual($expectedCity, $city);

        $nonExistentCity = $this->citiesService->findCityById(999);
        $this->assertNull($nonExistentCity);

        $validationError = $this->citiesService->validateCityExists(999);
        $this->assertNotNull($validationError);
    }

    public function testFindCitiesByCountryId(): void
    {
        $cities = $this->citiesService->findCitiesByCountryId($this->testCities[0]->getCountry()->getId());
        $this->assertCount(
            count($this->testCities),
            $cities
        );

        $cities = $this->citiesService->findCitiesByCountryId(999);
        $this->assertCount(
            0,
            $cities
        );
    }

    public function testValidateCityCountry(): void
    {
        $city             = $this->testCities[0];
        $correctCountryId = $city->getCountry()->getId();
        $wrongCountryId   = $this->testCountry2->getId();

        $this->assertNull($this->citiesService->validateCityCountry(
            $city,
            $correctCountryId
        ));
        $this->assertNotNull($this->citiesService->validateCityCountry(
            $city,
            $wrongCountryId
        ));
    }

    public function testAddCity(): void
    {
        $newCity = (new City())
            ->setName('New Test City')
            ->setCountry($this->testCountry1);

        $this->citiesService->addCity($newCity);

        $cities = $this->citiesService->findAllCities();
        $this->assertCount(
            count($this->testCities) + 1,
            $cities
        );

        $addedCity = $this->citiesRepository->findCityById(
            count($this->testCities) + 1
        );
        $this->assertNotNull($addedCity);
        $this->assertEquals(
            $newCity->getName(),
            $addedCity->getName()
        );
        $this->assertEquals(
            $this->testCountry1->getId(),
            $addedCity->getCountry()->getId()
        );
    }

    public function testUpdateCity(): void
    {
        $city        = $this->testCities[0];
        $updatedCity = (new City())
            ->setName('Updated City Name')
            ->setCountry($this->testCountry2);

        $validationError = $this->citiesService->validateCityUpdate($city->getId());
        $this->assertNull($validationError);

        $this->citiesService->updateCity($updatedCity, $city->getId());

        $updatedCityResult = $this->citiesService->findCityById($city->getId());
        $this->assertEquals(
            $updatedCity->getName(),
            $updatedCityResult->getName()
        );
        $this->assertEquals($this->testCountry2->getId(), $updatedCityResult->getCountry()->getId());
    }

    public function testUpdateNonExistentCity(): void
    {
        $validationError = $this->citiesService->validateCityUpdate(999);
        $this->assertNotNull($validationError);
    }

    public function testDeleteCity(): void
    {
        $cityWithHouses  = $this->testCities[0];
        $validationError = $this->citiesService->validateCityDeletion($cityWithHouses->getId());
        $this->assertNotNull($validationError);

        $cityWithoutHouses = $this->testCities[1];
        $validationError   = $this->citiesService->validateCityDeletion($cityWithoutHouses->getId());
        $this->assertNull($validationError);

        $this->citiesService->deleteCity($cityWithoutHouses->getId());

        $cities = $this->citiesService->findAllCities();
        $this->assertCount(1, $cities);
    }

    public function testDeleteNonExistentCity(): void
    {
        $validationError = $this->citiesService->validateCityDeletion(999);
        $this->assertNotNull($validationError);
    }
}
