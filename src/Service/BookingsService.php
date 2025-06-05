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

    public function validateBookingCreation(
        int $houseId,
        ?string $phoneNumber,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        ?string $telegramUsername = null,
        bool $isTelegramUser = false
    ): ?string {
        if ($isTelegramUser) {
            $user = $this->usersService->findUserByTelegramUsername($telegramUsername);
            if (!$user && !$telegramUsername) {
                return UsersMessages::NOT_FOUND;
            }
        } else {
            $user = $this->usersService->findUserByPhoneNumber($phoneNumber);
            if (!$user) {
                return UsersMessages::NOT_FOUND;
            }
        }

        $house = $this->housesService->findHouseById($houseId);
        if (!$house) {
            return HousesMessages::NOT_FOUND;
        }

        $datesValidationError = $this->validateBookingDates($startDate, $endDate);
        if ($datesValidationError) {
            return $datesValidationError;
        }

        $availabilityError = $this->validateHouseAvailability($house, $startDate, $endDate);
        if ($availabilityError) {
            return $availabilityError;
        }

        return null;
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
        $validationError = $this->validateBookingCreation(
            $houseId,
            $phoneNumber,
            $startDate,
            $endDate,
            $telegramUsername,
            $isTelegramUser
        );

        if ($validationError) {
            return $validationError;
        }

        if ($isTelegramUser) {
            if (!$this->usersService->findUserByTelegramUsername($telegramUsername)) {
                $this->usersService->registerTelegramUser(
                    $telegramChatId,
                    $telegramUserId,
                    $telegramUsername
                );
            }

            $user = $this->usersService->findUserByTelegramUsername($telegramUsername);
        } else {
            $user = $this->usersService->findUserByPhoneNumber($phoneNumber);
        }

        $house = $this->housesService->findHouseById($houseId);

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
     * @return string|null
     */
    public function validateBookingReplacement(Booking $replacingBooking, int $id): ?string
    {
        $existingBooking = $this->bookingsRepo->findBookingById($id);
        if (!$existingBooking) {
            return BookingsMessages::NOT_FOUND;
        }

        $houseSwitchError = $this->switchHouse($existingBooking, $replacingBooking);
        if ($houseSwitchError) {
            return $houseSwitchError;
        }

        return null;
    }

    /**
     * @param Booking $replacingBooking
     * @param int $id
     * @return Booking
     */
    public function replaceBooking(Booking $replacingBooking, int $id): Booking
    {
        $replacingBooking->setId($id);
        $this->bookingsRepo->updateBooking($replacingBooking);
        return $replacingBooking;
    }

    /**
     * @param Booking $updatedBooking
     * @param int $id
     * @return string|null
     */
    public function validateBookingUpdate(Booking $updatedBooking, int $id): ?string
    {
        $existingBooking = $this->bookingsRepo->findBookingById($id);
        if (!$existingBooking) {
            return BookingsMessages::NOT_FOUND;
        }

        if ($updatedBooking->getHouse() && $existingBooking->getHouse()->getId() !== $updatedBooking->getHouse()->getId()) {
            $houseId = $updatedBooking->getHouse()->getId();
            $house   = $this->housesService->findHouseById($houseId);
            if (!$house) {
                return HousesMessages::NOT_FOUND;
            }
        }

        if (
            $updatedBooking->getHouse() && $existingBooking->getHouse()->getId() !== $updatedBooking->getHouse()->getId()
        ) {
            $houseSwitchError = $this->switchHouse($existingBooking, $updatedBooking);
            if ($houseSwitchError) {
                return $houseSwitchError;
            }
        }

        return null;
    }

    /**
     * @param Booking $updatedBooking
     * @param int $id
     * @return Booking
     */
    public function updateBooking(Booking $updatedBooking, int $id): Booking
    {
        $existingBooking = $this->bookingsRepo->findBookingById($id);

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
            $existingBooking->setHouse($updatedBooking->getHouse());
        }

        $this->bookingsRepo->updateBooking($existingBooking);
        return $existingBooking;
    }

    /**
     * @param int $id
     * @return string|null
     */
    public function validateBookingDeletion(int $id): ?string
    {
        $booking = $this->bookingsRepo->findBookingById($id);
        if (!$booking) {
            return BookingsMessages::NOT_FOUND;
        }

        return null;
    }

    /**
     * @param int $id
     * @return void
     */
    public function deleteBooking(int $id): void
    {
        $this->bookingsRepo->deleteBookingById($id);
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
