<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\House;
use App\Entity\User;
use App\Repository\BookingsRepository;
use App\Repository\HousesRepository;
use App\Repository\UsersRepository;
use App\Service\BookingsService;
use App\Service\HousesService;
use App\Service\UsersService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BookingsServiceTest extends KernelTestCase
{
    private BookingsService $bookingsService;
    private HousesService $housesService;
    private UsersService $usersService;

    /** @var BookingsRepository $bookingsRepository */
    private BookingsRepository $bookingsRepository;
    /** @var HousesRepository $housesRepository */
    private HousesRepository $housesRepository;
    /** @var UsersRepository $usersRepository */
    private UsersRepository $usersRepository;

    private EntityManagerInterface $entityManager;

    /** @var Booking[] */
    private array $testBookings = [];
    private House $testHouse1;
    private House $testHouse2;
    private City $testCity;
    private Country $testCountry;
    private User $testUser1;
    private User $testUser2;

    #[Override]
    public function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());

        $this->entityManager = static::getContainer()
            ->get('doctrine')
            ->getManager();
        $this->bookingsRepository = $this->entityManager->getRepository(
            Booking::class
        );
        $this->housesRepository = $this->entityManager->getRepository(
            House::class
        );
        $this->usersRepository = $this->entityManager->getRepository(
            User::class
        );

        $this->housesService = new HousesService(
            $this->housesRepository
        );
        $this->usersService = new UsersService(
            $this->usersRepository,
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

        $this->bookingsService = new BookingsService(
            $this->bookingsRepository,
            $this->housesService,
            $this->usersService
        );

        $this->truncateTables();
        $this->createTestData();
    }

    private function truncateTables(): void
    {
        $connection = $this->entityManager->getConnection();
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

        $this->testUser1 = (new User())
            ->setPhoneNumber('+1234567890')
            ->setTelegramChatId(12345)
            ->setTelegramUserId(67890)
            ->setTelegramUsername('test_user1');
        $this->entityManager->persist($this->testUser1);

        $this->testUser2 = (new User())
            ->setPhoneNumber('+9876543210')
            ->setTelegramChatId(54321)
            ->setTelegramUserId(98765)
            ->setTelegramUsername('test_user2');
        $this->entityManager->persist($this->testUser2);

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
            ->setComment('Test comment 1')
            ->setStartDate(
                (new DateTimeImmutable())->modify('+1 week')
            )
            ->setEndDate(
                (new DateTimeImmutable())->modify('+2 week')
            )
            ->setHouse($this->testHouse1)
            ->setUser($this->testUser1);

        $this->testBookings[] = (new Booking())
            ->setStartDate(
                (new DateTimeImmutable())->modify('+3 week')
            )
            ->setEndDate(
                (new DateTimeImmutable())->modify('+4 week')
            )
            ->setHouse($this->testHouse2)
            ->setUser($this->testUser2);

        foreach ($this->testBookings as $booking) {
            $this->entityManager->persist($booking);
        }
        $this->entityManager->flush();
    }

    private function assertBookingsEqual(Booking $expected, Booking $actual): void
    {
        $this->assertEquals($expected->getId(), $actual->getId());
        $this->assertEquals($expected->getComment(), $actual->getComment());
        $this->assertEquals($expected->getStartDate()->format('Y-m-d'), $actual->getStartDate()->format('Y-m-d'));
        $this->assertEquals($expected->getEndDate()->format('Y-m-d'), $actual->getEndDate()->format('Y-m-d'));
        $this->assertEquals($expected->getHouse()->getId(), $actual->getHouse()->getId());
        $this->assertEquals($expected->getUser()->getId(), $actual->getUser()->getId());
    }

    public function testCreateBooking(): void
    {
        $startDate = (new DateTimeImmutable())->modify('+5 week');
        $endDate   = (new DateTimeImmutable())->modify('+6 week');

        $result = $this->bookingsService->createBooking(
            $this->testHouse1->getId(),
            $this->testUser1->getPhoneNumber(),
            'New booking comment',
            $startDate,
            $endDate,
        );

        $this->assertNull($result);

        $bookings = $this->bookingsRepository->findAll();
        $this->assertCount(count($this->testBookings) + 1, $bookings);

        $newBooking = $bookings[2];
        $this->assertEquals('New booking comment', $newBooking->getComment());
        $this->assertEquals($this->testHouse1->getId(), $newBooking->getHouse()->getId());
    }

    public function testCreateBookingWithInvalidDates(): void
    {
        // Past start date
        $result = $this->bookingsService->createBooking(
            $this->testHouse1->getId(),
            '+1111222333',
            null,
            new DateTimeImmutable('2020-01-01'),
            new DateTimeImmutable('2025-01-05'),
            11111,
            22222,
            'new_user'
        );
        $this->assertNotNull($result);

        // Start date after end date
        $result = $this->bookingsService->createBooking(
            $this->testHouse1->getId(),
            '+1111222333',
            null,
            new DateTimeImmutable('2025-01-05'),
            new DateTimeImmutable('2025-01-01'),
            11111,
            22222,
            'new_user'
        );
        $this->assertNotNull($result);
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

    public function testFindBookingsByUserId(): void
    {
        $bookings = $this->bookingsService->findBookingsByUserId(
            $this->testUser1->getId()
        );
        $this->assertCount(1, $bookings);
        $this->assertEquals($this->testBookings[0]->getId(), $bookings[0]->getId());
    }

    public function testUpdateBooking(): void
    {
        $booking        = $this->testBookings[0];
        $updatedBooking = (new Booking())
            ->setComment('Updated comment');

        $validationError = $this->bookingsService->validateBookingUpdate($updatedBooking, $booking->getId());
        $this->assertNull($validationError);

        $updatedBookingResult = $this->bookingsService->updateBooking($updatedBooking, $booking->getId());

        $this->assertEquals(
            'Updated comment',
            $updatedBookingResult->getComment()
        );
        $this->assertEquals(
            $booking->getHouse()->getId(),
            $updatedBookingResult->getHouse()->getId()
        );
    }

    public function testReplaceBooking(): void
    {
        $booking          = $this->testBookings[0];
        $replacingBooking = (new Booking())
            ->setComment('Replaced booking')
            ->setHouse($this->testHouse2)
            ->setStartDate(new DateTimeImmutable('2025-01-10'))
            ->setEndDate(new DateTimeImmutable('2025-01-15'))
            ->setUser($this->testUser1);

        $validationError = $this->bookingsService->validateBookingReplacement($replacingBooking, $booking->getId());
        $this->assertNull($validationError);

        $replacedBooking = $this->bookingsService->replaceBooking($replacingBooking, $booking->getId());

        $this->assertEquals(
            'Replaced booking',
            $replacedBooking->getComment()
        );
        $this->assertEquals(
            $this->testHouse2->getId(),
            $replacedBooking->getHouse()->getId()
        );
    }

    public function testDeleteBooking(): void
    {
        $bookingId = $this->testBookings[0]->getId();

        // Try to delete non-existent booking
        $validationError = $this->bookingsService->validateBookingDeletion(999);
        $this->assertNotNull($validationError);

        // Delete existing booking
        $validationError = $this->bookingsService->validateBookingDeletion($bookingId);
        $this->assertNull($validationError);

        $this->bookingsService->deleteBooking($bookingId);

        $bookings = $this->bookingsService->findAllBookings();
        $this->assertCount(1, $bookings);
    }

    public function testValidateHouseAvailability(): void
    {
        // House is available for new dates
        $error = $this->bookingsService->validateHouseAvailability(
            $this->testHouse1,
            (new DateTimeImmutable())->modify('+3 week'),
            (new DateTimeImmutable())->modify('+4 week')
        );

        $this->assertNull($error);

        // House is not available (conflict with existing booking)
        $error = $this->bookingsService->validateHouseAvailability(
            $this->testHouse1,
            (new DateTimeImmutable())->modify('+1 week'),
            (new DateTimeImmutable())->modify('+2 week')
        );
        $this->assertNotNull($error);
    }
}
