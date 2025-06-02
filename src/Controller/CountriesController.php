<?php

declare(strict_types=1);

namespace App\Controller;

use App\ApiDoc\ApiEndpoint;
use App\ApiDoc\ApiResponse;
use App\Constant\CountriesMessages;
use App\DTO\CountryDTO;
use App\DTO\DTOFactory;
use App\Entity\Country;
use App\Serializer\DTOSerializer;
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

#[Route('/api/v1/countries', name: 'api_v1_countries_')]
#[OA\Tag(name: 'Countries')]
class CountriesController extends AbstractController
{
    public function __construct(
        private CountriesService $countryService,
        private DTOSerializer $dtoSerializer,
        private DTOValidator $dtoValidator,
        private DTOFactory $dtoFactory
    ) {
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    #[ApiEndpoint(
        method: 'GET',
        path: '/api/v1/countries/',
        summary: 'Get list of countries',
        description: 'Retrieves all countries',
        requiresAuth: false,
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'List of countries',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(type: CountryDTO::class, groups: ['read'])
                    )
                )
            )
        ]
    )]
    public function listCountries(): JsonResponse
    {
        $countryDTOs = $this->dtoFactory->createFromEntities(
            $this->countryService->findAllCountries()
        );

        return new JsonResponse(
            array_map(fn ($dto) => $dto->toArray(), $countryDTOs),
            Response::HTTP_OK
        );
    }

    #[Route('/', name: 'add', methods: ['POST'])]
    #[ApiEndpoint(
        method: 'POST',
        path: '/api/v1/countries/',
        summary: 'Add a new country',
        description: 'Creates a new country (FOR ADMIN ONLY).',
        requiresAuth: true,
        requestBody: new OA\RequestBody(
            description: 'Country data',
            required: true,
            content: new Model(type: CountryDTO::class, groups: ['write'])
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_CREATED,
                description: 'Country created successfully',
                messageExample: CountriesMessages::CREATED
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'Validation or Deserialization error',
            ),
        ]
    )]
    public function addCountry(Request $request): JsonResponse
    {
        try {
            /** @var CountryDTO $countryDTO */
            $countryDTO = $this->dtoSerializer->deserialize(
                $request,
                CountryDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                CountriesMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationErrors = $this->dtoValidator->validate($countryDTO);
        if ($validationErrors) {
            return new JsonResponse(
                CountriesMessages::validationFailed($validationErrors),
                Response::HTTP_BAD_REQUEST
            );
        }

        $country = new Country();
        $country->setName($countryDTO->name);

        $this->countryService->addCountry($country);
        return new JsonResponse(
            CountriesMessages::created(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'get_by_id', methods: ['GET'])]
    #[ApiEndpoint(
        method: 'GET',
        requiresAuth: false,
        path: '/api/v1/countries/{id}',
        summary: 'Get country by ID',
        description: 'Retrieves country details by ID.',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Country ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'Country information',
                content: new Model(type: CountryDTO::class, groups: ['read'])
            ),
            new ApiResponse(
                responseCode: Response::HTTP_NOT_FOUND,
                description: 'Country not found',
            ),
        ]
    )]
    public function getCountry(int $id): JsonResponse
    {
        $country = $this->countryService->findCountryById($id);

        if (!$country) {
            return new JsonResponse(
                CountriesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $countryDTO = CountryDTO::createFromEntity($country);

        return new JsonResponse(
            $countryDTO->toArray(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'update_by_id', methods: ['PATCH'])]
    #[ApiEndpoint(
        method: 'PATCH',
        requiresAuth: true,
        path: '/api/v1/countries/{id}',
        summary: 'Update country by ID',
        description: 'Updates country details by ID (FOR ADMIN ONLY).',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Country ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            description: 'Country data (At least one field of entity is required)',
            required: true,
            content: new Model(type: CountryDTO::class, groups: ['write'])
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'Country updated successfully',
                messageExample: CountriesMessages::UPDATED
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
    public function updateCountry(Request $request, int $id): JsonResponse
    {
        try {
            /** @var CountryDTO $countryDTO */
            $countryDTO = $this->dtoSerializer->deserialize(
                $request,
                CountryDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                CountriesMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationError = $this->countryService->validateCountryUpdate($id);
        if ($validationError) {
            return new JsonResponse(
                CountriesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $country = new Country();
        $country->setName($countryDTO->name);

        $this->countryService->updateCountry($country, $id);
        return new JsonResponse(
            CountriesMessages::updated(),
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[ApiEndpoint(
        method: 'DELETE',
        requiresAuth: true,
        path: '/api/v1/countries/{id}',
        summary: 'Deletes country by ID',
        description: 'Deletes country details by ID (FOR ADMIN ONLY).',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Country ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'Country deleted successfully',
                messageExample: CountriesMessages::DELETED
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'Country has cities',
                messageExample: CountriesMessages::HAS_CITIES
            ),
            new ApiResponse(
                responseCode: Response::HTTP_NOT_FOUND,
                description: 'Country not found',
            ),
        ]
    )]
    public function deleteCountry(int $id): JsonResponse
    {
        $validationError = $this->countryService->validateCountryDeletion($id);

        if ($validationError === CountriesMessages::NOT_FOUND) {
            return new JsonResponse(
                CountriesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($validationError === CountriesMessages::HAS_CITIES) {
            return new JsonResponse(
                CountriesMessages::hasCities(),
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->countryService->deleteCountry($id);
        return new JsonResponse(
            CountriesMessages::deleted(),
            Response::HTTP_OK
        );
    }
}
