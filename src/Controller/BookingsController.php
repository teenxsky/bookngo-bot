<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\BookingsMessages;
use App\Constant\HousesMessages;
use App\Constant\UsersMessages;
use App\Entity\Booking;
use App\Entity\User;
use App\Service\BookingsService;
use App\Service\HousesService;
use App\Service\UsersService;
use App\Validator\EntityValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/bookings', name: 'api_v1_bookings_')]
class BookingsController extends AbstractController
{
    private EntityValidator $entityValidator;

    public function __construct(
        private BookingsService $bookingsService,
        private HousesService $housesService,
        private UsersService $usersService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
        $this->entityValidator = new EntityValidator($validator);
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function listBookings(#[CurrentUser] User $user): JsonResponse
    {
        if ($this->usersService->isAdmin($user)) {
            $bookings = array_map(
                fn ($booking) => $booking->toArray(),
                $this->bookingsService->findAllBookings()
            );
        } else {
            $bookings = array_merge(
                array_map(
                    fn ($booking) => $booking->toArray(),
                    $this->bookingsService->findBookingsByUserId(
                        userId: $user->getId(),
                        isActual: true
                    )
                ),
                array_map(
                    fn ($booking) => $booking->toArray(),
                    $this->bookingsService->findBookingsByUserId(
                        userId: $user->getId(),
                        isActual: false
                    )
                )
            );
        }

        return new JsonResponse(
            $bookings,
            Response::HTTP_OK
        );
    }

    #[Route('/', name: 'add', methods: ['POST'])]
    public function addBooking(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $booking = $this->deserializeBooking($request);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }

        $validationError = $this->entityValidator->validate($booking);
        if ($validationError) {
            return new JsonResponse(
                BookingsMessages::validationFailed($validationError),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationError = $this->bookingsService->validateBookingCreation(
            $booking->getHouse() ? $booking->getHouse()->getId() : -1,
            $user->getPhoneNumber(),
            $booking->getStartDate(),
            $booking->getEndDate()
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
            $booking->getHouse() ? $booking->getHouse()->getId() : -1,
            $user->getPhoneNumber(),
            $booking->getComment(),
            $booking->getStartDate(),
            $booking->getEndDate(),
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
                BookingsMessages::validationFailed(
                    ['You cannot get other users bookings']
                ),
                Response::HTTP_FORBIDDEN
            );
        }

        return new JsonResponse(
            $booking->toArray(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'replace_by_id', methods: ['PUT'])]
    public function replaceBooking(
        Request $request,
        int $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        $booking = $this->deserializeBooking($request);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }

        $validationError = $this->entityValidator->validate($booking);
        if ($validationError) {
            return new JsonResponse(
                BookingsMessages::validationFailed(
                    $validationError
                ),
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
                BookingsMessages::validationFailed(
                    ['You cannot replace other users bookings']
                ),
                Response::HTTP_FORBIDDEN
            );
        }

        $booking->setUser($user);

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
        $booking = $this->deserializeBooking($request);
        if ($booking instanceof JsonResponse) {
            return $booking;
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
                BookingsMessages::validationFailed(
                    ['You cannot update other users bookings']
                ),
                Response::HTTP_FORBIDDEN
            );
        }

        $booking->setUser($user);

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
                BookingsMessages::validationFailed(
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

    private function deserializeBooking(Request $request): Booking | JsonResponse
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return new JsonResponse(
                BookingsMessages::deserializationFailed(
                    ['Unsupported content type']
                ),
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE
            );
        }

        try {
            $data = array_filter(
                json_decode(
                    $request->getContent(),
                    true
                ),
                fn ($value) => $value !== null
            );

            $booking = $this->serializer->deserialize(
                json_encode($data),
                Booking::class,
                'json'
            );

            if (isset($data['house_id'])) {
                $house = $this->housesService->findHouseById(
                    (int) $data['house_id']
                );

                if ($house) {
                    $booking->setHouse($house);
                }
            }

            return $booking;
        } catch (NotEncodableValueException | UnexpectedValueException $e) {
            return new JsonResponse(
                BookingsMessages::deserializationFailed(
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
