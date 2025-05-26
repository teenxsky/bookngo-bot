<?php

declare(strict_types=1);

namespace App\Telegram;

use InvalidArgumentException;
use JsonException;
use Predis\Client as RedisClient;
use RuntimeException;

class SessionManager
{
    private const SESSION_FORMAT = 'tg:%d:session';
    private const DEFAULT_TTL    = 36000;

    private RedisClient $redis;
    private int $ttl;

    public function __construct(
        string $host,
        int $port,
        int $ttl = self::DEFAULT_TTL
    ) {
        $this->ttl   = $ttl;
        $this->redis = new RedisClient([
            'scheme' => 'tcp',
            'host'   => $host,
            'port'   => $port,
        ]);
    }

    /**
     * @param int $chatId
     * @param string $state
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function saveSession(int $chatId, string $state, array $data = []): void
    {
        $this->validateState($state);
        $data = $this->normalizeSessionData($data);

        try {
            $this->redis->setex(
                sprintf(self::SESSION_FORMAT, $chatId),
                $this->ttl,
                json_encode([
                    'state' => $state,
                    'data'  => $data,
                ], JSON_THROW_ON_ERROR)
            );
        } catch (JsonException $e) {
            throw new RuntimeException('Session data encoding failed', 0, $e);
        }
    }

    /**
     * @param int $chatId,
     * @return array{
     *     state: string,
     *     data: array{
     *         house_id?: string,
     *         city_id?: string,
     *         country_id?: string,
     *         start_date?: string,
     *         end_date?: string,
     *         phone_number?: string,
     *         comment?: string|null,
     *         booking_id?: int,
     *     }
     * }|null
     */
    public function getSession(int $chatId): ?array
    {
        $key  = sprintf(self::SESSION_FORMAT, $chatId);
        $data = $this->redis->get($key);

        if ($data === null) {
            return null;
        }

        try {
            $session = json_decode(
                json: $data,
                associative: true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException('Session data decoding failed', 0, $e);
        }

        $this->validateSessionStructure($session);
        $this->validateState($session['state']);

        return $session;
    }

    /**
     * @param int $chatId
     * @return void
     */
    public function deleteSession(int $chatId): void
    {
        $this->redis->del(
            sprintf(self::SESSION_FORMAT, $chatId)
        );
    }

    /**
     * @param array|null $data
     * @return array{
     *     booking_id: int|null,
     *     city_id: int|null,
     *     comment: string|null,
     *     country_id: int|null,
     *     start_date: string|null,
     *     end_date: string|null,
     *     house_id: int|null,
     *     is_actual: int|null,
     *     phone_number: string|null,
     * }
     */
    private function normalizeSessionData(?array $data): array
    {
        if ($data === null) {
            return [
                'booking_id'   => null,
                'city_id'      => null,
                'comment'      => null,
                'country_id'   => null,
                'start_date'   => null,
                'end_date'     => null,
                'house_id'     => null,
                'is_actual'    => null,
                'phone_number' => null,
            ];
        }

        return [
            'booking_id'   => isset($data['booking_id']) ? (int)$data['booking_id'] : null,
            'city_id'      => isset($data['city_id']) ? (int)$data['city_id'] : null,
            'comment'      => isset($data['comment']) ? (string)$data['comment'] : null,
            'country_id'   => isset($data['country_id']) ? (int)$data['country_id'] : null,
            'start_date'   => isset($data['start_date']) ? (string)$data['start_date'] : null,
            'end_date'     => isset($data['end_date']) ? (string)$data['end_date'] : null,
            'house_id'     => isset($data['house_id']) ? (int)$data['house_id'] : null,
            'is_actual'    => isset($data['is_actual']) ? (int)$data['is_actual'] : null,
            'phone_number' => isset($data['phone_number']) ? (string)$data['phone_number'] : null,
        ];
    }

    /**
     * @param array $session
     * @throws RuntimeException
     * @return void
     */
    private function validateSessionStructure(array $session): void
    {
        if (!isset($session['state'])) {
            throw new RuntimeException(
                'Invalid session structure: missing state'
            );
        }

        if (!isset($session['data']) || !is_array($session['data'])) {
            throw new RuntimeException(
                'Invalid session structure: missing data'
            );
        }
    }

    /**
     * @param string $state
     * @throws InvalidArgumentException
     * @return void
     */
    private function validateState(string $state): void
    {
        if (!array_key_exists($state, WorkflowStateManager::STATES)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid workflow state: %s. Allowed states: %s',
                $state,
                implode(', ', array_keys(WorkflowStateManager::STATES))
            ));
        }
    }
}
