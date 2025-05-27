<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\UsersMessages;
use App\Entity\User;
use App\Service\UsersService;
use App\Validator\EntityValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/users', name: 'api_v1_users_')]
class UsersController extends AbstractController
{
    public function __construct(
        private EntityValidator $entityValidator,
        private UsersService $usersService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
        $this->entityValidator = new EntityValidator($validator);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $user = $this->deserializeUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $error = $this->entityValidator->validate($user);
        if ($error) {
            return new JsonResponse(
                UsersMessages::validationFailed(
                    $error
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $error = $this->usersService->registerApiUser(
            $user->getPhoneNumber(),
            $user->getPassword()
        );
        if ($error) {
            return new JsonResponse(
                UsersMessages::registrationFailed(
                    [$error]
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
        $user = $this->deserializeUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $error = $this->entityValidator->validate($user);
        if ($error) {
            return new JsonResponse(
                UsersMessages::validationFailed(
                    $error
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $result = $this->usersService->loginApiUser(
            $user->getPhoneNumber(),
            $user->getPassword()
        );
        if ($result['error']) {
            return new JsonResponse(
                UsersMessages::loginFailed(
                    [$result['error']]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            UsersMessages::login(
                $result['tokens']['access_token'],
                $result['tokens']['refresh_token']
            ),
            Response::HTTP_CREATED
        );
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $data = json_decode(
            $request->getContent(),
            true
        );

        if (!isset($data['refresh_token'])) {
            return new JsonResponse(
                UsersMessages::logoutFailed(
                    ['Field "refresh_token" is required.']
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $error = $this->usersService->logout($data['refresh_token']);
        if ($error) {
            return new JsonResponse(
                UsersMessages::logoutFailed(
                    [$error]
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
        $data = json_decode(
            $request->getContent(),
            true
        );

        if (!isset($data['refresh_token'])) {
            return new JsonResponse(
                UsersMessages::logoutFailed(
                    ['Field "refresh_token" is required.']
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $result = $this->usersService->refresh(
            $data['refresh_token']
        );

        if ($result['error']) {
            return new JsonResponse(
                UsersMessages::refreshFailed(
                    [$result['error']]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            UsersMessages::refresh(
                $result['tokens']['access_token'],
                $result['tokens']['refresh_token']
            ),
            Response::HTTP_CREATED
        );
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(
            $user->toArray(),
            Response::HTTP_OK,
        );
    }

    private function deserializeUser(Request $request): User | JsonResponse
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return new JsonResponse(
                UsersMessages::deserializationFailed(
                    ['Unsupported content type']
                ),
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE
            );
        }

        try {
            $data = array_filter(
                json_decode(
                    $request->getContent(),
                    true
                ),
                fn ($value) => $value !== null
            );

            $user = $this->serializer->deserialize(
                json_encode($data),
                User::class,
                'json'
            );

            return $user;
        } catch (NotEncodableValueException | UnexpectedValueException $e) {
            return new JsonResponse(
                UsersMessages::deserializationFailed(
                    [$e->getMessage()]
                ),
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
