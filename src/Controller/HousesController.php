<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\HousesMessages;
use App\Entity\House;
use App\Service\CitiesService;
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

#[Route('/api/v1/houses', name: 'houses_api')]
class HousesController extends AbstractController
{
    public function __construct(
        private HousesService $housesService,
        private CitiesService $citiesService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/', name: 'houses_list', methods: ['GET'])]
    public function listHouses(): JsonResponse
    {
        $houses = array_map(
            fn ($booking) => $booking->toArray(),
            $this->housesService->findAllHouses()
        );

        return new JsonResponse($houses, Response::HTTP_OK);
    }

    #[Route('/', name: 'houses_add', methods: ['POST'])]
    public function addHouse(Request $request): JsonResponse
    {
        $house = $this->deserializeHouse($request);
        if ($house instanceof JsonResponse) {
            return $house;
        }

        $error = $this->validateHouse($house);
        if ($error) {
            return $error;
        }

        $this->housesService->addHouse($house);
        return new JsonResponse(
            HousesMessages::created(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'houses_get_by_id', methods: ['GET'])]
    public function getHouse(int $id): JsonResponse
    {
        $result = $this->housesService->findHouseById($id);
        return $result['house']
            ? new JsonResponse(
                $result['house']->toArray(),
                Response::HTTP_OK
            )
            : new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
    }

    #[Route('/{id}', name: 'houses_replace_by_id', methods: ['PUT'])]
    public function replaceHouse(Request $request, int $id): JsonResponse
    {
        $replacingHouse = $this->deserializeHouse($request);
        if ($replacingHouse instanceof JsonResponse) {
            return $replacingHouse;
        }

        $error = $this->validateHouse($replacingHouse);
        if ($error) {
            return $error;
        }

        $result = $this->housesService->replaceHouse($replacingHouse, $id);
        return $result
            ? new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            )
            : new JsonResponse(
                HousesMessages::replaced(),
                Response::HTTP_OK
            );
    }

    #[Route('/{id}', name: 'houses_update_by_id', methods: ['PATCH'])]
    public function updateHouse(Request $request, int $id): JsonResponse
    {
        $updatedHouse = $this->deserializeHouse($request);
        if ($updatedHouse instanceof JsonResponse) {
            return $updatedHouse;
        }

        $result = $this->housesService->updateHouseFields($updatedHouse, $id);
        return $result
            ? new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            )
            : new JsonResponse(
                HousesMessages::updated(),
                Response::HTTP_OK
            );
    }

    #[Route('/{id}', name: 'houses_delete', methods: ['DELETE'])]
    public function deleteHouse(int $id): JsonResponse
    {
        $result = $this->housesService->deleteHouse($id);

        if ($result === HousesMessages::NOT_FOUND) {
            return new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($result === HousesMessages::BOOKED) {
            return new JsonResponse(
                HousesMessages::booked(),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            HousesMessages::deleted(),
            Response::HTTP_OK
        );
    }

    private function deserializeHouse(Request $request): House | JsonResponse
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return new JsonResponse(
                HousesMessages::buildMessage(
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

            $house = $this->serializer->deserialize(
                json_encode($data),
                House::class,
                'json'
            );

            if (isset($data['city_id'])) {
                $result = $this->citiesService->findCityById(
                    (int) $data['city_id']
                );

                if ($result['city']) {
                    $house->setCity($result['city']);
                }
            }

            return $house;
        } catch (NotEncodableValueException | UnexpectedValueException $e) {
            return new JsonResponse(
                HousesMessages::buildMessage(
                    'Deserialization failed',
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    private function validateHouse(House $house): ?JsonResponse
    {
        $errors = $this->validator->validate($house);
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
            HousesMessages::buildMessage(
                'Validation failed',
                $errorsArray
            ),
            Response::HTTP_BAD_REQUEST
        );
    }
}
