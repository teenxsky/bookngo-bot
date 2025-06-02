<?php

declare(strict_types=1);

namespace App\Controller;

use App\ApiDoc\ApiEndpoint;
use App\ApiDoc\ApiResponse;
use App\Constant\CitiesMessages;
use App\Constant\HousesMessages;
use App\DTO\DTOFactory;
use App\DTO\HouseDTO;
use App\Entity\House;
use App\Serializer\DTOSerializer;
use App\Service\CitiesService;
use App\Service\HousesService;
use App\Validator\DTOValidator;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Houses')]
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
    #[ApiEndpoint(
        method: 'GET',
        path: '/api/v1/houses/',
        summary: 'Get list of houses',
        description: 'Retrieves all houses.',
        requiresAuth: false,
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'List of houses.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(type: HouseDTO::class, groups: ['read'])
                    )
                )
            )
        ]
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
    #[ApiEndpoint(
        method: 'POST',
        requiresAuth: true,
        path: '/api/v1/houses/',
        summary: 'Create a new house',
        description: 'Creates a new house (FOR ADMIN ONLY).',
        requestBody: new OA\RequestBody(
            description: 'House data',
            required: true,
            content: new Model(type: HouseDTO::class, groups: ['write'])
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_CREATED,
                description: 'House created successfully',
                messageExample: HousesMessages::CREATED
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'Validation or Deserialization error',
            ),
            new ApiResponse(
                responseCode: Response::HTTP_NOT_FOUND,
                description: 'City not found',
            ),
        ]
    )]
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
            } else {
                return new JsonResponse(
                    CitiesMessages::notFound(),
                    Response::HTTP_NOT_FOUND
                );
            }
        }

        $this->housesService->addHouse($house);
        return new JsonResponse(
            HousesMessages::created(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'get_by_id', methods: ['GET'])]
    #[ApiEndpoint(
        method: 'GET',
        requiresAuth: false,
        path: '/api/v1/houses/{id}',
        summary: 'Get house by ID',
        description: 'Retrieves house details by ID.',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'House ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'House information',
                content: new Model(type: HouseDTO::class, groups: ['read'])
            ),
            new ApiResponse(
                responseCode: Response::HTTP_NOT_FOUND,
                description: 'House not found',
            ),
        ]
    )]
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
    #[ApiEndpoint(
        method: 'PUT',
        requiresAuth: true,
        path: '/api/v1/houses/{id}',
        summary: 'Replace house by ID',
        description: 'Replaces house details by ID (FOR ADMIN ONLY).',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'House ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            description: 'House data',
            required: true,
            content: new Model(type: HouseDTO::class, groups: ['write'])
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'House replaced successfully',
                messageExample: HousesMessages::REPLACED
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'Validation or Deserialization error',
            ),
            new ApiResponse(
                responseCode: Response::HTTP_NOT_FOUND,
                description: 'House not found',
            ),
        ]
    )]
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
    #[ApiEndpoint(
        method: 'PATCH',
        requiresAuth: true,
        path: '/api/v1/houses/{id}',
        summary: 'Update house by ID',
        description: 'Updates house details by ID (FOR ADMIN ONLY).',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'House ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            description: 'House data (At least one field of entity is required)',
            required: true,
            content: new Model(type: HouseDTO::class, groups: ['write'])
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'House updated successfully',
                messageExample: HousesMessages::UPDATED
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'Validation or Deserialization error',
            ),
            new ApiResponse(
                responseCode: Response::HTTP_NOT_FOUND,
                description: 'Booking or House not found',
            ),
        ]
    )]
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
    #[ApiEndpoint(
        method: 'DELETE',
        requiresAuth: true,
        path: '/api/v1/houses/{id}',
        summary: 'Delete house by ID',
        description: 'Deletes house by ID (FOR ADMIN ONLY).',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'House ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'House deleted successfully',
                messageExample: HousesMessages::DELETED
            ),
            new ApiResponse(
                responseCode: Response::HTTP_NOT_FOUND,
                description: 'House not found',
            ),
        ]
    )]
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
