<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\CitiesMessages;
use App\Constant\HousesMessages;
use App\Entity\House;
use App\Service\CitiesService;
use App\Service\HousesService;
use App\Validator\EntityValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/houses', name: 'api_v1_houses_')]
class HousesController extends AbstractController
{
    private EntityValidator $entityValidator;

    public function __construct(
        private HousesService $housesService,
        private CitiesService $citiesService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
        $this->entityValidator = new EntityValidator($validator);
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function listHouses(): JsonResponse
    {
        $houses = array_map(
            fn ($booking) => $booking->toArray(),
            $this->housesService->findAllHouses()
        );

        return new JsonResponse($houses, Response::HTTP_OK);
    }

    #[Route('/', name: 'add', methods: ['POST'])]
    public function addHouse(Request $request): JsonResponse
    {
        $house = $this->deserializeHouse($request);
        if ($house instanceof JsonResponse) {
            return $house;
        }

        $validationError = $this->entityValidator->validate($house);
        if ($validationError) {
            return new JsonResponse(
                HousesMessages::validationFailed(
                    $validationError
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->housesService->addHouse($house);
        return new JsonResponse(
            HousesMessages::created(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'get_by_id', methods: ['GET'])]
    public function getHouse(int $id): JsonResponse
    {
        $house = $this->housesService->findHouseById($id);
        return $house
            ? new JsonResponse(
                $house->toArray(),
                Response::HTTP_OK
            )
            : new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
    }

    #[Route('/{id}', name: 'replace_by_id', methods: ['PUT'])]
    public function replaceHouse(Request $request, int $id): JsonResponse
    {
        $replacingHouse = $this->deserializeHouse($request);
        if ($replacingHouse instanceof JsonResponse) {
            return $replacingHouse;
        }

        $validationError = $this->entityValidator->validate($replacingHouse);
        if ($validationError) {
            return new JsonResponse(
                HousesMessages::validationFailed(
                    $validationError
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationError = $this->housesService->validateHouseReplacement($id);
        if ($validationError) {
            return new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $this->housesService->replaceHouse($replacingHouse, $id);
        return new JsonResponse(
            HousesMessages::replaced(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'update_by_id', methods: ['PATCH'])]
    public function updateHouse(Request $request, int $id): JsonResponse
    {
        $updatedHouse = $this->deserializeHouse($request);
        if ($updatedHouse instanceof JsonResponse) {
            return $updatedHouse;
        }

        $validationError = $this->housesService->validateHouseUpdate($id);
        if ($validationError) {
            return new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $this->housesService->updateHouseFields($updatedHouse, $id);
        return new JsonResponse(
            HousesMessages::updated(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function deleteHouse(int $id): JsonResponse
    {
        $validationError = $this->housesService->validateHouseDeletion($id);

        if ($validationError === HousesMessages::NOT_FOUND) {
            return new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($validationError === HousesMessages::BOOKED) {
            return new JsonResponse(
                HousesMessages::booked(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->housesService->deleteHouse($id);
        return new JsonResponse(
            HousesMessages::deleted(),
            Response::HTTP_OK
        );
    }

    private function deserializeHouse(Request $request): House | JsonResponse
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return new JsonResponse(
                HousesMessages::deserializationFailed(
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
                $city = $this->citiesService->findCityById($data['city_id']);

                if (!$city) {
                    return new JsonResponse(
                        HousesMessages::deserializationFailed(
                            [CitiesMessages::NOT_FOUND]
                        ),
                        Response::HTTP_BAD_REQUEST
                    );
                }
                $house->setCity($city);
            }

            return $house;
        } catch (NotEncodableValueException | UnexpectedValueException $e) {
            return new JsonResponse(
                HousesMessages::deserializationFailed(
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
