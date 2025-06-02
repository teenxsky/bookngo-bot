<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\HousesMessages;
use App\DTO\DTOFactory;
use App\DTO\HouseDTO;
use App\Entity\House;
use App\Serializer\DTOSerializer;
use App\Service\CitiesService;
use App\Service\HousesService;
use App\Validator\DTOValidator;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/houses', name: 'api_v1_houses_')]
class HousesController extends AbstractController
{
    public function __construct(
        private HousesService $housesService,
        private CitiesService $citiesService,
        private DTOSerializer $dtoSerializer,
        private DTOValidator $dtoValidator,
        private DTOFactory $dtoFactory
    ) {
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/houses/',
        summary: 'Get list of houses',
        description: 'Retrieves all houses'
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'List of houses',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'address', type: 'string'),
                    new OA\Property(property: 'price_per_night', type: 'number', format: 'float'),
                    new OA\Property(property: 'bedrooms_count', type: 'integer'),
                    new OA\Property(property: 'has_wifi', type: 'boolean'),
                    new OA\Property(property: 'has_kitchen', type: 'boolean'),
                    new OA\Property(property: 'has_air_conditioning', type: 'boolean'),
                    new OA\Property(property: 'has_parking', type: 'boolean'),
                    new OA\Property(property: 'has_sea_view', type: 'boolean'),
                    new OA\Property(property: 'image_url', type: 'string', nullable: true),
                    new OA\Property(property: 'city', type: 'object')
                ]
            )
        )
    )]
    public function listHouses(): JsonResponse
    {
        $houseDTOs = $this->dtoFactory->createFromEntities(
            $this->housesService->findAllHouses()
        );

        return new JsonResponse(
            array_map(fn ($dto) => $dto->toArray(), $houseDTOs),
            Response::HTTP_OK
        );
    }

    #[Route('/', name: 'add', methods: ['POST'])]
    public function addHouse(Request $request): JsonResponse
    {
        try {
            /** @var HouseDTO $houseDTO */
            $houseDTO = $this->dtoSerializer->deserialize(
                $request,
                HouseDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                HousesMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationErrors = $this->dtoValidator->validate($houseDTO);
        if ($validationErrors) {
            return new JsonResponse(
                HousesMessages::validationFailed($validationErrors),
                Response::HTTP_BAD_REQUEST
            );
        }

        $house = new House();
        $this->dtoFactory->mapToEntity($houseDTO, $house);

        if ($houseDTO->cityId) {
            $city = $this->citiesService->findCityById($houseDTO->cityId);
            if ($city) {
                $house->setCity($city);
            }
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

        if (!$house) {
            return new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $houseDTO = HouseDTO::createFromEntity($house);

        return new JsonResponse(
            $houseDTO->toArray(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'replace_by_id', methods: ['PUT'])]
    public function replaceHouse(Request $request, int $id): JsonResponse
    {
        try {
            /** @var HouseDTO $houseDTO */
            $houseDTO = $this->dtoSerializer->deserialize(
                $request,
                HouseDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                HousesMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationErrors = $this->dtoValidator->validate($houseDTO);
        if ($validationErrors) {
            return new JsonResponse(
                HousesMessages::validationFailed($validationErrors),
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

        $house = new House();
        $this->dtoFactory->mapToEntity($houseDTO, $house);

        if ($houseDTO->cityId) {
            $city = $this->citiesService->findCityById($houseDTO->cityId);
            if ($city) {
                $house->setCity($city);
            }
        }

        $this->housesService->replaceHouse($house, $id);
        return new JsonResponse(
            HousesMessages::replaced(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'update_by_id', methods: ['PATCH'])]
    public function updateHouse(Request $request, int $id): JsonResponse
    {
        try {
            /** @var HouseDTO $houseDTO */
            $houseDTO = $this->dtoSerializer->deserialize(
                $request,
                HouseDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                HousesMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationError = $this->housesService->validateHouseUpdate($id);
        if ($validationError) {
            return new JsonResponse(
                HousesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $house = new House();
        $this->dtoFactory->mapToEntity($houseDTO, $house);

        if ($houseDTO->cityId) {
            $city = $this->citiesService->findCityById($houseDTO->cityId);
            if ($city) {
                $house->setCity($city);
            }
        }

        $this->housesService->updateHouseFields($house, $id);
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
}
