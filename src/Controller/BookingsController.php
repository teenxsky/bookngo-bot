<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\BookingsMessages;
use App\Constant\HousesMessages;
use App\Entity\Booking;
use App\Service\BookingsService;
use App\Service\HousesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/bookings', name: 'bookings_api')]
class BookingsController extends AbstractController
{
    public function __construct(
        private BookingsService $bookingsService,
        private HousesService $housesService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/', name: 'bookings_list', methods: ['GET'])]
    public function listBookings(): JsonResponse
    {
        $bookings = array_map(
            fn ($booking) => $booking->toArray(),
            $this->bookingsService->findAllBookings()
        );

        return new JsonResponse($bookings, Response::HTTP_OK);
    }

    #[Route('/', name: 'bookings_add', methods: ['POST'])]
    public function addBooking(Request $request): JsonResponse
    {
        $booking = $this->deserializeBooking($request);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }

        $error = $this->validateBooking($booking);
        if ($error) {
            return $error;
        }

        $error = $this->bookingsService->createBooking(
            $booking->getHouse() ? $booking->getHouse()->getId() : -1,
            $booking->getPhoneNumber(),
            $booking->getComment(),
            $booking->getStartDate(),
            $booking->getEndDate(),
            $booking->getTelegramChatId(),
            $booking->getTelegramUserId(),
            $booking->getTelegramUsername()
        );

        if ($error === HousesMessages::NOT_FOUND) {
            return new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($error === HousesMessages::NOT_AVAILABLE) {
            return new JsonResponse(
                HousesMessages::notAvailable(),
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($error === BookingsMessages::PAST_START_DATE || $error === BookingsMessages::PAST_END_DATE) {
            return new JsonResponse(
                BookingsMessages::buildMessage('Validation failed', [$error]),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            BookingsMessages::created(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'bookings_get_by_id', methods: ['GET'])]
    public function getBooking(int $id): JsonResponse
    {
        $booking = $this->bookingsService->findBookingById($id);
        return $booking
            ? new JsonResponse(
                $booking->toArray(),
                Response::HTTP_OK
            )
            : new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
    }

    #[Route('/{id}', name: 'bookings_replace_by_id', methods: ['PUT'])]
    public function replaceBooking(Request $request, int $id): JsonResponse
    {
        $booking = $this->deserializeBooking($request);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }

        $error = $this->validateBooking($booking);
        if ($error) {
            return $error;
        }

        $result = $this->bookingsService->replaceBooking($booking, $id);
        if ($result['error'] === BookingsMessages::NOT_FOUND) {
            return new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($result['error'] === HousesMessages::NOT_AVAILABLE) {
            return new JsonResponse(
                HousesMessages::notAvailable(),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            BookingsMessages::replaced(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'bookings_update_by_id', methods: ['PATCH'])]
    public function updateBooking(Request $request, int $id): JsonResponse
    {
        $booking = $this->deserializeBooking($request);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }

        $result = $this->bookingsService->updateBooking($booking, $id);
        if ($result['error'] === BookingsMessages::NOT_FOUND) {
            return new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($result['error'] === HousesMessages::NOT_AVAILABLE) {
            return new JsonResponse(
                HousesMessages::notAvailable(),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            BookingsMessages::updated(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'bookings_delete_by_id', methods: ['DELETE'])]
    public function deleteBooking(int $id): JsonResponse
    {
        $result = $this->bookingsService->deleteBooking($id);
        if ($result['error'] === BookingsMessages::NOT_FOUND) {
            return new JsonResponse(
                BookingsMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(
            BookingsMessages::deleted(),
            Response::HTTP_OK
        );
    }

    private function deserializeBooking(Request $request): Booking | JsonResponse
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return new JsonResponse(
                BookingsMessages::buildMessage(
                    'Deserialization failed',
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
                $result = $this->housesService->findHouseById(
                    (int) $data['house_id']
                );

                if ($result['house']) {
                    $booking->setHouse($result['house']);
                }
            }

            return $booking;
        } catch (NotEncodableValueException | UnexpectedValueException $e) {
            return new JsonResponse(
                BookingsMessages::buildMessage(
                    'Deserialization failed',
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    private function validateBooking(Booking $booking): ?JsonResponse
    {
        $errors = $this->validator->validate($booking);

        if (count($errors) === 0) {
            return null;
        }

        $errorsArray = [];
        foreach ($errors as $error) {
            $errorsArray[] = [
                'field'   => (new UnicodeString($error->getPropertyPath()))->snake(),
                'message' => $error->getMessage(),
            ];
        }

        return new JsonResponse(
            BookingsMessages::buildMessage(
                'Validation failed',
                $errorsArray
            ),
            Response::HTTP_BAD_REQUEST
        );
    }
}
