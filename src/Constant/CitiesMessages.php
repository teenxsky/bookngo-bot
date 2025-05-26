<?php

declare(strict_types=1);

namespace App\Constant;

/**
 * Class CitiesMessages
 * @package App\Constant
 *
 * This class contains messages related to the City entity.
 */
class CitiesMessages extends ApiMessages
{
    public const CREATED       = 'City created!';
    public const UPDATED       = 'City updated!';
    public const DELETED       = 'City deleted!';
    public const NOT_FOUND     = 'City not found.';
    public const WRONG_COUNTRY = 'City does not belong to the specified country.';
    public const HAS_HOUSES    = 'Cannot delete city that has houses.';

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
    public static function hasHouses(): array
    {
        return self::buildMessage(self::HAS_HOUSES);
    }
}
