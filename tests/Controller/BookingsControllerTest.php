<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Constant\BookingsMessages;
use App\Constant\HousesMessages;
use App\Entity\Booking;
use App\Entity\House;
use App\Repository\BookingsRepository;
use App\Repository\CitiesRepository;
use App\Repository\CountriesRepository;
use App\Repository\HousesRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class BookingsControllerTest extends WebTestCase
{
    /** @var HousesRepository $housesRepository */
    private static HousesRepository $housesRepository;
    /** @var CitiesRepository $citiesRepository */
    private static CitiesRepository $citiesRepository;
    /** @var BookingsRepository $bookingsRepository */
    private static BookingsRepository $bookingsRepository;
    /** @var CountriesRepository $countriesRepository */
    private static CountriesRepository $countriesRepository;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

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
        $kernel = static::createKernel();
        $kernel->boot();
        self::assertSame('test', $kernel->getEnvironment());

        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $connection    = $entityManager->getConnection();

        $connection->executeStatement('TRUNCATE TABLE booking RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE house RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE city RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE country RESTART IDENTITY CASCADE');

        self::$countriesRepository = $entityManager->getRepository('App\Entity\Country');
        self::$countriesRepository->loadFromCsv(self::COUNTRIES_CSV_PATH);

        self::$citiesRepository = $entityManager->getRepository('App\Entity\City');
        self::$citiesRepository->loadFromCsv(self::CITIES_CSV_PATH);

        self::$housesRepository = $entityManager->getRepository('App\Entity\House');
        self::$housesRepository->loadFromCsv(self::HOUSES_CSV_PATH);

        self::$bookingsRepository = $entityManager->getRepository(Booking::class);
        self::$bookingsRepository->loadFromCsv(self::BOOKINGS_CSV_PATH);
    }

    #[Override]
    public function setUp(): void
    {
        $this->client        = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        self::$bookingsRepository = $this->entityManager->getRepository(Booking::class);
        self::$housesRepository   = $this->entityManager->getRepository(House::class);
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
     * Scenario: Listing all bookings
     * Given there are bookings in the system
     * When I request the list of bookings
     * Then I should receive a list of all bookings with status 200
     */
    public function testListBookings(): void
    {
        $expectedBookings = array_map(
            fn ($booking) => $booking->toArray(),
            self::$bookingsRepository->findAll()
        );

        $this->client->request('GET', self::API_BOOKINGS);
        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_OK,
            $expectedBookings
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

        $this->client->request('GET', sprintf(self::API_BOOKINGS_ID, $bookingId));
        $this->assertResponse(
            $this->client->getResponse(),
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

        $this->client->request('GET', sprintf(self::API_BOOKINGS_ID, $bookingId));
        $this->assertResponse(
            $this->client->getResponse(),
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
            'house_id'     => 3,
            'phone_number' => '+1234567890',
            'comment'      => 'New booking',
            'start_date'   => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('+1 month')
                ->format('Y-m-d'),
            'telegram_chat_id'  => 111222333,
            'telegram_user_id'  => 444555666,
            'telegram_username' => 'new_user'
        ];

        $this->client->request(
            'POST',
            self::API_BOOKINGS,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($newBookingData)
        );

        $response = $this->client->getResponse();
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
            'house_id'     => 3,
            'phone_number' => '',
            'start_date'   => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('-1 month')
                ->format('Y-m-d'),
            'telegram_chat_id' => 111222333
        ];

        $this->client->request(
            'POST',
            self::API_BOOKINGS,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidBookingData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Validation failed', $responseData['message']);
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
            'house_id'     => 999,
            'phone_number' => '+1234567890',
            'start_date'   => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('+1 month')
                ->format('Y-m-d'),
        ];

        $this->client->request(
            'POST',
            self::API_BOOKINGS,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($newBookingData)
        );

        $this->assertResponse(
            $this->client->getResponse(),
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
        $bookingId      = 1;
        $newBookingData = [
            'house_id'     => 4,
            'phone_number' => '+9876543210',
            'comment'      => 'Updated booking',
            'start_date'   => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('+1 month')
                ->format('Y-m-d'),
            'telegram_chat_id'  => 999888777,
            'telegram_user_id'  => 666555444,
            'telegram_username' => 'updated_user'
        ];

        $this->client->request(
            'PUT',
            sprintf(self::API_BOOKINGS_ID, $bookingId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($newBookingData)
        );

        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_OK,
            BookingsMessages::replaced()
        );

        $updatedBooking = self::$bookingsRepository->find($bookingId);
        $this->assertEquals('Updated booking', $updatedBooking->getComment());
        $this->assertEquals(4, $updatedBooking->getHouse()->getId());
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
            'house_id'     => 4,
            'phone_number' => '+9876543210',
            'start_date'   => (new DateTimeImmutable())
                ->modify('+1 day')
                ->format('Y-m-d'),
            'end_date' => (new DateTimeImmutable())
                ->modify('+1 month')
                ->format('Y-m-d')
        ];

        $this->client->request(
            'PUT',
            sprintf(self::API_BOOKINGS_ID, $bookingId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($replacedBookingData)
        );

        $this->assertResponse(
            $this->client->getResponse(),
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
            'comment'      => 'Updated comment',
            'phone_number' => '+1112223333'
        ];

        $this->client->request(
            'PATCH',
            sprintf(self::API_BOOKINGS_ID, $bookingId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_OK,
            BookingsMessages::updated()
        );

        $updatedBooking = self::$bookingsRepository->find($bookingId);
        $this->assertEquals('Updated comment', $updatedBooking->getComment());
        $this->assertEquals('+1112223333', $updatedBooking->getPhoneNumber());
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

        $this->client->request('DELETE', sprintf(self::API_BOOKINGS_ID, $bookingId));

        $this->assertResponse(
            $this->client->getResponse(),
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

        $this->client->request('DELETE', sprintf(self::API_BOOKINGS_ID, $bookingId));

        $this->assertResponse(
            $this->client->getResponse(),
            Response::HTTP_NOT_FOUND,
            BookingsMessages::notFound()
        );
    }
}
