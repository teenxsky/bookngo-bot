<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\House;
use App\Repository\HousesRepository;
use App\Service\HousesService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class HousesServiceTest extends KernelTestCase
{
    private HousesService $housesService;
    /** @var HousesRepository $housesRepository */
    private HousesRepository $housesRepository;
    private EntityManagerInterface $entityManager;

    /** @var House[] */
    private array $testHouses = [];
    private City $testCity1;
    private City $testCity2;
    private Country $testCountry1;
    private Country $testCountry2;

    #[Override]
    public function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());

        $this->entityManager    = static::getContainer()->get('doctrine')->getManager();
        $this->housesRepository = $this->entityManager->getRepository(House::class);
        $this->housesService    = new HousesService($this->housesRepository);

        $this->truncateTables();
        $this->createTestData();
    }

    private function truncateTables(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE house RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE city RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE country RESTART IDENTITY CASCADE');
    }

    private function createTestData(): void
    {
        $this->testCountry1 = (new Country())
            ->setName('Test Country 1');

        $this->testCountry2 = (new Country())
            ->setName('Test Country 2');

        $this->entityManager->persist($this->testCountry1);
        $this->entityManager->persist($this->testCountry2);

        $this->testCity1 = (new City())
            ->setName('Test City 1')
            ->setCountry($this->testCountry1);

        $this->testCity2 = (new City())
            ->setName('Test City 2')
            ->setCountry($this->testCountry2);

        $this->entityManager->persist($this->testCity1);
        $this->entityManager->persist($this->testCity2);

        $this->testHouses[] = (new House())
            ->setAddress('Test Address 1')
            ->setBedroomsCount(2)
            ->setPricePerNight(1000)
            ->setHasAirConditioning(true)
            ->setHasWifi(true)
            ->setHasKitchen(true)
            ->setHasParking(false)
            ->setHasSeaView(true)
            ->setImageUrl('http://example.com/house1.jpg')
            ->setCity($this->testCity1);

        $this->testHouses[] = (new House())
            ->setAddress('Test Address 2')
            ->setBedroomsCount(3)
            ->setPricePerNight(1500)
            ->setHasAirConditioning(false)
            ->setHasWifi(true)
            ->setHasKitchen(false)
            ->setHasParking(true)
            ->setHasSeaView(false)
            ->setImageUrl('http://example.com/house2.jpg')
            ->setCity($this->testCity1);

        $this->testHouses[] = (new House())
            ->setAddress('Test Address 3')
            ->setBedroomsCount(1)
            ->setPricePerNight(800)
            ->setHasAirConditioning(true)
            ->setHasWifi(false)
            ->setHasKitchen(true)
            ->setHasParking(true)
            ->setHasSeaView(false)
            ->setImageUrl('http://example.com/house3.jpg')
            ->setCity($this->testCity2);

        foreach ($this->testHouses as $house) {
            $this->entityManager->persist($house);
        }
        $this->entityManager->flush();
    }

    private function assertHousesEqual(House $expected, House $actual): void
    {
        $this->assertEquals($expected->getId(), $actual->getId());
        $this->assertEquals($expected->getAddress(), $actual->getAddress());
        $this->assertEquals($expected->getBedroomsCount(), $actual->getBedroomsCount());
        $this->assertEquals($expected->getPricePerNight(), $actual->getPricePerNight());
        $this->assertEquals($expected->hasAirConditioning(), $actual->hasAirConditioning());
        $this->assertEquals($expected->hasWifi(), $actual->hasWifi());
        $this->assertEquals($expected->hasKitchen(), $actual->hasKitchen());
        $this->assertEquals($expected->hasParking(), $actual->hasParking());
        $this->assertEquals($expected->hasSeaView(), $actual->hasSeaView());
        $this->assertEquals($expected->getImageUrl(), $actual->getImageUrl());
        $this->assertEquals($expected->getCity()->getId(), $actual->getCity()->getId());
        $this->assertEquals($expected->getCity()->getCountry()->getId(), $actual->getCity()->getCountry()->getId());
    }

    public function testfindHouseById(): void
    {
        $expectedHouse = $this->testHouses[0];
        $result        = $this->housesService->findHouseById($expectedHouse->getId());

        $this->assertNull($result['error']);
        $this->assertNotNull($result['house']);
        $this->assertHousesEqual($expectedHouse, $result['house']);
    }

    public function testfindHouseByIdNotFound(): void
    {
        $nonExistentId = 999;
        $result        = $this->housesService->findHouseById($nonExistentId);

        $this->assertNotNull($result['error']);
        $this->assertNull($result['house']);
    }

    public function testFindAvailableHouses(): void
    {
        $cityId    = $this->testCity1->getId();
        $startDate = new DateTimeImmutable('2023-01-01');
        $endDate   = new DateTimeImmutable('2023-01-10');

        $houses = $this->housesService->findAvailableHouses($cityId, $startDate, $endDate);

        $this->assertCount(2, $houses);
        foreach ($houses as $house) {
            $this->assertEquals($cityId, $house->getCity()->getId());
            $this->assertEquals($this->testCountry1->getId(), $house->getCity()->getCountry()->getId());
        }
    }

    public function testFindAllHouses(): void
    {
        $houses = $this->housesService->findAllHouses();
        $this->assertCount(count($this->testHouses), $houses);
    }

    public function testValidateHouseCity(): void
    {
        $house         = $this->testHouses[0];
        $correctCityId = $house->getCity()->getId();
        $wrongCityId   = 999;

        $this->assertNull($this->housesService->validateHouseCity($house, $correctCityId));
        $this->assertNotNull($this->housesService->validateHouseCity($house, $wrongCityId));
    }

    public function testAddHouse(): void
    {
        $newHouse = (new House())
            ->setAddress('New Test Address')
            ->setBedroomsCount(2)
            ->setPricePerNight(1200)
            ->setHasAirConditioning(true)
            ->setHasWifi(true)
            ->setHasKitchen(true)
            ->setHasParking(false)
            ->setHasSeaView(true)
            ->setImageUrl('http://example.com/new_house.jpg')
            ->setCity($this->testCity1);

        $this->housesService->addHouse($newHouse);

        $houses = $this->housesService->findAllHouses();
        $this->assertCount(count($this->testHouses) + 1, $houses);

        $addedHouse = $this->housesRepository->findHouseById(4);
        $this->assertNotNull($addedHouse);
        $this->assertEquals($newHouse->getAddress(), $addedHouse->getAddress());
        $this->assertEquals(
            $this->testCountry1->getId(),
            $addedHouse->getCity()->getCountry()->getId()
        );
    }

    public function testDeleteHouse(): void
    {
        $houseId = $this->testHouses[0]->getId();

        $error = $this->housesService->deleteHouse(999);
        $this->assertNotNull($error);

        $error = $this->housesService->deleteHouse($houseId);
        $this->assertNull($error);

        $houses = $this->housesService->findAllHouses();
        $this->assertCount(count($this->testHouses) - 1, $houses);
    }

    public function testUpdateHouseFields(): void
    {
        $house        = $this->testHouses[0];
        $updatedHouse = (new House())
            ->setBedroomsCount(3)
            ->setPricePerNight(1100)
            ->setHasAirConditioning(false);

        $error = $this->housesService->updateHouseFields(
            $updatedHouse,
            $house->getId()
        );
        $this->assertNull($error);

        $result = $this->housesService->findHouseById($house->getId());
        $this->assertEquals(
            3,
            $result['house']->getBedroomsCount()
        );
        $this->assertEquals(
            1100,
            $result['house']->getPricePerNight()
        );
        $this->assertFalse($result['house']->hasAirConditioning());

        $this->assertEquals(
            $this->testCity1->getId(),
            $result['house']->getCity()->getId()
        );
        $this->assertEquals(
            $this->testCountry1->getId(),
            $result['house']->getCity()->getCountry()->getId()
        );
    }

    public function testReplaceHouse(): void
    {
        $house          = $this->testHouses[0];
        $replacingHouse = (new House())
            ->setAddress('Replaced Address')
            ->setBedroomsCount(4)
            ->setPricePerNight(2000)
            ->setHasAirConditioning(false)
            ->setHasWifi(false)
            ->setHasKitchen(false)
            ->setHasParking(true)
            ->setHasSeaView(false)
            ->setImageUrl('http://example.com/replaced.jpg')
            ->setCity($this->testCity1);

        $error = $this->housesService->replaceHouse(
            $replacingHouse,
            $house->getId()
        );
        $this->assertNull($error);

        $result = $this->housesService->findHouseById($house->getId());

        $this->assertEquals(
            'Replaced Address',
            $result['house']->getAddress()
        );
        $this->assertEquals(
            $replacingHouse->getBedroomsCount(),
            $result['house']->getBedroomsCount()
        );
        $this->assertEquals(
            $replacingHouse->getImageUrl(),
            $result['house']->getImageUrl()
        );
        $this->assertEquals(
            $this->testCity1->getId(),
            $result['house']->getCity()->getId()
        );
        $this->assertEquals(
            $this->testCountry1->getId(),
            $result['house']->getCity()->getCountry()->getId()
        );
    }

    public function testCheckHouseAvailability(): void
    {
        $house       = $this->testHouses[0];
        $isAvailable = $this->housesService->checkHouseAvailability($house);
        $this->assertTrue($isAvailable);
    }
}
