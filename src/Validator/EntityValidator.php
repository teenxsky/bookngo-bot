<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\String\UnicodeString;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EntityValidator
{
    public function __construct(
        private ValidatorInterface $validator
    ) {
    }

    /**
     * @param object $entity
     * @return array{field: string, message: string[]|null}
     */
    public function validate(object $entity): ?array
    {
        $errors = $this->validator->validate($entity);

        if (count($errors) === 0) {
            return null;
        }

        return $this->formatErrors($errors);
    }

    /**
     * @param ConstraintViolationListInterface $errors
     * @return array<array{field: string, message: string}>
     */
    private function formatErrors(ConstraintViolationListInterface $errors): array
    {
        $formattedErrors = [];

        foreach ($errors as $error) {
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
