<?php

declare(strict_types=1);

namespace App\ApiDoc;

use Attribute;
use InvalidArgumentException;
use OpenApi\Annotations\Operation;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiEndpoint extends Operation
{
    /**
     * @param string $method
     * @param string $path
     * @param string $summary
     * @param string $description
     * @param bool $requiresAuth
     * @param array $responses
     * @param array $security
     * @param array $parameters
     * @param ?OA\RequestBody $requestBody
     */
    public function __construct(
        string $method,
        string $path,
        string $summary,
        string $description,
        bool $requiresAuth = true,
        array $responses = [],
        array $security = [],
        array $parameters = [],
        ?OA\RequestBody $requestBody = null,
    ) {
        if (!in_array(strtoupper($method), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new InvalidArgumentException("Invalid HTTP method: $method");
        }

        if ($requiresAuth) {
            $responses[] = new OA\Response(
                response: Response::HTTP_UNAUTHORIZED,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found'),
                    ]
                )
            );
            $security[] = ['Bearer' => []];
        }

        $this->method      = strtolower($method);
        $this->path        = $path;
        $this->summary     = $summary;
        $this->description = $description;
        $this->responses   = $responses;
        $this->security    = $security;
        $this->parameters  = $parameters;
        $this->requestBody = $requestBody;
    }
}
