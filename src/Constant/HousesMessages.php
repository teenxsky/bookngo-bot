<?php

declare (strict_types=1);

namespace App\Constant;

/**
 * Class HousesMessages
 * @package App\Constant
 *
 * This class contains messages related to the House entity.
 */
class HousesMessages extends ApiMessages
{
    public const CREATED       = 'House created!';
    public const REPLACED      = 'House replaced!';
    public const UPDATED       = 'House updated!';
    public const DELETED       = 'House deleted!';
    public const NOT_FOUND     = 'House not found.';
    public const BOOKED        = 'Cannot delete booked house.';
    public const NOT_AVAILABLE = 'House is not available for selected dates.';
    public const WRONG_CITY    = 'House does not belong to the selected city.';

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
    public static function replaced(): array
    {
        return self::buildMessage(self::REPLACED);
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
    public static function booked(): array
    {
        return self::buildMessage(self::BOOKED);
    }

    /**
     * @return array{message: string, errors?: array}
     */
    public static function notAvailable(): array
    {
        return self::buildMessage(self::NOT_AVAILABLE);
    }
}
