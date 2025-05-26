<?php

declare(strict_types=1);

namespace App\Constant;

/**
 * Class CountriesMessages
 * @package App\Constant
 *
 * This class contains messages related to the Country entity.
 */
class CountriesMessages extends ApiMessages
{
    public const CREATED    = 'Country created!';
    public const UPDATED    = 'Country updated!';
    public const DELETED    = 'Country deleted!';
    public const NOT_FOUND  = 'Country not found.';
    public const HAS_CITIES = 'Cannot delete country that has cities';

    /**
     * @return array{message: string, errors?: array}
     */
    public static function created(): array
    {
        return self::buildMessage(self::CREATED);
    }

    /**
     * @return array{message: string, errors?: array}
     */
    public static function updated(): array
    {
        return self::buildMessage(self::UPDATED);
    }

    /**
     * @return array{message: string, errors?: array}
     */
    public static function deleted(): array
    {
        return self::buildMessage(self::DELETED);
    }

    /**
     * @return array{message: string, errors?: array}
     */
    public static function notFound(): array
    {
        return self::buildMessage(self::NOT_FOUND);
    }

    /**
     * @return array{message: string, errors?: array}
     */
    public static function hasCities(): array
    {
        return self::buildMessage(self::HAS_CITIES);
    }
}
