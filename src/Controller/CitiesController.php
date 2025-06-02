<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\CitiesMessages;
use App\DTO\CityDTO;
use App\DTO\DTOFactory;
use App\Entity\City;
use App\Serializer\DTOSerializer;
use App\Service\CitiesService;
use App\Service\CountriesService;
use App\Validator\DTOValidator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/cities', name: 'api_v1_cities_')]
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
            }
        }

        $this->cityService->addCity($city);
        return new JsonResponse(
            CitiesMessages::created(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'get_by_id', methods: ['GET'])]
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
