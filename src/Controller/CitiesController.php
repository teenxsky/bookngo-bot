<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\CitiesMessages;
use App\Entity\City;
use App\Service\CitiesService;
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

#[Route('/api/v1/cities', name: 'api_v1_cities_')]
class CitiesController extends AbstractController
{
    private EntityValidator $entityValidator;

    public function __construct(
        private CitiesService $cityService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
        $this->entityValidator = new EntityValidator($validator);
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function listCities(): JsonResponse
    {
        $cities = array_map(
            fn ($city) => $city->toArray(),
            $this->cityService->findAllCities()
        );

        return new JsonResponse($cities, Response::HTTP_OK);
    }

    #[Route('/', name: 'add', methods: ['POST'])]
    public function addCity(Request $request): JsonResponse
    {
        $city = $this->deserializeCity($request);
        if ($city instanceof JsonResponse) {
            return $city;
        }

        $error = $this->entityValidator->validate($city);
        if ($error) {
            return new JsonResponse(
                CitiesMessages::validationFailed(
                    $error
                ),
                Response::HTTP_BAD_REQUEST
            );
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

    #[Route('/{id}', name: 'update_by_id', methods: ['PATCH'])]
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

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
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
                CitiesMessages::deserializationFailed(
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
                CitiesMessages::deserializationFailed(
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
