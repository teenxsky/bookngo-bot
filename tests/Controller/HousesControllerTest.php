<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Constant\HousesMessages;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\House;
use App\Entity\User;
use App\Repository\CitiesRepository;
use App\Repository\CountriesRepository;
use App\Repository\HousesRepository;
use App\Service\UsersService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class HousesControllerTest extends WebTestCase
{
    /** @var HousesRepository $housesRepository */
    private static HousesRepository $housesRepository;
    /** @var CitiesRepository $citiesRepository */
    private static CitiesRepository $citiesRepository;
    /** @var CountriesRepository $countriesRepository */
    private static CountriesRepository $countriesRepository;

    private static KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    // Test Credentials
    /** @var array{access_token: string, refresh_token: string} */
    private static array $adminTokens;

    private const ADMIN_PHONE_NUMBER = '+1234567890';
    private const ADMIN_PASSWORD     = 'admin123';

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
        // Initialize the client
        self::$client = static::createClient();
        self::assertSame(
            'test',
            self::$client->getKernel()->getEnvironment()
        );

        // Initialize the entity manager
        $entityManager = self::$client->getKernel()->getContainer()
            ->get('doctrine')
            ->getManager();

        // Clear all tables
        $connection = $entityManager->getConnection();
        $connection->executeStatement(
            'TRUNCATE TABLE refresh_tokens RESTART IDENTITY CASCADE'
        );
        $connection->executeStatement(
            'TRUNCATE TABLE users RESTART IDENTITY CASCADE'
        );
        $connection->executeStatement(
            'TRUNCATE TABLE houses RESTART IDENTITY CASCADE'
        );
        $connection->executeStatement(
            'TRUNCATE TABLE cities RESTART IDENTITY CASCADE'
        );
        $connection->executeStatement(
            'TRUNCATE TABLE countries RESTART IDENTITY CASCADE'
        );

        // Initialize services
        $usersService = new UsersService(
            $entityManager->getRepository(
                User::class
            ),
            static::getContainer()->get(
                UserPasswordHasherInterface::class
            ),
            static::getContainer()->get(
                JWTTokenManagerInterface::class
            ),
            static::getContainer()->get(
                RefreshTokenManagerInterface::class
            )
        );

        // Register test user
        $usersService->registerApiUser(
            phoneNumber: self::ADMIN_PHONE_NUMBER,
            password: self::ADMIN_PASSWORD,
            isAdmin: true
        );

        // Login to get tokens
        if (!$usersService->validateCredentials(
            self::ADMIN_PHONE_NUMBER,
            self::ADMIN_PASSWORD
        )) {
            self::$adminTokens = $usersService->loginApiUser(
                self::ADMIN_PHONE_NUMBER,
            );
        }

        // Initialize the database
        self::$countriesRepository = $entityManager->getRepository(
            Country::class
        );
        self::$countriesRepository->loadFromCsv(
            self::COUNTRIES_CSV_PATH
        );

        self::$citiesRepository = $entityManager->getRepository(
            City::class
        );
        self::$citiesRepository->loadFromCsv(
            self::CITIES_CSV_PATH
        );

        self::$housesRepository = $entityManager->getRepository(
            House::class
        );
        self::$housesRepository->loadFromCsv(
            self::HOUSES_CSV_PATH
        );
    }

    #[Override]
    public function setUp(): void
    {
        self::$client->getKernel()->boot();
        self::$client->setServerParameter(
            'HTTP_Authorization',
            'Bearer '. self::$adminTokens['access_token'],
        );

        $this->entityManager = self::$client->getContainer()
            ->get('doctrine')
            ->getManager();
        self::$countriesRepository = $this->entityManager->getRepository(
            Country::class
        );
        self::$citiesRepository = $this->entityManager->getRepository(
            City::class
        );
        self::$housesRepository = $this->entityManager->getRepository(
            House::class
        );
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

        self::$client->request(
            'GET',
            self::API_HOUSES,
        );
        $this->assertResponse(
            self::$client->getResponse(),
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

        self::$client->request(
            'GET',
            sprintf(self::API_HOUSES_ID, $houseId),
        );
        $this->assertResponse(
            self::$client->getResponse(),
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

        self::$client->request(
            'GET',
            sprintf(self::API_HOUSES_ID, $houseId),
        );
        $this->assertResponse(
            self::$client->getResponse(),
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

        self::$client->request(
            'POST',
            self::API_HOUSES,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($newHouseData)
        );

        $response = self::$client->getResponse();
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

        self::$client->request(
            'POST',
            self::API_HOUSES,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidHouseData)
        );

        $response = self::$client->getResponse();
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

        self::$client->request(
            'PUT',
            sprintf(self::API_HOUSES_ID, $houseId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($newHouseData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
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

        self::$client->request(
            'PUT',
            sprintf(self::API_HOUSES_ID, $houseId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($replacedHouseData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
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

        self::$client->request(
            'PATCH',
            sprintf(self::API_HOUSES_ID, $houseId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
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

        self::$client->request(
            'DELETE',
            sprintf(self::API_HOUSES_ID, $houseId),
        );

        $this->assertResponse(
            self::$client->getResponse(),
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

        self::$client->request(
            'DELETE',
            sprintf(self::API_HOUSES_ID, $houseId),
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_NOT_FOUND,
            HousesMessages::notFound()
        );
    }
}
