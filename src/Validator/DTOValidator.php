<?php

declare(strict_types=1);

namespace App\Validator;

use App\DTO\BaseDTO;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DTOValidator
{
    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(
        private ValidatorInterface $validator
    ) {
    }

    /**
     * @param BaseDTO $dto
     * @return array<array{field: string, message: string}>
     */
    public function validate(BaseDTO $dto): ?array
    {
        $validationErrors = $this->validator->validate($dto);

        if (count($validationErrors) === 0) {
            return null;
        }

        return $this->formatErrors($validationErrors);
    }

    /**
     * @param ConstraintViolationListInterface $validationErrors
     * @return array<array{field: string, message: string}>
     */
    private function formatErrors(
        ConstraintViolationListInterface $validationErrors
    ): array {
        $formattedErrors = [];

        foreach ($validationErrors as $error) {
            $formattedErrors[] = [
                'field' => (new UnicodeString(
                    $error->getPropertyPath()
                ))->snake()->toString(),
                'message' => (string) $error->getMessage(),
            ];
        }

        return $formattedErrors;
    }
}
