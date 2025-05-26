<?php

declare (strict_types=1);

namespace App\Constant;

/**
 * Class ApiMessages
 * @package App\Constant
 *
 * This class contains messages related to API responses.
 */
class ApiMessages
{
    /**
     * @param string $message
     * @param array $errors
     * @return array{message:string,errors?:array}
     */
    public static function buildMessage(string $message, array $errors = []): array
    {
        return ! empty($errors)
        ? [
            'message' => $message,
            'errors'  => $errors,
        ]
        : [
            'message' => $message,
        ];
    }

    /**
     * @param array $errors
     * @return array{errors: array, message: string}
     */
    public static function validationFailed(array $errors): array
    {
        return self::buildMessage(
            'Validation failed',
            $errors
        );
    }

    /**
     * @param array $errors
     * @return array{errors: array, message: string}
     */
    public static function deserializationFailed(array $errors): array
    {
        return self::buildMessage(
            'Deserialization failed',
            $errors
        );
    }
}
