<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\CountriesMessages;
use App\Entity\Country;
use App\Service\CountriesService;
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

#[Route('/api/v1/countries', name: 'countries_api')]
class CountriesController extends AbstractController
{
    public function __construct(
        private CountriesService $countryService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/', name: 'countries_list', methods: ['GET'])]
    public function listCountries(): JsonResponse
    {
        $countries = array_map(
            fn ($country) => $country->toArray(),
            $this->countryService->findAllCountries()
        );

        return new JsonResponse($countries, Response::HTTP_OK);
    }

    #[Route('/', name: 'countries_add', methods: ['POST'])]
    public function addCountry(Request $request): JsonResponse
    {
        $country = $this->deserializeCountry($request);
        if ($country instanceof JsonResponse) {
            return $country;
        }

        $error = $this->validateCountry($country);
        if ($error) {
            return $error;
        }

        $this->countryService->addCountry($country);
        return new JsonResponse(
            CountriesMessages::created(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'countries_get_by_id', methods: ['GET'])]
    public function getCountry(int $id): JsonResponse
    {
        $result = $this->countryService->findCountryById($id);
        return $result['country']
            ? new JsonResponse(
                $result['country']->toArray(),
                Response::HTTP_OK
            )
            : new JsonResponse(
                CountriesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
    }

    #[Route('/{id}', name: 'countries_update_by_id', methods: ['PATCH'])]
    public function updateCountry(Request $request, int $id): JsonResponse
    {
        $updatedCountry = $this->deserializeCountry($request);
        if ($updatedCountry instanceof JsonResponse) {
            return $updatedCountry;
        }

        $result = $this->countryService->updateCountry($updatedCountry, $id);
        return $result
            ? new JsonResponse(
                CountriesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            )
            : new JsonResponse(
                CountriesMessages::updated(),
                Response::HTTP_OK
            );
    }

    #[Route('/{id}', name: 'countries_delete', methods: ['DELETE'])]
    public function deleteCountry(int $id): JsonResponse
    {
        $result = $this->countryService->deleteCountry($id);

        if ($result === CountriesMessages::NOT_FOUND) {
            return new JsonResponse(
                CountriesMessages::notFound(),
                Response::HTTP_NOT_FOUND
            );
        }

        if ($result === CountriesMessages::HAS_CITIES) {
            return new JsonResponse(
                CountriesMessages::hasCities(),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            CountriesMessages::deleted(),
            Response::HTTP_OK
        );
    }

    private function deserializeCountry(Request $request): Country | JsonResponse
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return new JsonResponse(
                CountriesMessages::buildMessage(
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
                Country::class,
                'json'
            );
        } catch (NotEncodableValueException | UnexpectedValueException $e) {
            return new JsonResponse(
                CountriesMessages::buildMessage(
                    'Deserialization failed',
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    private function validateCountry(Country $country): ?JsonResponse
    {
        $errors = $this->validator->validate($country);
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
            CountriesMessages::buildMessage(
                'Validation failed',
                $errorsArray
            ),
            Response::HTTP_BAD_REQUEST
        );
    }
}
