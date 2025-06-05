<?php

declare(strict_types=1);

namespace App\Controller;

use App\ApiDoc\ApiEndpoint;
use App\ApiDoc\ApiResponse;
use App\Constant\CitiesMessages;
use App\Constant\CountriesMessages;
use App\DTO\CityDTO;
use App\DTO\DTOFactory;
use App\Entity\City;
use App\Serializer\DTOSerializer;
use App\Service\CitiesService;
use App\Service\CountriesService;
use App\Validator\DTOValidator;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/cities', name: 'api_v1_cities_')]
#[OA\Tag(name: 'Cities')]
class CitiesController extends AbstractController
{
    public function __construct(
        private CitiesService $cityService,
        private CountriesService $countriesService,
        private DTOSerializer $dtoSerializer,
        private DTOValidator $dtoValidator,
        private DTOFactory $dtoFactory
    ) {
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    #[ApiEndpoint(
        method: 'GET',
        path: '/api/v1/cities/',
        summary: 'Get list of cities',
        description: 'Retrieves all cities.',
        requiresAuth: false,
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'List of cities',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(type: CityDTO::class, groups: ['read'])
                    )
                )
            )
        ]
    )]
    public function listCities(): JsonResponse
    {
        $cityDTOs = $this->dtoFactory->createFromEntities(
            $this->cityService->findAllCities()
        );

        return new JsonResponse(
            array_map(fn ($dto) => $dto->toArray(), $cityDTOs),
            Response::HTTP_OK
        );
    }

    #[Route('/', name: 'add', methods: ['POST'])]
    #[ApiEndpoint(
        method: 'POST',
        path: '/api/v1/cities/',
        summary: 'Add a new city',
        description: 'Creates a new city (FOR ADMIN ONLY).',
        requiresAuth: true,
        requestBody: new OA\RequestBody(
            description: 'Booking data',
            required: true,
            content: new Model(type: CityDTO::class, groups: ['write'])
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_CREATED,
                description: 'City created successfully',
                messageExample: CitiesMessages::CREATED
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'Validation or Deserialization error',
            ),
            new ApiResponse(
                responseCode: Response::HTTP_NOT_FOUND,
                description: 'Country not found',
            ),
        ]
    )]
    public function addCity(Request $request): JsonResponse
    {
        try {
            /** @var CityDTO $cityDTO */
            $cityDTO = $this->dtoSerializer->deserialize(
                $request,
                CityDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                CitiesMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationErrors = $this->dtoValidator->validate($cityDTO);
        if ($validationErrors) {
            return new JsonResponse(
                CitiesMessages::validationFailed($validationErrors),
                Response::HTTP_BAD_REQUEST
            );
        }

        $city = new City();
        $city->setName($cityDTO->name);

        if ($cityDTO->countryId) {
            $country = $this->countriesService->findCountryById($cityDTO->countryId);
            if ($country) {
                $city->setCountry($country);
            } else {
                return new JsonResponse(
                    CountriesMessages::notFound(),
                    Response::HTTP_NOT_FOUND
                );
            }
        }

        $this->cityService->addCity($city);
        return new JsonResponse(
            CitiesMessages::created(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'get_by_id', methods: ['GET'])]
    #[ApiEndpoint(
        method: 'GET',
        requiresAuth: false,
        path: '/api/v1/cities/{id}',
        summary: 'Get city by ID',
        description: 'Retrieves city details by ID.',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'City ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'City information',
                content: new Model(type: CityDTO::class, groups: ['read'])
            ),
            new ApiResponse(
                responseCode: Response::HTTP_NOT_FOUND,
                description: 'City not found',
            ),
        ]
    )]
    public function getCity(int $id): JsonResponse
    {
        $city = $this->cityService->findCityById($id);

        if (!$city) {
            return new JsonResponse(
                CitiesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $cityDTO = CityDTO::createFromEntity($city);

        return new JsonResponse(
            $cityDTO->toArray(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'update_by_id', methods: ['PATCH'])]
    #[ApiEndpoint(
        method: 'PATCH',
        requiresAuth: true,
        path: '/api/v1/cities/{id}',
        summary: 'Update city by ID',
        description: 'Updates city details by ID (FOR ADMIN ONLY).',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'City ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            description: 'City data (At least one field of entity is required)',
            required: true,
            content: new Model(type: CityDTO::class, groups: ['write'])
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'City updated successfully',
                messageExample: CitiesMessages::UPDATED
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
    public function updateCity(Request $request, int $id): JsonResponse
    {
        try {
            /** @var CityDTO $cityDTO */
            $cityDTO = $this->dtoSerializer->deserialize(
                $request,
                CityDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                CitiesMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationError = $this->cityService->validateCityUpdate($id);
        if ($validationError) {
            return new JsonResponse(
                CitiesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $city = new City();
        $city->setName($cityDTO->name);

        if ($cityDTO->countryId) {
            $country = $this->countriesService->findCountryById($cityDTO->countryId);
            if ($country) {
                $city->setCountry($country);
            }
        }

        $this->cityService->updateCity($city, $id);
        return new JsonResponse(
            CitiesMessages::updated(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[ApiEndpoint(
        method: 'DELETE',
        requiresAuth: true,
        path: '/api/v1/cities/{id}',
        summary: 'Deletes city by ID',
        description: 'Deletes city details by ID (FOR ADMIN ONLY).',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'City ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'City deleted successfully',
                messageExample: CitiesMessages::DELETED
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'City has houses',
                messageExample: CitiesMessages::HAS_HOUSES
            ),
            new ApiResponse(
                responseCode: Response::HTTP_NOT_FOUND,
                description: 'City not found',
            ),
        ]
    )]
    public function deleteCity(int $id): JsonResponse
    {
        $validationError = $this->cityService->validateCityDeletion($id);

        if ($validationError === CitiesMessages::NOT_FOUND) {
            return new JsonResponse(
                CitiesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($validationError === CitiesMessages::HAS_HOUSES) {
            return new JsonResponse(
                CitiesMessages::hasHouses(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->cityService->deleteCity($id);
        return new JsonResponse(
            CitiesMessages::deleted(),
            Response::HTTP_OK
        );
    }
}
