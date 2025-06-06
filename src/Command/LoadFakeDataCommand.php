<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\BookingsService;
use App\Service\HousesService;
use App\Service\UsersService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @psalm-suppress UnusedClass
 */
#[AsCommand(
    name: 'app:load-fake-data',
    description: 'Load fake data into the database',
)]
class LoadFakeDataCommand extends Command
{
    private User $apiUser;
    private User $telegramUser;

    public function __construct(
        private readonly UsersService $usersService,
        private readonly HousesService $housesService,
        private readonly BookingsService $bookingsService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    #[Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->entityManager->beginTransaction();
            $this->loadFakeUsers($io);
            $this->entityManager->commit();

            $io->title('Loading Fake Bookings.');
            if ($io->confirm('Do you want to fake Bookings?')) {
                $this->entityManager->beginTransaction();
                $this->loadFakeBookings($io);
                $this->entityManager->commit();
            } else {
                $io->info('Loading fake Bookings cancelled.');
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            $io->error('Error loading data: ' . $e->getMessage());
            $io->info('Rollback completed!');
            return Command::FAILURE;
        }
    }

    private function loadFakeUsers(SymfonyStyle $io): void
    {
        $io->title('Loading test users...');

        // User #1 (API USER)
        $io->section('Loading api user...');
        $phoneNumber = $this->generateUniquePhoneNumber();
        $password    = (string) rand(100000, 999999);
        $this->usersService->registerApiUser($phoneNumber, $password);
        $this->apiUser = $this->usersService->findUserByPhoneNumber($phoneNumber);
        $io->success('API user loaded successfully.');

        // User #2 (TELEGRAM USER)
        $io->section('Loading telegram user...');
        $telegramChatId   = -1;
        $telegramUserId   = -1;
        $telegramUsername = $this->generateUniqueTelegramUsername();
        $this->usersService->registerTelegramUser($telegramChatId, $telegramUserId, $telegramUsername);
        $this->telegramUser = $this->usersService->findUserByTelegramUsername($telegramUsername);
        $io->success('Telegram user loaded successfully.');
    }

    private function loadFakeBookings(SymfonyStyle $io): void
    {
        $io->title('Loading test users...');

        // Booking #1 (API USER)
        $io->section('Loading booking #1...');
        $dates = $this->generateRandomDates();

        $startDate = $dates[0];
        $endDate   = $dates[1];

        $houseId = $this->findAvailableHouseId($startDate, $endDate);
        if ($houseId === null) {
            throw new Exception('No available house found for booking #1.');
        }

        $creationError = $this->bookingsService->createBooking(
            houseId: $houseId,
            phoneNumber: $this->apiUser->getPhoneNumber(),
            comment: '',
            startDate: $startDate,
            endDate: $endDate,
            isTelegramUser: false
        );
        if ($creationError !== null) {
            throw new Exception($creationError);
        }
        $io->success('Booking #1 loaded successfully.');

        // Booking #2 (Telegram USER)
        $io->section('Loading booking #2...');
        $dates = $this->generateRandomDates();

        $startDate = $dates[0];
        $endDate   = $dates[1];

        $houseId = $this->findAvailableHouseId($startDate, $endDate);
        if ($houseId === null) {
            throw new Exception('No available house found for booking #2.');
        }

        $creationError = $this->bookingsService->createBooking(
            houseId: $houseId,
            phoneNumber: null,
            comment: 'I\'ll come at 8 in the morning',
            startDate: $startDate,
            endDate: $endDate,
            telegramUsername: $this->telegramUser->getTelegramUsername(),
            isTelegramUser: true
        );
        if ($creationError !== null) {
            throw new Exception($creationError);
        }
        $io->success('Booking #2 loaded successfully.');
    }

    /**
     * @return string
     */
    private function generateUniquePhoneNumber(): string
    {
        do {
            $phoneNumber = '+' . implode('', array_map(fn ($_) => mt_rand(0, 9), range(1, 14)));
        } while ($this->usersService->findUserByPhoneNumber($phoneNumber));

        return $phoneNumber;
    }

    /**
     * @return string
     */
    private function generateUniqueTelegramUsername(): string
    {
        do {
            $telegramUsername = 'bookngo_tg_test_user_' . rand(100000, 999999);
        } while ($this->usersService->findUserByTelegramUsername($telegramUsername));

        return $telegramUsername;
    }

    /**
     * @return array<int, DateTimeImmutable>
     */
    private function generateRandomDates(): array
    {
        $startWeek = rand(20, 100);
        $endWeek   = rand($startWeek + 1, $startWeek + 10);

        $startDate = new DateTimeImmutable('+' . $startWeek . ' week');
        $endDate   = new DateTimeImmutable('+' . $endWeek . ' week');

        return [$startDate, $endDate];
    }

    /**
     * @param DateTimeImmutable $startDate
     * @param DateTimeImmutable $endDate
     * @return int|null
     */
    private function findAvailableHouseId(DateTimeImmutable $startDate, DateTimeImmutable $endDate): ?int
    {
        $houses = $this->housesService->findAvailableHouses(
            cityId: null,
            startDate: $startDate,
            endDate: $endDate
        );

        if (empty($houses)) {
            return null;
        }

        $randomHouse = $houses[array_rand($houses)];
        return $randomHouse->getId();
    }
}
