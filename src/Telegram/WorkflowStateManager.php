<?php

declare(strict_types=1);

namespace App\Telegram;

use InvalidArgumentException;

final class WorkflowStateManager
{
    public const START     = '/start';
    public const MAIN_MENU = 'main_menu';

    public const BOOKINGS_MENU     = 'bookings_menu';
    public const BOOKINGS_LIST     = 'bookings_list';
    public const BOOKING_INFO      = 'booking_info';
    public const EDIT_PHONE_NUMBER = 'edit_phone_number';
    public const EDIT_COMMENT      = 'edit_comment';
    public const DELETE_BOOKING    = 'delete_booking';

    public const NEW_BOOKING     = 'new_booking';
    public const CITIES          = 'cities';
    public const DATES           = 'dates';
    public const HOUSES_LIST     = 'houses_list';
    public const PHONE_NUMBER    = 'phone_number';
    public const COMMENT         = 'comment';
    public const BOOKING_SUMMARY = 'booking_summary';
    public const BOOKING_CONFIRM = 'confirm_booking';

    public const STATES = [
      self::MAIN_MENU => [
          'prev' => null,
          'next' => null
      ],
      self::BOOKINGS_MENU => [
          'prev' => self::MAIN_MENU,
          'next' => self::BOOKINGS_LIST
      ],
      self::BOOKINGS_LIST => [
        'prev' => self::BOOKINGS_MENU,
        'next' => self::BOOKING_INFO
      ],
      self::BOOKING_INFO => [
        'prev' => self::BOOKINGS_LIST,
        'next' => null
      ],
      self::EDIT_PHONE_NUMBER => [
        'prev' => self::BOOKING_INFO,
        'next' => self::BOOKING_INFO
      ],
      self::EDIT_COMMENT => [
        'prev' => self::BOOKING_INFO,
        'next' => self::BOOKING_INFO
      ],
      self::DELETE_BOOKING => [
        'prev' => self::BOOKINGS_LIST,
        'next' => null
      ],
      self::NEW_BOOKING => [
          'prev' => self::MAIN_MENU,
          'next' => self::CITIES
      ],
      self::CITIES => [
          'prev' => self::NEW_BOOKING,
          'next' => self::DATES
      ],
      self::DATES => [
          'prev' => self::CITIES,
          'next' => self::HOUSES_LIST
      ],
      self::HOUSES_LIST => [
          'prev' => self::DATES,
          'next' => self::PHONE_NUMBER
      ],
      self::PHONE_NUMBER => [
          'prev' => self::HOUSES_LIST,
          'next' => self::COMMENT
      ],
      self::COMMENT => [
          'prev' => self::PHONE_NUMBER,
          'next' => self::BOOKING_SUMMARY,
      ],
      self::BOOKING_SUMMARY => [
          'prev' => self::COMMENT,
          'next' => self::BOOKING_CONFIRM
      ],
      self::BOOKING_CONFIRM => [
          'prev' => self::BOOKING_SUMMARY,
          'next' => self::MAIN_MENU,
      ],
    ];

    private const CALLBACK_FORMATS = [
      self::MAIN_MENU => [
          'format' => self::MAIN_MENU,
          'keys'   => [],
      ],
      self::BOOKINGS_MENU => [
          'format' => self::BOOKINGS_MENU,
          'keys'   => [],
      ],
      self::BOOKINGS_LIST => [
          'format' => self::BOOKINGS_LIST . '_%d',
          'keys'   => ['is_actual'],
      ],
      self::BOOKING_INFO => [
          'format' => self::BOOKING_INFO . '_%d',
          'keys'   => ['booking_id']
      ],
      self::EDIT_PHONE_NUMBER => [
          'format' => self::EDIT_PHONE_NUMBER,
          'keys'   => [],
      ],
      self::EDIT_COMMENT => [
          'format' => self::EDIT_COMMENT,
          'keys'   => [],
      ],
      self::DELETE_BOOKING => [
          'format' => self::DELETE_BOOKING,
          'keys'   => [],
      ],
      self::NEW_BOOKING => [
          'format' => self::NEW_BOOKING,
          'keys'   => [],
      ],
      self::CITIES => [
          'format' => self::CITIES . '_%d',
          'keys'   => ['country_id'],
      ],
      self::DATES => [
          'format' => self::DATES . '_%d',
          'keys'   => ['city_id'],
      ],
      self::HOUSES_LIST => [
          'format' => self::HOUSES_LIST . '_%d %s %s',
          'keys'   => ['city_id', 'start_date', 'end_date'],
      ],
      self::PHONE_NUMBER => [
          'format' => self::PHONE_NUMBER,
          'keys'   => [],
      ],
      self::COMMENT => [
          'format' => self::COMMENT,
          'keys'   => [],
      ],
      self::BOOKING_SUMMARY => [
          'format' => self::BOOKING_SUMMARY,
          'keys'   => [],
      ],
      self::BOOKING_CONFIRM => [
          'format' => self::BOOKING_CONFIRM,
          'keys'   => [],
      ],
    ];

    /**
     * @param string $state
     * @return string|null
     */
    public static function getPrev(string $state): ?string
    {
        return self::STATES[$state]['prev'] ?? null;
    }

    /**
     * @param string $state
     * @return string|null
     */
    public static function getNext(string $state): ?string
    {
        return self::STATES[$state]['next'] ?? null;
    }

    /**
     * @param string $state
     * @param array<string,mixed> $stateParams Named state parameters array
     * @param mixed $callbackParams Individual callback parameters
     * @return string
     */
    public function buildCallback(
        string $state,
        array $stateParams = [],
        mixed $callbackParams = null,
    ): string {
        $this->ensureStateExists($state);

        $format = self::CALLBACK_FORMATS[$state]['format'];
        $keys   = self::CALLBACK_FORMATS[$state]['keys'];

        if (($callbackParams === null && empty($stateParams)) || empty($keys)) {
            return $format;
        }

        if ($callbackParams !== null) {
            $params = is_array($callbackParams) ? $callbackParams : [$callbackParams];
            if (count($params) !== count($keys)) {
                throw new InvalidArgumentException(
                    sprintf('Expected %d parameters, got %d', count($keys), count($params))
                );
            }
            return sprintf($format, ...$params);
        }

        $args = [];
        foreach ($keys as $key) {
            if (!isset($stateParams[$key])) {
                throw new InvalidArgumentException("Missing required parameter: $key");
            }
            $args[] = $stateParams[$key];
        }

        return sprintf($format, ...$args);
    }

    /**
     * @param string $state
     * @param string $callback
     * @return array{
     *      house_id?:int,
     *      city_id?:int,
     *      country_id?:int,
     *      start_date?:string,
     *      end_date?:string,
     *      is_actual:?string,
     *      booking_id?:int,
     * }|null
     */
    public function extractCallbackData(string $state, string $callback): ?array
    {
        $this->ensureStateExists($state);

        $format = self::CALLBACK_FORMATS[$state]['format'];
        $keys   = self::CALLBACK_FORMATS[$state]['keys'];

        $values = array_fill(0, count($keys), null);
        $result = sscanf($callback, $format, ...$values);

        if ($result === false || $result === 0) {
            return null;
        }

        $values = (array) $values;

        if (count($keys) !== count($values)) {
            return null;
        }

        return array_combine($keys, $values);
    }

    private function ensureStateExists(string $state): void
    {
        if (!isset(self::CALLBACK_FORMATS[$state])) {
            throw new InvalidArgumentException("Unknown state: {$state}");
        }
    }
}
