<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\CountriesMessages;
use App\DTO\CountryDTO;
use App\DTO\DTOFactory;
use App\Entity\Country;
use App\Serializer\DTOSerializer;
use App\Service\CountriesService;
use App\Validator\DTOValidator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/countries', name: 'api_v1_countries_')]
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
