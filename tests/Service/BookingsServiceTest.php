<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\House;
use App\Repository\BookingsRepository;
use App\Repository\HousesRepository;
use App\Service\BookingsService;
use App\Service\HousesService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookingsServiceTest extends KernelTestCase
{
    private BookingsService $bookingsService;
    private HousesService $housesService;

    /** @var BookingsRepository $bookingsRepository */
    private BookingsRepository $bookingsRepository;
    /** @var HousesRepository $housesRepository */
    private HousesRepository $housesRepository;

    private EntityManagerInterface $entityManager;

    /** @var Booking[] */
    private array $testBookings = [];
    private House $testHouse1;
    private House $testHouse2;
    private City $testCity;
    private Country $testCountry;

    #[Override]
    public function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());

        $this->entityManager      = static::getContainer()->get('doctrine')->getManager();
        $this->bookingsRepository = $this->entityManager->getRepository(Booking::class);
        $this->housesRepository   = $this->entityManager->getRepository(House::class);

        $this->housesService = new HousesService(
            $this->housesRepository
        );
        $this->bookingsService = new BookingsService(
            $this->bookingsRepository,
            $this->housesService
        );

        $this->truncateTables();
        $this->createTestData();
    }

    private function truncateTables(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE booking RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE house RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE city RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE country RESTART IDENTITY CASCADE');
    }

    private function createTestData(): void
    {
        $this->testCountry = (new Country())
            ->setName('Test Country');
        $this->entityManager->persist($this->testCountry);

        $this->testCity = (new City())
            ->setName('Test City')
            ->setCountry($this->testCountry);
        $this->entityManager->persist($this->testCity);

        $this->testHouse1 = (new House())
            ->setAddress('Test Address 1')
            ->setBedroomsCount(2)
            ->setPricePerNight(1000)
            ->setHasAirConditioning(true)
            ->setHasWifi(true)
            ->setHasKitchen(true)
            ->setHasParking(false)
            ->setHasSeaView(true)
            ->setImageUrl('http://example.com/house1.jpg')
            ->setCity($this->testCity);

        $this->testHouse2 = (new House())
            ->setAddress('Test Address 2')
            ->setBedroomsCount(3)
            ->setPricePerNight(1500)
            ->setHasAirConditioning(false)
            ->setHasWifi(true)
            ->setHasKitchen(false)
            ->setHasParking(true)
            ->setHasSeaView(false)
            ->setImageUrl('http://example.com/house2.jpg')
            ->setCity($this->testCity);

        $this->entityManager->persist($this->testHouse1);
        $this->entityManager->persist($this->testHouse2);

        $this->testBookings[] = (new Booking())
            ->setPhoneNumber('+1234567890')
            ->setComment('Test comment 1')
            ->setStartDate(new DateTimeImmutable('2025-01-10'))
            ->setEndDate(new DateTimeImmutable('2025-01-15'))
            ->setTelegramChatId(12345)
            ->setTelegramUserId(67890)
            ->setTelegramUsername('test_user1')
            ->setHouse($this->testHouse1);

        $this->testBookings[] = (new Booking())
            ->setPhoneNumber('+9876543210')
            ->setStartDate(new DateTimeImmutable('2025-02-01'))
            ->setEndDate(new DateTimeImmutable('2025-02-05'))
            ->setTelegramChatId(54321)
            ->setTelegramUserId(98765)
            ->setTelegramUsername('test_user2')
            ->setHouse($this->testHouse2);

        foreach ($this->testBookings as $booking) {
            $this->entityManager->persist($booking);
        }
        $this->entityManager->flush();
    }

    private function assertBookingsEqual(Booking $expected, Booking $actual): void
    {
        $this->assertEquals($expected->getId(), $actual->getId());
        $this->assertEquals($expected->getPhoneNumber(), $actual->getPhoneNumber());
        $this->assertEquals($expected->getComment(), $actual->getComment());
        $this->assertEquals($expected->getStartDate()->format('Y-m-d'), $actual->getStartDate()->format('Y-m-d'));
        $this->assertEquals($expected->getEndDate()->format('Y-m-d'), $actual->getEndDate()->format('Y-m-d'));
        $this->assertEquals($expected->getTelegramChatId(), $actual->getTelegramChatId());
        $this->assertEquals($expected->getTelegramUserId(), $actual->getTelegramUserId());
        $this->assertEquals($expected->getTelegramUsername(), $actual->getTelegramUsername());
        $this->assertEquals($expected->getHouse()->getId(), $actual->getHouse()->getId());
    }

    public function testCreateBooking(): void
    {
        $startDate = (new DateTimeImmutable())->modify('+1 day');
        $endDate   = (new DateTimeImmutable())->modify('+1 month');

        $error = $this->bookingsService->createBooking(
            $this->testHouse1->getId(),
            '+1111222333',
            'New booking comment',
            $startDate,
            $endDate,
            11111,
            22222,
            'new_user'
        );

        $this->assertNull($error);

        $bookings = $this->bookingsRepository->findAllBookings();
        $this->assertCount(count($this->testBookings) + 1, $bookings);

        $newBooking = $bookings[2];
        $this->assertEquals('+1111222333', $newBooking->getPhoneNumber());
        $this->assertEquals('New booking comment', $newBooking->getComment());
        $this->assertEquals($this->testHouse1->getId(), $newBooking->getHouse()->getId());
    }

    public function testCreateBookingWithInvalidDates(): void
    {
        // Past start date
        $error = $this->bookingsService->createBooking(
            $this->testHouse1->getId(),
            '+1111222333',
            null,
            new DateTimeImmutable('2020-01-01'),
            new DateTimeImmutable('2025-01-05'),
            11111,
            22222,
            'new_user'
        );
        $this->assertNotNull($error);

        // Start date after end date
        $error = $this->bookingsService->createBooking(
            $this->testHouse1->getId(),
            '+1111222333',
            null,
            new DateTimeImmutable('2025-01-10'),
            new DateTimeImmutable('2025-01-05'),
            11111,
            22222,
            'new_user'
        );
        $this->assertNotNull($error);
    }

    public function testCalculateTotalPrice(): void
    {
        $startDate     = new DateTimeImmutable('2025-01-10');
        $endDate       = new DateTimeImmutable('2025-01-15');
        $expectedPrice = 5 * $this->testHouse1->getPricePerNight();

        $price = $this->bookingsService->calculateTotalPrice(
            $this->testHouse1,
            $startDate,
            $endDate
        );

        $this->assertEquals($expectedPrice, $price);
    }

    public function testFindBookingById(): void
    {
        $expectedBooking = $this->testBookings[0];
        $booking         = $this->bookingsService->findBookingById($expectedBooking->getId());

        $this->assertNotNull($booking);
        $this->assertBookingsEqual($expectedBooking, $booking);
    }

    public function testFindAllBookings(): void
    {
        $bookings = $this->bookingsService->findAllBookings();
        $this->assertCount(count($this->testBookings), $bookings);
    }

    public function testFindBookingsByCriteria(): void
    {
        // Test by telegram user id
        $bookings = $this->bookingsService->findBookingsByCriteria(
            ['telegramUserId' => $this->testBookings[0]->getTelegramUserId()],
        );
        $this->assertCount(1, $bookings);
        $this->assertEquals($this->testBookings[0]->getTelegramUsername(), $bookings[0]->getTelegramUsername());

        // Test actual bookings
        $bookings = $this->bookingsService->findBookingsByCriteria(
            [],
            true
        );
        $this->assertCount(0, $bookings);
    }

    public function testUpdateBooking(): void
    {
        $booking        = $this->testBookings[0];
        $updatedBooking = (new Booking())
            ->setPhoneNumber('+9999999999')
            ->setComment('Updated comment');

        $result = $this->bookingsService->updateBooking($updatedBooking, $booking->getId());

        $this->assertNull($result['error']);
        $this->assertNotNull($result['booking']);
        $this->assertEquals(
            $updatedBooking->getPhoneNumber(),
            $result['booking']->getPhoneNumber()
        );
        $this->assertEquals(
            $updatedBooking->getComment(),
            $result['booking']->getComment()
        );
        // Ensure other fields didn't change
        $this->assertEquals(
            $booking->getHouse()->getId(),
            $result['booking']->getHouse()->getId()
        );
    }

    public function testReplaceBooking(): void
    {
        $booking          = $this->testBookings[0];
        $replacingBooking = (new Booking())
            ->setPhoneNumber('+9999999999')
            ->setComment('Replaced booking')
            ->setHouse($this->testHouse2)
            ->setStartDate(new DateTimeImmutable('2025-01-10'))
            ->setEndDate(new DateTimeImmutable('2025-01-15'));

        $result = $this->bookingsService->replaceBooking($replacingBooking, $booking->getId());

        $this->assertNull($result['error']);
        $this->assertNotNull($result['booking']);
        $this->assertEquals(
            $replacingBooking->getPhoneNumber(),
            $result['booking']->getPhoneNumber()
        );
        $this->assertEquals(
            $replacingBooking->getComment(),
            $result['booking']->getComment()
        );
        $this->assertEquals(
            $this->testHouse2->getId(),
            $result['booking']->getHouse()->getId()
        );
    }

    public function testDeleteBooking(): void
    {
        $bookingId = $this->testBookings[0]->getId();

        // Try to delete non-existent booking
        $result = $this->bookingsService->deleteBooking(999);
        $this->assertNotNull($result['error']);

        // Delete existing booking
        $result = $this->bookingsService->deleteBooking($bookingId);
        $this->assertNull($result['error']);
        $this->assertNotNull($result['booking']);

        $bookings = $this->bookingsService->findAllBookings();
        $this->assertCount(1, $bookings);
    }

    public function testValidateHouseAvailability(): void
    {
        // House is available for new dates
        $error = $this->bookingsService->validateHouseAvailability(
            $this->testHouse1,
            new DateTimeImmutable('2025-03-01'),
            new DateTimeImmutable('2025-03-05')
        );
        $this->assertNull($error);

        // House is not available (conflict with existing booking)
        $error = $this->bookingsService->validateHouseAvailability(
            $this->testHouse1,
            new DateTimeImmutable('2025-01-12'),
            new DateTimeImmutable('2025-01-16')
        );
        $this->assertNotNull($error);
    }
}
