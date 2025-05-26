<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Constant\HousesMessages;
use App\Repository\CitiesRepository;
use App\Repository\CountriesRepository;
use App\Repository\HousesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class HousesControllerTest extends WebTestCase
{
    /** @var HousesRepository $housesRepository */
    private static HousesRepository $housesRepository;
    /** @var CitiesRepository $citiesRepository */
    private static CitiesRepository $citiesRepository;
    /** @var CountriesRepository $countriesRepository */
    private static CountriesRepository $countriesRepository;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    // Test Data Paths
    private const HOUSES_CSV_PATH    = __DIR__ . '/../Resources/test_houses.csv';
    private const CITIES_CSV_PATH    = __DIR__ . '/../Resources/test_cities.csv';
    private const COUNTRIES_CSV_PATH = __DIR__ . '/../Resources/test_countries.csv';

    // API Endpoints
    private const API_HOUSES    = '/api/v1/houses/';
    private const API_HOUSES_ID = '/api/v1/houses/%d';

    #[Override]
    public static function setUpBeforeClass(): void
    {
        self::initializeDatabase();
    }

    protected static function initializeDatabase(): void
    {
        $kernel = static::createKernel();
        $kernel->boot();
        self::assertSame('test', $kernel->getEnvironment());

        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $connection    = $entityManager->getConnection();

        $connection->executeStatement('TRUNCATE TABLE house RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE city RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE country RESTART IDENTITY CASCADE');

        self::$countriesRepository = $entityManager->getRepository('App\Entity\Country');
        self::$countriesRepository->loadFromCsv(self::COUNTRIES_CSV_PATH);

        self::$citiesRepository = $entityManager->getRepository('App\Entity\City');
        self::$citiesRepository->loadFromCsv(self::CITIES_CSV_PATH);

        self::$housesRepository = $entityManager->getRepository('App\Entity\House');
        self::$housesRepository->loadFromCsv(self::HOUSES_CSV_PATH);
    }

    #[Override]
    public function setUp(): void
    {
        $this->client        = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        self::$housesRepository    = $this->entityManager->getRepository('App\Entity\House');
        self::$citiesRepository    = $this->entityManager->getRepository('App\Entity\City');
        self::$countriesRepository = $this->entityManager->getRepository('App\Entity\Country');
    }

    private function assertResponse(
        Response $response,
        int $expectedStatusCode,
        ?array $expectedContent = null
    ): void {
        $this->assertEquals(
            $expectedStatusCode,
            $response->getStatusCode()
        );
        $this->assertJson($response->getContent());

        if ($expectedContent) {
            $this->assertEquals(
                json_encode($expectedContent),
                $response->getContent()
            );
        }
    }

    /*
     * Scenario: Listing all houses
     * Given there are houses in the system
     * When I request the list of houses
     * Then I should receive a list of all houses with status 200
     */
    public function testListHouses(): void
    {
        $expectedHouses = array_map(
            fn ($house) => $house->toArray(),
            self::$housesRepository->findAll()
        );

        $this->client->request('GET', self::API_HOUSES);
        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_OK,
            $expectedHouses
        );
    }

    /*
     * Scenario: Getting a house by ID
     * Given there is a house with the specified ID
     * When I request the house by ID
     * Then I should receive the house details with status 200
     */
    public function testGetHouseById(): void
    {
        $houseId       = 1;
        $expectedHouse = self::$housesRepository->find($houseId)->toArray();

        $this->client->request('GET', sprintf(self::API_HOUSES_ID, $houseId));
        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_OK,
            $expectedHouse
        );
    }

    /*
     * Scenario: Getting a non-existent house by ID
     * Given there is no house with the specified ID
     * When I request the house by ID
     * Then I should receive an error with status 404
     */
    public function testGetHouseByIdNotFound(): void
    {
        $houseId = 999;

        $this->client->request('GET', sprintf(self::API_HOUSES_ID, $houseId));
        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_NOT_FOUND,
            HousesMessages::notFound()
        );
    }

    /*
     * Scenario: Adding a new house successfully
     * Given valid house data
     * When I create a new house
     * Then the house should be created with status 201
     */
    public function testAddHouseSuccess(): void
    {
        $newHouseData = [
            'city_id'              => 1,
            'address'              => '789 New St, Testville',
            'bedrooms_count'       => 3,
            'price_per_night'      => 18000,
            'has_air_conditioning' => true,
            'has_wifi'             => true,
            'has_kitchen'          => true,
            'has_parking'          => false,
            'has_sea_view'         => false,
            'image_url'            => 'https://example.com/new_house.jpg'
        ];

        $countBefore = count(self::$housesRepository->findAll());

        $this->client->request(
            'POST',
            self::API_HOUSES,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($newHouseData)
        );

        $response = $this->client->getResponse();
        $this->assertResponse(
            $response,
            Response::HTTP_CREATED,
            HousesMessages::created()
        );

        $countAfter = count(self::$housesRepository->findAll());
        $this->assertEquals($countBefore + 1, $countAfter);
    }

    /*
     * Scenario: Adding a house with invalid data
     * Given invalid house data
     * When I create a new house
     * Then I should receive validation errors with status 400
     */
    public function testAddHouseValidationError(): void
    {
        $invalidHouseData = [
            'city_id'              => 1,
            'address'              => '',
            'bedrooms_count'       => 21,
            'price_per_night'      => 50,
            'has_air_conditioning' => true,
            'has_wifi'             => true,
            'has_kitchen'          => true,
            'has_parking'          => true,
            'has_sea_view'         => false
        ];

        $this->client->request(
            'POST',
            self::API_HOUSES,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidHouseData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Validation failed', $responseData['message']);
        $this->assertArrayHasKey('errors', $responseData);
        $this->assertNotEmpty($responseData['errors']);
    }

    /*
     * Scenario: Replacing a house successfully
     * Given there is an existing house
     * When I replace the house with new data
     * Then the house should be updated with status 200
     */
    public function testReplaceHouseSuccess(): void
    {
        $houseId      = 1;
        $newHouseData = [
            'city_id'              => 1,
            'address'              => 'Updated Address',
            'bedrooms_count'       => 4,
            'price_per_night'      => 20000,
            'has_air_conditioning' => true,
            'has_wifi'             => true,
            'has_kitchen'          => true,
            'has_parking'          => true,
            'has_sea_view'         => false,
            'image_url'            => 'https://example.com/updated.jpg'
        ];

        $this->client->request(
            'PUT',
            sprintf(self::API_HOUSES_ID, $houseId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($newHouseData)
        );

        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_OK,
            HousesMessages::replaced()
        );

        $updatedHouse = self::$housesRepository->find($houseId);
        $this->assertEquals('Updated Address', $updatedHouse->getAddress());
    }

    /*
     * Scenario: Replacing a non-existent house
     * Given there is no house with the specified ID
     * When I replace the house
     * Then I should receive an error with status 404
     */
    public function testReplaceHouseNotFound(): void
    {
        $houseId           = 999;
        $replacedHouseData = [
            'city_id'              => 1,
            'address'              => 'Replaced Address',
            'bedrooms_count'       => 4,
            'price_per_night'      => 20000,
            'has_air_conditioning' => true,
            'has_wifi'             => true,
            'has_kitchen'          => true,
            'has_parking'          => true,
            'has_sea_view'         => false,
            'image_url'            => 'https://example.com/updated.jpg'
        ];

        $this->client->request(
            'PUT',
            sprintf(self::API_HOUSES_ID, $houseId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($replacedHouseData)
        );

        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_NOT_FOUND,
            HousesMessages::notFound()
        );
    }

    /*
     * Scenario: Updating a house successfully
     * Given there is an existing house
     * When I update the house with partial data
     * Then the house should be updated with status 200
     */
    public function testUpdateHouseSuccess(): void
    {
        $houseId    = 2;
        $updateData = [
            'bedrooms_count'  => 3,
            'price_per_night' => 15000
        ];

        $this->client->request(
            'PATCH',
            sprintf(self::API_HOUSES_ID, $houseId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_OK,
            HousesMessages::updated()
        );

        $updatedHouse = self::$housesRepository->find($houseId);
        $this->assertEquals(3, $updatedHouse->getBedroomsCount());
        $this->assertEquals(15000, $updatedHouse->getPricePerNight());
    }

    /*
     * Scenario: Deleting a house successfully
     * Given there is an existing house
     * When I delete the house
     * Then the house should be deleted with status 200
     */
    public function testDeleteHouseSuccess(): void
    {
        $houseId = 3;

        $this->client->request('DELETE', sprintf(self::API_HOUSES_ID, $houseId));

        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_OK,
            HousesMessages::deleted()
        );

        $this->assertNull(self::$housesRepository->find($houseId));
    }

    /*
     * Scenario: Deleting a non-existent house
     * Given there is no house with the specified ID
     * When I delete the house
     * Then I should receive an error with status 404
     */
    public function testDeleteHouseNotFound(): void
    {
        $houseId = 999;

        $this->client->request('DELETE', sprintf(self::API_HOUSES_ID, $houseId));

        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_NOT_FOUND,
            HousesMessages::notFound()
        );
    }
}
