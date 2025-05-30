<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\CountriesMessages;
use App\Entity\Country;
use App\Service\CountriesService;
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

#[Route('/api/v1/countries', name: 'api_v1_countries_')]
class CountriesController extends AbstractController
{
    private EntityValidator $entityValidator;

    public function __construct(
        private CountriesService $countryService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
        $this->entityValidator = new EntityValidator($validator);
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function listCountries(): JsonResponse
    {
        $countries = array_map(
            fn ($country) => $country->toArray(),
            $this->countryService->findAllCountries()
        );

        return new JsonResponse($countries, Response::HTTP_OK);
    }

    #[Route('/', name: 'add', methods: ['POST'])]
    public function addCountry(Request $request): JsonResponse
    {
        $country = $this->deserializeCountry($request);
        if ($country instanceof JsonResponse) {
            return $country;
        }

        $validationError = $this->entityValidator->validate($country);
        if ($validationError) {
            return new JsonResponse(
                CountriesMessages::validationFailed(
                    $validationError
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

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
        return $country
            ? new JsonResponse(
                $country->toArray(),
                Response::HTTP_OK
            )
            : new JsonResponse(
                CountriesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
    }

    #[Route('/{id}', name: 'update_by_id', methods: ['PATCH'])]
    public function updateCountry(Request $request, int $id): JsonResponse
    {
        $updatedCountry = $this->deserializeCountry($request);
        if ($updatedCountry instanceof JsonResponse) {
            return $updatedCountry;
        }

        $validationError = $this->countryService->validateCountryUpdate($id);
        if ($validationError) {
            return new JsonResponse(
                CountriesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        $this->countryService->updateCountry($updatedCountry, $id);
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

    private function deserializeCountry(Request $request): Country | JsonResponse
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return new JsonResponse(
                CountriesMessages::deserializationFailed(
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
                Country::class,
                'json'
            );
        } catch (NotEncodableValueException | UnexpectedValueException $e) {
            return new JsonResponse(
                CountriesMessages::deserializationFailed(
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
