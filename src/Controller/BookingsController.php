<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\BookingsMessages;
use App\Constant\HousesMessages;
use App\Constant\UsersMessages;
use App\DTO\BookingDTO;
use App\DTO\DTOFactory;
use App\Entity\Booking;
use App\Entity\User;
use App\Serializer\DTOSerializer;
use App\Service\BookingsService;
use App\Service\HousesService;
use App\Service\UsersService;
use App\Validator\DTOValidator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/bookings', name: 'api_v1_bookings_')]
class BookingsController extends AbstractController
{
    public function __construct(
        private BookingsService $bookingsService,
        private HousesService $housesService,
        private UsersService $usersService,
        private DTOSerializer $dtoSerializer,
        private DTOValidator $dtoValidator,
        private DTOFactory $dtoFactory
    ) {
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function listBookings(#[CurrentUser] User $user): JsonResponse
    {
        if ($this->usersService->isAdmin($user)) {
            $bookings = $this->dtoFactory->createFromEntities(
                $this->bookingsService->findAllBookings()
            );
        } else {
            $actualBookings = $this->dtoFactory->createFromEntities(
                $this->bookingsService->findBookingsByUserId(
                    $user->getId(),
                    true
                )
            );

            $archivedBookings = $this->dtoFactory->createFromEntities(
                $this->bookingsService->findBookingsByUserId(
                    $user->getId(),
                    false
                )
            );

            $bookings = array_merge($actualBookings, $archivedBookings);
        }

        return new JsonResponse(
            array_map(fn ($dto) => $dto->toArray(), $bookings),
            Response::HTTP_OK
        );
    }

    #[Route('/', name: 'add', methods: ['POST'])]
    public function addBooking(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            /** @var BookingDTO $bookingDTO */
            $bookingDTO = $this->dtoSerializer->deserialize(
                $request,
                BookingDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                BookingsMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationErrors = $this->dtoValidator->validate($bookingDTO);
        if ($validationErrors) {
            return new JsonResponse(
                BookingsMessages::validationFailed($validationErrors),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationError = $this->bookingsService->validateBookingCreation(
            $bookingDTO->houseId ?? -1,
            $user->getPhoneNumber(),
            $bookingDTO->startDate,
            $bookingDTO->endDate
        );

        if ($validationError) {
            if ($validationError === UsersMessages::NOT_FOUND) {
                return new JsonResponse(
                    UsersMessages::notFound(),
                    Response::HTTP_NOT_FOUND
                );
            }

            if ($validationError === HousesMessages::NOT_FOUND) {
                return new JsonResponse(
                    HousesMessages::notFound(),
                    Response::HTTP_NOT_FOUND
                );
            }

            if ($validationError === HousesMessages::NOT_AVAILABLE) {
                return new JsonResponse(
                    HousesMessages::notAvailable(),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (
                $validationError === BookingsMessages::PAST_START_DATE || $validationError === BookingsMessages::PAST_END_DATE
            ) {
                return new JsonResponse(
                    BookingsMessages::validationFailed([$validationError]),
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        $bookingError = $this->bookingsService->createBooking(
            $bookingDTO->houseId ?? -1,
            $user->getPhoneNumber(),
            $bookingDTO->comment,
            $bookingDTO->startDate,
            $bookingDTO->endDate,
        );

        if ($bookingError === UsersMessages::NOT_FOUND) {
            return new JsonResponse(
                UsersMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($bookingError === HousesMessages::NOT_FOUND) {
            return new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($bookingError === HousesMessages::NOT_AVAILABLE) {
            return new JsonResponse(
                HousesMessages::notAvailable(),
                Response::HTTP_BAD_REQUEST
            );
        }

        if (
            $bookingError === BookingsMessages::PAST_START_DATE || $bookingError === BookingsMessages::PAST_END_DATE
        ) {
            return new JsonResponse(
                BookingsMessages::validationFailed([$bookingError]),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            BookingsMessages::created(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'get_by_id', methods: ['GET'])]
    public function getBooking(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $booking = $this->bookingsService->findBookingById($id);

        if (!$booking) {
            return new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $isAdmin = $this->usersService->isAdmin($user);
        $isOwner = $booking->getUser()->getId() === $user->getId();
        if (!$isAdmin && !$isOwner) {
            return new JsonResponse(
                BookingsMessages::accessDenied(
                    ['You cannot get other users bookings']
                ),
                Response::HTTP_FORBIDDEN
            );
        }

        $bookingDTO = BookingDTO::createFromEntity($booking);

        return new JsonResponse(
            $bookingDTO->toArray(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'replace_by_id', methods: ['PUT'])]
    public function replaceBooking(
        Request $request,
        int $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        try {
            /** @var BookingDTO $bookingDTO */
            $bookingDTO = $this->dtoSerializer->deserialize(
                $request,
                BookingDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                BookingsMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationErrors = $this->dtoValidator->validate($bookingDTO);
        if ($validationErrors) {
            return new JsonResponse(
                BookingsMessages::validationFailed($validationErrors),
                Response::HTTP_BAD_REQUEST
            );
        }

        $existingBooking = $this->bookingsService->findBookingById($id);
        if (!$existingBooking) {
            return new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $isAdmin = $this->usersService->isAdmin($user);
        $isOwner = $existingBooking->getUser()->getId() === $user->getId();
        if (!$isAdmin && !$isOwner) {
            return new JsonResponse(
                BookingsMessages::accessDenied(
                    ['You cannot replace other users bookings']
                ),
                Response::HTTP_FORBIDDEN
            );
        }

        $booking = new Booking();
        $this->dtoFactory->mapToEntity($bookingDTO, $booking);
        $booking->setUser($user);

        if ($bookingDTO->startDate) {
            $booking->setStartDate($bookingDTO->startDate);
        }
        if ($bookingDTO->endDate) {
            $booking->setEndDate($bookingDTO->endDate);
        }
        if ($bookingDTO->houseId) {
            $house = $this->housesService->findHouseById($bookingDTO->houseId);
            if ($house) {
                $booking->setHouse($house);
            }
        }

        $validationError = $this->bookingsService->validateBookingReplacement($booking, $id);
        if ($validationError === BookingsMessages::NOT_FOUND) {
            return new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($validationError === HousesMessages::NOT_AVAILABLE) {
            return new JsonResponse(
                HousesMessages::notAvailable(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->bookingsService->replaceBooking($booking, $id);

        return new JsonResponse(
            BookingsMessages::replaced(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'update_by_id', methods: ['PATCH'])]
    public function updateBooking(
        Request $request,
        int $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        try {
            /** @var BookingDTO $bookingDTO */
            $bookingDTO = $this->dtoSerializer->deserialize(
                $request,
                BookingDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                BookingsMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $existingBooking = $this->bookingsService->findBookingById($id);
        if (!$existingBooking) {
            return new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $isAdmin = $this->usersService->isAdmin($user);
        $isOwner = $existingBooking->getUser()->getId() === $user->getId();
        if (!$isAdmin && !$isOwner) {
            return new JsonResponse(
                BookingsMessages::accessDenied(
                    ['You cannot update other users bookings']
                ),
                Response::HTTP_FORBIDDEN
            );
        }

        $booking = new Booking();
        $this->dtoFactory->mapToEntity($bookingDTO, $booking);
        $booking->setUser($user);

        if ($bookingDTO->houseId) {
            $house = $this->housesService->findHouseById($bookingDTO->houseId);
            if ($house) {
                $booking->setHouse($house);
            }
        }

        $validationError = $this->bookingsService->validateBookingUpdate($booking, $id);
        if ($validationError === BookingsMessages::NOT_FOUND) {
            return new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($validationError === HousesMessages::NOT_AVAILABLE) {
            return new JsonResponse(
                HousesMessages::notAvailable(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->bookingsService->updateBooking($booking, $id);

        return new JsonResponse(
            BookingsMessages::updated(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'delete_by_id', methods: ['DELETE'])]
    public function deleteBooking(
        int $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        $booking = $this->bookingsService->findBookingById($id);
        if (!$booking) {
            return new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $isAdmin = $this->usersService->isAdmin($user);
        $isOwner = $booking->getUser()->getId() === $user->getId();
        if (!$isAdmin && !$isOwner) {
            return new JsonResponse(
                BookingsMessages::accessDenied(
                    ['You cannot delete other users bookings']
                ),
                Response::HTTP_FORBIDDEN
            );
        }

        $validationError = $this->bookingsService->validateBookingDeletion($id);
        if ($validationError === BookingsMessages::NOT_FOUND) {
            return new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $this->bookingsService->deleteBooking($id);

        return new JsonResponse(
            BookingsMessages::deleted(),
            Response::HTTP_OK
        );
    }
}
