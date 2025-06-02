<?php

declare(strict_types=1);

namespace App\Serializer;

use App\DTO\BaseDTO;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\UnicodeString;

class DTOSerializer
{
    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(
        private SerializerInterface $serializer
    ) {
    }

    /**
     * @param Request $request
     * @param string $dtoClass
     * @return BaseDTO
     * @throws Exception
     */
    public function deserialize(Request $request, string $dtoClass): BaseDTO
    {
        if ($request->getContentTypeFormat() !== 'json') {
            throw new Exception('Unsupported content type');
        }

        try {
            $data = array_filter(
                json_decode($request->getContent(), true) ?: [],
                fn ($value) => $value !== null
            );

            foreach ($data as $property => $_) {
                if (! property_exists($dtoClass, (new UnicodeString($property))->camel()->toString())) {
                    throw new Exception("Property '$property' does not exist in $dtoClass");
                }
            }

            $dto = $this->serializer->deserialize(
                json_encode($data),
                $dtoClass,
                'json',
                ['groups' => ['write']]
            );

            return $dto;
        } catch (NotEncodableValueException | UnexpectedValueException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return string
     * @throws Exception
     */
    public function getRefreshTokenFromRequest(Request $request): string
    {
        if ($request->getContentTypeFormat() !== 'json') {
            throw new Exception('Unsupported content type');
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['refresh_token'])) {
                throw new Exception('Field "refresh_token" is required.');
            }

            if (!is_string($data['refresh_token'])) {
                throw new Exception('Field "refresh_token" must be a string.');
            }

            return $data['refresh_token'];
        } catch (NotEncodableValueException | UnexpectedValueException $e) {
            throw new Exception($e->getMessage());
        }
    }
}
