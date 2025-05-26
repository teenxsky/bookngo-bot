<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\CitiesMessages;
use App\Entity\City;
use App\Service\CitiesService;
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

#[Route('/api/v1/cities', name: 'cities_api')]
class CitiesController extends AbstractController
{
    public function __construct(
        private CitiesService $cityService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/', name: 'cities_list', methods: ['GET'])]
    public function listCities(): JsonResponse
    {
        $cities = array_map(
            fn ($city) => $city->toArray(),
            $this->cityService->findAllCities()
        );

        return new JsonResponse($cities, Response::HTTP_OK);
    }

    #[Route('/', name: 'cities_add', methods: ['POST'])]
    public function addCity(Request $request): JsonResponse
    {
        $city = $this->deserializeCity($request);
        if ($city instanceof JsonResponse) {
            return $city;
        }

        $error = $this->validateCity($city);
        if ($error) {
            return $error;
        }

        $this->cityService->addCity($city);
        return new JsonResponse(
            CitiesMessages::created(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'cities_get_by_id', methods: ['GET'])]
    public function getCity(int $id): JsonResponse
    {
        $result = $this->cityService->findCityById($id);
        return $result['city']
            ? new JsonResponse(
                $result['city']->toArray(),
                Response::HTTP_OK
            )
            : new JsonResponse(
                CitiesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
    }

    #[Route('/{id}', name: 'cities_update_by_id', methods: ['PATCH'])]
    public function updateCity(Request $request, int $id): JsonResponse
    {
        $updatedCity = $this->deserializeCity($request);
        if ($updatedCity instanceof JsonResponse) {
            return $updatedCity;
        }

        $result = $this->cityService->updateCity($updatedCity, $id);
        return $result
            ? new JsonResponse(
                CitiesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            )
            : new JsonResponse(
                CitiesMessages::updated(),
                Response::HTTP_OK
            );
    }

    #[Route('/{id}', name: 'cities_delete', methods: ['DELETE'])]
    public function deleteCity(int $id): JsonResponse
    {
        $result = $this->cityService->deleteCity($id);

        if ($result === CitiesMessages::NOT_FOUND) {
            return new JsonResponse(
                CitiesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($result === CitiesMessages::HAS_HOUSES) {
            return new JsonResponse(
                CitiesMessages::hasHouses(),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            CitiesMessages::deleted(),
            Response::HTTP_OK
        );
    }

    private function deserializeCity(Request $request): City | JsonResponse
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return new JsonResponse(
                CitiesMessages::buildMessage(
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
                )
            );

            return $this->serializer->deserialize(
                json_encode($data),
                City::class,
                'json'
            );
        } catch (NotEncodableValueException | UnexpectedValueException $e) {
            return new JsonResponse(
                CitiesMessages::buildMessage(
                    'Deserialization failed',
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    private function validateCity(City $city): ?JsonResponse
    {
        $errors = $this->validator->validate($city);
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
            CitiesMessages::buildMessage(
                'Validation failed',
                $errorsArray
            ),
            Response::HTTP_BAD_REQUEST
        );
    }
}
