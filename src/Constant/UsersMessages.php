<?php

declare (strict_types=1);

namespace App\Constant;

/**
 * Class BookingsMessages
 * @package App\Constant
 *
 * This class contains messages related to the User entity.
 */
class UsersMessages extends ApiMessages
{
    public const LOGIN    = 'Login successful!';
    public const LOGOUT   = 'Logout successful!';
    public const REGISTER = 'User registered successfully!';
    public const REFRESH  = 'Token refreshed successfully!';

    public const LOGIN_FAILED        = 'Login failed.';
    public const LOGOUT_FAILED       = 'Logout failed.';
    public const NOT_FOUND           = 'User not found.';
    public const INVALID_CREDENTIALS = 'Invalid credentials.';
    public const REFRESH_FAILED      = 'Token refresh failed.';
    public const INVALID_REFRESH     = 'Invalid refresh token.';
    public const REGISTRATION_FAILED = 'User registration failed.';
    public const ALREADY_EXISTS      = 'User with this phone number already exists.';

    /**
     * @param string $message
     * @param string $accessToken
     * @param string $refreshToken
     * @param array $errors
     * @return array[]|array{errors: array, message: string, tokens: array{access_token:string,refresh_token:string}}
     */
    public static function buildAuthMessage(
        string $message,
        string $accessToken,
        string $refreshToken,
        array $errors = []
    ): array {
        $response = self::buildMessage(
            $message,
            $errors
        );

        $response['tokens'] = [
          'access_token'  => $accessToken,
          'refresh_token' => $refreshToken,
        ];
        return $response;
    }

    /**
     * @return array|array{errors: array, message: string, tokens: array{access_token: string, refresh_token: string}}
     */
    public static function register(): array
    {
        return self::buildMessage(
            self::REGISTER,
        );
    }

    /**
     * @param string $accessToken
     * @param string $refreshToken
     * @return array|array{errors: array, message: string, tokens: array{access_token: string, refresh_token: string}}
     */
    public static function login(string $accessToken, string $refreshToken): array
    {
        return self::buildAuthMessage(
            self::LOGIN,
            $accessToken,
            $refreshToken
        );
    }

    /**
     * @param string $accessToken
     * @param string $refreshToken
     * @return array|array{errors: array, message: string, tokens: array{access_token: string, refresh_token: string}}
     */
    public static function refresh(string $accessToken, string $refreshToken): array
    {
        return self::buildAuthMessage(
            self::REFRESH,
            $accessToken,
            $refreshToken
        );
    }

    /**
     * @return array|array{errors: array, message: string}
     */
    public static function logout(): array
    {
        return self::buildMessage(
            self::LOGOUT
        );
    }

    /**
     * @param array $errors
     * @return array{errors: array, message: string}
     */
    public static function registrationFailed(array $errors): array
    {
        return self::buildMessage(
            self::REGISTRATION_FAILED,
            $errors
        );
    }

    /**
     * @param array $errors
     * @return array{errors: array, message: string}
     */
    public static function loginFailed(array $errors): array
    {
        return self::buildMessage(
            self::LOGIN_FAILED,
            $errors
        );
    }

    /**
     * @param array $errors
     * @return array{errors: array, message: string}
     */
    public static function logoutFailed(array $errors): array
    {
        return self::buildMessage(
            self::LOGOUT_FAILED,
            $errors
        );
    }

    /**
     * @param array $errors
     * @return array{errors: array, message: string}
     */
    public static function refreshFailed(array $errors): array
    {
        return self::buildMessage(
            self::REFRESH_FAILED,
            $errors
        );
    }

    /**
     * @return array{errors: array, message: string}
     */
    public static function notFound(): array
    {
        return self::buildMessage(
            self::NOT_FOUND
        );
    }
}
