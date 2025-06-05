<?php

declare(strict_types=1);

namespace App\ApiDoc;

use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response as OAResponse;

class ApiResponse extends OAResponse
{
    private const ERROR_CODES_START = 400;

    /**
     * @param int $responseCode
     * @param string $description
     * @param string|null $messageExample
     * @param mixed $content
     * @param Property[] $extraProperties
     */
    public function __construct(
        int $responseCode,
        string $description,
        mixed $content = null,
        ?string $messageExample = null,
        array $extraProperties = []
    ) {
        if ($content !== null) {
            parent::__construct(
                response: $responseCode,
                description: $description,
                content: $content
            );
            return;
        }

        $properties = [];

        if ($messageExample !== null) {
            $properties[] = new Property(
                property: 'message',
                type: 'string',
                example: $messageExample
            );
        } else {
            $properties[] = new Property(
                property: 'message',
                type: 'string'
            );
        }

        if ($responseCode >= self::ERROR_CODES_START) {
            $properties[] = new Property(
                property: 'errors',
                type: 'array',
                items: new Items(type: 'string')
            );
        }

        $properties = array_merge($properties, $extraProperties);

        parent::__construct(
            response: $responseCode,
            description: $description,
            content: new JsonContent(properties: $properties)
        );
    }
}
