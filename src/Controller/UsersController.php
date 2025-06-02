<?php

declare(strict_types=1);

namespace App\Controller;

use App\ApiDoc\ApiEndpoint;
use App\ApiDoc\ApiResponse;
use App\Constant\UsersMessages;
use App\DTO\DTOFactory;
use App\DTO\UserDTO;
use App\Entity\User;
use App\Serializer\DTOSerializer;
use App\Service\UsersService;
use App\Validator\DTOValidator;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/users', name: 'api_v1_users_')]
#[OA\Tag(name: 'Users')]
class UsersController extends AbstractController
{
    public function __construct(
        private UsersService $usersService,
        private DTOSerializer $dtoSerializer,
        private DTOValidator $dtoValidator,
        private DTOFactory $dtoFactory
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[ApiEndpoint(
        method: 'POST',
        requiresAuth: false,
        path: '/api/v1/users/register',
        summary: 'Registration of a new user',
        description: 'Creates a new user with the specified phone number and password',
        requestBody: new OA\RequestBody(
            description: 'User data',
            required: true,
            content: new Model(type: UserDTO::class, groups: ['write'])
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_CREATED,
                description: 'The user is successfully registered',
                messageExample: UsersMessages::REGISTER,
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'Validation or Deserialization or Registration error',
            ),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        try {
            /** @var UserDTO $userDTO */
            $userDTO = $this->dtoSerializer->deserialize(
                $request,
                UserDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                UsersMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationErrors = $this->dtoValidator->validate($userDTO);
        if ($validationErrors) {
            return new JsonResponse(
                UsersMessages::validationFailed($validationErrors),
                Response::HTTP_BAD_REQUEST
            );
        }

        $registrationError = $this->usersService->registerApiUser(
            phoneNumber: $userDTO->phoneNumber,
            password: $userDTO->password,
            isAdmin: false
        );
        if ($registrationError) {
            return new JsonResponse(
                UsersMessages::registrationFailed(
                    [$registrationError]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            UsersMessages::register(),
            Response::HTTP_CREATED
        );
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    #[ApiEndpoint(
        method: 'POST',
        requiresAuth: false,
        path: '/api/v1/users/login',
        summary: 'User authorization',
        description: 'Authorizes user and returns JWT tokens',
        requestBody: new OA\RequestBody(
            description: 'User credentials',
            required: true,
            content: new Model(type: UserDTO::class, groups: ['write'])
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_CREATED,
                description: 'Successful authorization',
                messageExample: UsersMessages::LOGIN,
                extraProperties: [new OA\Property(
                    property: 'tokens',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'refresh_token', type: 'string')
                    ]
                )]
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'Validation or Deserialization or Registration error',
            ),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        try {
            /** @var UserDTO $userDTO */
            $userDTO = $this->dtoSerializer->deserialize(
                $request,
                UserDTO::class,
            );
        } catch (Exception $e) {
            return new JsonResponse(
                UsersMessages::deserializationFailed([$e->getMessage()]),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationErrors = $this->dtoValidator->validate($userDTO);
        if ($validationErrors) {
            return new JsonResponse(
                UsersMessages::validationFailed($validationErrors),
                Response::HTTP_BAD_REQUEST
            );
        }

        $authError = $this->usersService->validateCredentials(
            $userDTO->phoneNumber,
            $userDTO->password
        );
        if ($authError) {
            return new JsonResponse(
                UsersMessages::loginFailed(
                    [$authError]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $tokens = $this->usersService->loginApiUser(
            $userDTO->phoneNumber
        );
        return new JsonResponse(
            UsersMessages::login(
                $tokens['access_token'],
                $tokens['refresh_token']
            ),
            Response::HTTP_CREATED
        );
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[ApiEndpoint(
        method: 'POST',
        requiresAuth: false,
        path: '/api/v1/users/logout',
        summary: 'User logout',
        description: 'User logout and token invalidation',
        requestBody: new OA\RequestBody(
            description: 'Refresh token',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string')
                ]
            )
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'Successful logout',
                messageExample: UsersMessages::LOGOUT,
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'Logout or Deserialization error',
            ),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        try {
            $refreshToken = $this->dtoSerializer->getRefreshTokenFromRequest($request);
        } catch (Exception $e) {
            return new JsonResponse(
                UsersMessages::deserializationFailed(
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $logoutError = $this->usersService->logout($refreshToken);
        if ($logoutError) {
            return new JsonResponse(
                UsersMessages::logoutFailed(
                    [$logoutError]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            UsersMessages::logout(),
            Response::HTTP_OK
        );
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    #[ApiEndpoint(
        method: 'POST',
        requiresAuth: false,
        path: '/api/v1/users/refresh',
        summary: 'Token Refresh',
        description: 'Refresh JWT token using refresh token',
        requestBody: new OA\RequestBody(
            description: 'Refresh token',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string')
                ]
            )
        ),
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_CREATED,
                description: 'Token successfully refreshed',
                messageExample: UsersMessages::LOGOUT,
                extraProperties: [new OA\Property(
                    property: 'tokens',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'refresh_token', type: 'string')
                    ]
                )]
            ),
            new ApiResponse(
                responseCode: Response::HTTP_BAD_REQUEST,
                description: 'Refresh or Validation or Deserialization error',
            ),
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        try {
            $refreshToken = $this->dtoSerializer->getRefreshTokenFromRequest($request);
        } catch (Exception $e) {
            return new JsonResponse(
                UsersMessages::deserializationFailed(
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $validationError = $this->usersService->validateRefreshToken(
            $refreshToken
        );
        if ($validationError) {
            return new JsonResponse(
                UsersMessages::refreshFailed(
                    [$validationError]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $tokens = $this->usersService->refresh(
            $refreshToken
        );
        return new JsonResponse(
            UsersMessages::refresh(
                $tokens['access_token'],
                $tokens['refresh_token']
            ),
            Response::HTTP_CREATED
        );
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[ApiEndpoint(
        method: 'GET',
        requiresAuth: true,
        path: '/api/v1/users/me',
        summary: 'Current user information',
        description: 'Get information about the current authenticated user',
        responses: [
            new ApiResponse(
                responseCode: Response::HTTP_OK,
                description: 'User information',
                content: new Model(type: UserDTO::class, groups: ['read'])
            ),
        ]
    )]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        $userDTO = UserDTO::createFromEntity($user);

        return new JsonResponse(
            $userDTO->toArray(),
            Response::HTTP_OK,
        );
    }
}
