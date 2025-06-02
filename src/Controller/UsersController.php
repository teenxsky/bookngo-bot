<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\UsersMessages;
use App\DTO\DTOFactory;
use App\DTO\UserDTO;
use App\Entity\User;
use App\Serializer\DTOSerializer;
use App\Service\UsersService;
use App\Validator\DTOValidator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/users', name: 'api_v1_users_')]
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
            $userDTO->phoneNumber,
            $userDTO->password
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
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        $userDTO = UserDTO::createFromEntity($user);

        return new JsonResponse(
            $userDTO->toArray(),
            Response::HTTP_OK,
        );
    }
}
