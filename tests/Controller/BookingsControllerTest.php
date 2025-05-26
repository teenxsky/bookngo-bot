<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Constant\BookingsMessages;
use App\Constant\HousesMessages;
use App\Entity\Booking;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\House;
use App\Entity\User;
use App\Repository\BookingsRepository;
use App\Repository\CitiesRepository;
use App\Repository\CountriesRepository;
use App\Repository\HousesRepository;
use App\Service\UsersService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BookingsControllerTest extends WebTestCase
{
    /** @var HousesRepository $housesRepository */
    private static HousesRepository $housesRepository;
    /** @var CitiesRepository $citiesRepository */
    private static CitiesRepository $citiesRepository;
    /** @var CountriesRepository $countriesRepository */
    private static CountriesRepository $countriesRepository;
    /** @var BookingsRepository $bookingsRepository */
    private static BookingsRepository $bookingsRepository;

    private static KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    // Test Credentials
    /** @var array{access_token: string, refresh_token: string} */
    private static array $userTokens;
    /** @var array{access_token: string, refresh_token: string} */
    private static array $adminTokens;

    private const USER_PHONE_NUMBER  = '+1234567890';
    private const USER_PASSWORD      = 'user123';
    private const ADMIN_PHONE_NUMBER = '+1234567891';
    private const ADMIN_PASSWORD     = 'admin123';

    // Test Data Paths
    private const BOOKINGS_CSV_PATH  = __DIR__ . '/../Resources/test_bookings.csv';
    private const HOUSES_CSV_PATH    = __DIR__ . '/../Resources/test_houses.csv';
    private const CITIES_CSV_PATH    = __DIR__ . '/../Resources/test_cities.csv';
    private const COUNTRIES_CSV_PATH = __DIR__ . '/../Resources/test_countries.csv';

    // API Endpoints
    private const API_BOOKINGS    = '/api/v1/bookings/';
    private const API_BOOKINGS_ID = '/api/v1/bookings/%d';

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
        $entityManager = self::$client->getContainer()
            ->get('doctrine')
            ->getManager();

        // Clear all tables
        $connection = $entityManager->getConnection();
        $connection->executeStatement(
            'TRUNCATE TABLE refresh_tokens RESTART IDENTITY CASCADE'
        );
        $connection->executeStatement(
            'TRUNCATE TABLE bookings RESTART IDENTITY CASCADE'
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
        $connection->executeStatement(
            'TRUNCATE TABLE users RESTART IDENTITY CASCADE'
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

        // Register test users
        $usersService->registerApiUser(
            self::USER_PHONE_NUMBER,
            self::USER_PASSWORD,
            false
        );
        $usersService->registerApiUser(
            self::ADMIN_PHONE_NUMBER,
            self::ADMIN_PASSWORD,
            true
        );

        // Login to get tokens
        self::$userTokens = $usersService->loginApiUser(
            self::USER_PHONE_NUMBER,
            self::USER_PASSWORD
        )['tokens'];
        self::$adminTokens = $usersService->loginApiUser(
            self::ADMIN_PHONE_NUMBER,
            self::ADMIN_PASSWORD
        )['tokens'];

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

        self::$bookingsRepository = $entityManager->getRepository(
            Booking::class
        );
        self::$bookingsRepository->loadFromCsv(
            self::BOOKINGS_CSV_PATH
        );
    }

    #[Override]
    public function setUp(): void
    {
        self::$client->getKernel()->boot();

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
        self::$bookingsRepository = $this->entityManager->getRepository(
            Booking::class
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
     * Scenario: Listing user's bookings
     * Given there are bookings in the system
     * When I request the list of bookings
     * Then I should receive a list of all user's bookings with status 200
     */
    public function testUserListBookings(): void
    {
        $expectedBookings = array_merge(
            array_map(
                fn ($booking) => $booking->toArray(),
                self::$bookingsRepository->findBookingsByUserId(
                    1,
                    true
                )
            ),
            array_map(
                fn ($booking) => $booking->toArray(),
                self::$bookingsRepository->findBookingsByUserId(
                    1,
                    false
                )
            )
        );

        self::$client->request(
            method: 'GET',
            uri: self::API_BOOKINGS,
            server: [
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ]
        );
        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_OK,
            $expectedBookings,
        );
    }

    /*
     * Scenario: Listing all bookings
     * Given there are bookings in the system
     * When I request the list of bookings
     * Then I should receive a list of all bookings with status 200
     */
    public function testAdminListBookings(): void
    {
        $expectedBookings = array_map(
            fn ($booking) => $booking->toArray(),
            self::$bookingsRepository->findAllBookings()
        );

        self::$client->request(
            method: 'GET',
            uri: self::API_BOOKINGS,
            server: [
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$adminTokens['access_token']
                )
            ]
        );
        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_OK,
            $expectedBookings,
        );
    }

    /*
     * Scenario: Getting a booking by ID
     * Given there is a booking with the specified ID
     * When I request the booking by ID
     * Then I should receive the booking details with status 200
     */
    public function testGetBookingById(): void
    {
        $bookingId       = 1;
        $expectedBooking = self::$bookingsRepository->find($bookingId)->toArray();

        self::$client->request(
            method: 'GET',
            uri: sprintf(self::API_BOOKINGS_ID, $bookingId),
            server: [
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ]
        );
        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_OK,
            $expectedBooking
        );
    }

    /*
     * Scenario: Getting a non-existent booking by ID
     * Given there is no booking with the specified ID
     * When I request the booking by ID
     * Then I should receive an error with status 404
     */
    public function testGetBookingByIdNotFound(): void
    {
        $bookingId = 999;

        self::$client->request(
            method: 'GET',
            uri: sprintf(self::API_BOOKINGS_ID, $bookingId),
            server: [
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ]
        );
        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_NOT_FOUND,
            BookingsMessages::notFound()
        );
    }

    /*
     * Scenario: Adding a new booking successfully
     * Given valid booking data
     * When I create a new booking
     * Then the booking should be created with status 201
     */
    public function testAddBookingSuccess(): void
    {
        $newBookingData = [
            'house_id'   => 3,
            'comment'    => 'New booking',
            'start_date' => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('+1 month')
                ->format('Y-m-d'),
        ];

        self::$client->request(
            method: 'POST',
            uri: self::API_BOOKINGS,
            server: [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ],
            content: json_encode($newBookingData)
        );

        $response = self::$client->getResponse();
        $this->assertResponse(
            $response,
            Response::HTTP_CREATED,
            BookingsMessages::created()
        );
    }

    /*
     * Scenario: Adding a booking with invalid data
     * Given invalid booking data
     * When I create a new booking
     * Then I should receive validation errors with status 400
     */
    public function testAddBookingValidationError(): void
    {
        $invalidBookingData = [
            'house_id'   => 3,
            'start_date' => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('-1 month')
                ->format('Y-m-d'),
        ];

        self::$client->request(
            method: 'POST',
            uri: self::API_BOOKINGS,
            server: [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ],
            content: json_encode($invalidBookingData)
        );

        $response = self::$client->getResponse();
        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $responseData = json_decode(
            $response->getContent(),
            true
        );
        $this->assertEquals(
            'Validation failed',
            $responseData['message']
        );
        $this->assertArrayHasKey('errors', $responseData);
        $this->assertNotEmpty($responseData['errors']);
    }

    /*
     * Scenario: Adding a booking for non-existent house
     * Given booking data with non-existent house ID
     * When I create a new booking
     * Then I should receive an error with status 404
     */
    public function testAddBookingHouseNotFound(): void
    {
        $newBookingData = [
            'house_id'   => 999,
            'start_date' => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('+1 month')
                ->format('Y-m-d'),
        ];

        self::$client->request(
            method: 'POST',
            uri: self::API_BOOKINGS,
            server: [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ],
            content: json_encode($newBookingData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_NOT_FOUND,
            HousesMessages::notFound()
        );
    }

    /*
     * Scenario: Replacing a booking successfully
     * Given there is an existing booking
     * When I replace the booking with new data
     * Then the booking should be updated with status 200
     */
    public function testReplaceBookingSuccess(): void
    {
        $bookingId      = 5;
        $newBookingData = [
            'house_id'   => 4,
            'comment'    => 'Updated booking',
            'start_date' => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('+1 month')
                ->format('Y-m-d')
        ];

        self::$client->request(
            method: 'PUT',
            uri: sprintf(self::API_BOOKINGS_ID, $bookingId),
            server: [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ],
            content: json_encode($newBookingData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_OK,
            BookingsMessages::replaced()
        );

        $updatedBooking = self::$bookingsRepository->find($bookingId);
        $this->assertEquals(
            'Updated booking',
            $updatedBooking->getComment()
        );
        $this->assertEquals(
            4,
            $updatedBooking->getHouse()->getId()
        );
    }

    /*
     * Scenario: Replacing a someone else booking
     * Given there is an existing booking
     * When I replace the booking with new data
     * Then the booking shouldn't be updated with status 403
     */
    public function testReplaceSomeoneElseBooking(): void
    {
        $bookingId      = 4;
        $newBookingData = [
            'house_id'   => 4,
            'comment'    => 'Updated booking',
            'start_date' => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('+1 month')
                ->format('Y-m-d')
        ];

        self::$client->request(
            method: 'PUT',
            uri: sprintf(self::API_BOOKINGS_ID, $bookingId),
            server: [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ],
            content: json_encode($newBookingData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_FORBIDDEN,
            BookingsMessages::validationFailed(
                ['You cannot replace other users bookings']
            )
        );
    }

    /*
     * Scenario: Replacing a non-existent booking
     * Given there is no booking with the specified ID
     * When I replace the booking
     * Then I should receive an error with status 404
     */
    public function testReplaceBookingNotFound(): void
    {
        $bookingId           = 999;
        $replacedBookingData = [
            'house_id'   => 4,
            'start_date' => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('+1 month')
                ->format('Y-m-d')
        ];

        self::$client->request(
            'PUT',
            sprintf(self::API_BOOKINGS_ID, $bookingId),
            [],
            [],
            [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ],
            json_encode($replacedBookingData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_NOT_FOUND,
            BookingsMessages::notFound()
        );
    }

    /*
     * Scenario: Updating a booking successfully
     * Given there is an existing booking
     * When I update the booking with partial data
     * Then the booking should be updated with status 200
     */
    public function testUpdateBookingSuccess(): void
    {
        $bookingId  = 2;
        $updateData = [
            'comment' => 'Updated comment',
        ];

        self::$client->request(
            'PATCH',
            sprintf(self::API_BOOKINGS_ID, $bookingId),
            [],
            [],
            [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ],
            json_encode($updateData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_OK,
            BookingsMessages::updated()
        );

        $updatedBooking = self::$bookingsRepository->findBookingById($bookingId);
        $this->assertNotNull($updatedBooking);
        $this->assertEquals(
            'Updated comment',
            $updatedBooking->getComment()
        );
    }

    /*
     * Scenario: Deleting a booking successfully
     * Given there is an existing booking
     * When I delete the booking
     * Then the booking should be deleted with status 200
     */
    public function testDeleteBookingSuccess(): void
    {
        $bookingId = 1;

        self::$client->request(
            method: 'DELETE',
            uri: sprintf(self::API_BOOKINGS_ID, $bookingId),
            server: [
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ]
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_OK,
            BookingsMessages::deleted()
        );

        $this->assertNull(self::$bookingsRepository->find($bookingId));
    }

    /*
     * Scenario: Deleting a non-existent booking
     * Given there is no booking with the specified ID
     * When I delete the booking
     * Then I should receive an error with status 404
     */
    public function testDeleteBookingNotFound(): void
    {
        $bookingId = 999;

        self::$client->request(
            method: 'DELETE',
            uri: sprintf(self::API_BOOKINGS_ID, $bookingId),
            server: [
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ]
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_NOT_FOUND,
            BookingsMessages::notFound()
        );
    }
}
