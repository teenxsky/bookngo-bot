<?php

declare(strict_types=1);

namespace App\Constant\Telegram;

/**
 * This class contains buttons for keyboard related to the Telegram bot.
 */
class Buttons
{
    /**
     * @param string $text
     * @param string $callbackData
     * @return array{callback_data:string, text:string}
     */
    public static function buildButton(string $text, string $callbackData): array
    {
        return [
          'text'          => $text,
          'callback_data' => $callbackData,
        ];
    }

    /**
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function mainMenu(string $callback): array
    {
        return self::buildButton(
            '🏠 Main Menu',
            $callback
        );
    }

    /**
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function actualBookings(string $callback): array
    {
        return self::buildButton(
            '✅ Actual',
            $callback
        );
    }

    /**
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function archivedBookings(string $callback): array
    {
        return self::buildButton(
            '🗄 Archived',
            $callback
        );
    }

    /**
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function editComment(string $callback): array
    {
        return self::buildButton(
            '✏️ Edit Comment',
            $callback
        );
    }

    /**
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function deleteBooking(string $callback): array
    {
        return self::buildButton(
            '❌ Delete',
            $callback
        );
    }

    /**
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function back(string $callback): array
    {
        return self::buildButton(
            '⬅️ Back',
            $callback
        );
    }

    /**
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function newBooking(string $callback): array
    {
        return self::buildButton(
            '🏠 New Booking',
            $callback
        );
    }

    /**
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function myBookings(string $callback): array
    {
        return self::buildButton(
            '📋 My Bookings',
            $callback
        );
    }

    /**
     * @param string $countryName
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function country(string $countryName, string $callback): array
    {
        return self::buildButton(
            "🌍 {$countryName}",
            $callback
        );
    }

    /**
     * @param string $cityName
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function city(string $cityName, string $callback): array
    {
        return self::buildButton(
            "🏙️ {$cityName}",
            $callback
        );
    }

    /**
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function confirm(string $callback): array
    {
        return self::buildButton(
            '✅ Confirm',
            $callback
        );
    }

    /**
     * @param string $address
     * @param string $callback
     * @return array{callback_data: string, text: string}
     */
    public static function bookingAddress(string $address, string $callback): array
    {
        return self::buildButton(
            '📍 ' . $address,
            $callback
        );
    }
}
