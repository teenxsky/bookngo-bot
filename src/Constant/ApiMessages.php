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
    public const ACCESS_DENIED          = 'Access denied.';
    public const VALIDATION_FAILED      = 'Validation failed.';
    public const DESERIALIZATION_FAILED = 'Deserialization failed.';

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
     * @return array{message:string,errors?:array}
     */
    public static function accessDenied(array $errors): array
    {
        return self::buildMessage(
            self::ACCESS_DENIED,
            $errors
        );
    }

    /**
     * @param array $errors
     * @return array{message:string,errors?:array}
     */
    public static function validationFailed(array $errors): array
    {
        return self::buildMessage(
            self::VALIDATION_FAILED,
            $errors
        );
    }

    /**
     * @param array $errors
     * @return array{message:string,errors?:array}
     */
    public static function deserializationFailed(array $errors): array
    {
        return self::buildMessage(
            self::DESERIALIZATION_FAILED,
            $errors
        );
    }
}
