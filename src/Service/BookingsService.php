<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\BookingsMessages;
use App\Constant\HousesMessages;
use App\Constant\UsersMessages;
use App\Entity\Booking;
use App\Entity\House;
use App\Repository\BookingsRepository;
use DateTimeImmutable;
use DateTimeInterface;

class BookingsService
{
    public function __construct(
        private BookingsRepository $bookingsRepo,
        private HousesService $housesService,
        private UsersService $usersService
    ) {
    }

    public function createBooking(
        int $houseId,
        ?string $phoneNumber,
        ?string $comment,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        ?int $telegramChatId = null,
        ?int $telegramUserId = null,
        ?string $telegramUsername = null,
        bool $isTelegramUser = false
    ): ?string {
        if ($isTelegramUser) {
            if (!$this->usersService->findUserByTelegramUsername(
                $telegramUsername
            )) {
                $this->usersService->registerTelegramUser(
                    $telegramChatId,
                    $telegramUserId,
                    $telegramUsername
                );
            }

            $user = $this->usersService->findUserByTelegramUsername($telegramUsername);
        } else {
            $user = $this->usersService->findUserByPhoneNumber($phoneNumber);

            if (!$user) {
                return UsersMessages::NOT_FOUND;
            }
        }

        $result = $this->housesService->findHouseById($houseId);
        if ($result['error']) {
            return $result['error'];
        }
        $house = $result['house'];

        $error = $this->validateBookingDates($startDate, $endDate);
        if ($error) {
            return $error;
        }

        $error = $this->validateHouseAvailability($house, $startDate, $endDate);
        if ($error) {
            return $error;
        }

        $booking = new Booking();
        $booking
            ->setHouse($house)
            ->setUser($user)
            ->setComment($comment)
            ->setStartDate($startDate)
            ->setEndDate($endDate);

        $this->bookingsRepo->addBooking($booking);

        return null;
    }

    /**
     * @param House $house
     * @param DateTimeInterface $startDate
     * @param DateTimeInterface $endDate
     * @return int
     */
    public function calculateTotalPrice(
        House $house,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate
    ): float {
        $interval = $startDate->diff($endDate);
        return $interval->days * $house->getPricePerNight();
    }

    /**
     * @param DateTimeInterface $startDate
     * @param DateTimeInterface $endDate
     * @return string|null
     */
    public function validateBookingDates(DateTimeInterface $startDate, DateTimeInterface $endDate): ?string
    {
        $today = new DateTimeImmutable();

        if ($startDate < $today) {
            return BookingsMessages::PAST_START_DATE;
        }

        if ($startDate > $endDate) {
            return BookingsMessages::PAST_END_DATE;
        }

        return null;
    }

    /**
     * @param House $house
     * @param DateTimeInterface $startDate
     * @param DateTimeInterface $endDate
     * @return string|null
     */
    public function validateHouseAvailability(
        House $house,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate
    ): ?string {
        $availableHouses = $this->housesService->findAvailableHouses(
            $house->getCity()->getId(),
            $startDate,
            $endDate
        );

        foreach ($availableHouses as $availableHouse) {
            if ($availableHouse->getId() === $house->getId()) {
                return null;
            }
        }
        return HousesMessages::NOT_AVAILABLE;
    }

    /**
     * @param Booking $replacingBooking
     * @param int $id
     * @return array{booking:Booking|null,error:string|null}
     */
    public function replaceBooking(Booking $replacingBooking, int $id): array
    {
        $existingBooking = $this->bookingsRepo->findBookingById($id);
        if (!$existingBooking) {
            return [
                'booking' => null,
                'error'   => BookingsMessages::NOT_FOUND
            ];
        }

        $replacingBooking->setId($id);
        $error = $this->switchHouse($existingBooking, $replacingBooking);
        if ($error !== null) {
            return [
                'booking' => null,
                'error'   => $error
            ];
        }

        $this->bookingsRepo->updateBooking($replacingBooking);
        return [
            'booking' => $replacingBooking,
            'error'   => null
        ];
    }

    /**
     * @return array{booking:Booking|null,error:string|null}
     */
    public function updateBooking(Booking $updatedBooking, int $id): array
    {
        $existingBooking = $this->bookingsRepo->findBookingById($id);
        if (!$existingBooking) {
            return [
                'booking' => null,
                'error'   => BookingsMessages::NOT_FOUND
            ];
        }

        $existingBooking
            ->setUser(
                $updatedBooking->getUser() ?? $existingBooking->getUser()
            )
            ->setComment(
                $updatedBooking->getComment() ?? $existingBooking->getComment()
            );

        if (
            $updatedBooking->getHouse() && $existingBooking->getHouse()->getId() !== $updatedBooking->getHouse()->getId()
        ) {
            $error = $this->switchHouse($existingBooking, $updatedBooking);
            if ($error !== null) {
                return [
                    'booking' => null,
                    'error'   => $error
                ];
            }
            $existingBooking->setHouse($updatedBooking->getHouse());
        }

        $this->bookingsRepo->updateBooking($existingBooking);
        return [
            'booking' => $existingBooking,
            'error'   => null
        ];
    }

    /**
     * @return array{booking:Booking|null,error:string|null}
     */
    public function deleteBooking(int $id): array
    {
        $booking = $this->bookingsRepo->findBookingById($id);
        if (!$booking) {
            return [
                'booking' => null,
                'error'   => BookingsMessages::NOT_FOUND
            ];
        }

        $this->bookingsRepo->deleteBookingById($id);
        return [
            'booking' => $booking,
            'error'   => null
        ];
    }

    /**
     * @param Booking $oldBooking
     * @param Booking $newBooking
     * @return string|null
     */
    private function switchHouse(Booking $oldBooking, Booking $newBooking): ?string
    {
        $oldHouse = $oldBooking->getHouse();
        $newHouse = $newBooking->getHouse();

        if (!$newHouse || $oldHouse->getId() === $newHouse->getId()) {
            return null;
        }

        if (!$this->housesService->checkHouseAvailability($newHouse)) {
            return HousesMessages::NOT_AVAILABLE;
        }

        return null;
    }

    /**
     * @return Booking[]
     */
    public function findAllBookings(): array
    {
        return $this->bookingsRepo->findAllBookings();
    }

    /**
     * @param int $id
     * @return Booking|null
     */
    public function findBookingById(int $id): ?Booking
    {
        return $this->bookingsRepo->findBookingById($id);
    }

    /**
     * @param int $userId
     * @param mixed $isActual
     * @return Booking[]
     */
    public function findBookingsByUserId(
        int $userId,
        ?bool $isActual = null
    ): array {
        return $this->bookingsRepo->findBookingsByUserId(
            $userId,
            $isActual
        );
    }
}
