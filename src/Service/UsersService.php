<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\UsersMessages;
use App\Entity\User;
use App\Repository\UsersRepository;
use DateTime;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UsersService
{
    public function __construct(
        private UsersRepository $usersRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenManagerInterface $refreshTokenManager,
    ) {
    }

    /**
     * @param string $phoneNumber
     * @return User|null
     */
    public function findUserByPhoneNumber(string $phoneNumber): ?User
    {
        return $this->usersRepository->findOneBy(
            ['phoneNumber' => $phoneNumber]
        );
    }

    /**
     * @param string $username
     * @return User|null
     */
    public function findUserByTelegramUsername(string $username): ?User
    {
        return $this->usersRepository->findOneBy(
            ['telegramUsername' => $username]
        );
    }

    /**
     * @param string $phoneNumber
     * @param string $password
     * @param bool $isAdmin
     * @return string|null
     */
    public function registerApiUser(
        string $phoneNumber,
        string $password,
        bool $isAdmin = false
    ): ?string {
        if ($this->findUserByPhoneNumber($phoneNumber)) {
            return UsersMessages::ALREADY_EXISTS;
        }

        $user = (new User())
            ->setPhoneNumber($phoneNumber);

        if ($isAdmin) {
            $user->setRoles(array_merge(
                $user->getRoles(),
                ['ROLE_ADMIN']
            ));
        }

        $user->setPassword(
            $this->passwordHasher->hashPassword(
                $user,
                $password
            )
        );

        $this->usersRepository->addUser($user);
        return null;
    }

    /**
     * @param int $telegramChatId
     * @param int $telegramUserId
     * @param string $telegramUsername
     * @return void
     */
    public function registerTelegramUser(
        int $telegramChatId,
        int $telegramUserId,
        string $telegramUsername,
    ): void {
        $user = $this->findUserByTelegramUsername($telegramUsername);
        if ($user) {
            return;
        }

        $user = (new User())
            ->setTelegramChatId($telegramChatId)
            ->setTelegramUserId($telegramUserId)
            ->setTelegramUsername($telegramUsername);

        $this->usersRepository->addUser($user);
    }

    /**
     * @param string $phoneNumber
     * @param string $password
     * @return array{tokens:array{access_token:string,refresh_token:string}|null,error:string}
     */
    public function loginApiUser(
        string $phoneNumber,
        string $password
    ): array {
        $existingUser = $this->findUserByPhoneNumber($phoneNumber);
        if (!$existingUser) {
            return [
                'tokens' => null,
                'error'  => UsersMessages::NOT_FOUND
            ];
        }

        if (!$this->passwordHasher->isPasswordValid(
            $existingUser,
            $password,
        )) {
            return [
                'tokens' => null,
                'error'  => UsersMessages::INVALID_CREDENTIALS
            ];
        }

        return [
            'tokens' => $this->generateTokens($existingUser),
            'error'  => null
        ];
    }

    public function logout(string $accessToken): ?string
    {
        $refreshToken = $this->refreshTokenManager->get($accessToken);
        if (!$refreshToken) {
            return UsersMessages::INVALID_REFRESH;
        }

        $user = $this->findUserByPhoneNumber(
            $refreshToken->getUsername()
        );
        if (!$user) {
            return UsersMessages::NOT_FOUND;
        }

        $user->incrementTokenVersion();
        $this->usersRepository->updateUser($user);
        $this->refreshTokenManager->delete($refreshToken);

        return null;
    }

    /**
     * @param string $refreshToken
     * @return array{tokens:array{access_token:string,refresh_token:string}|null,error:string}
     */
    public function refresh(string $refreshToken): array
    {
        $refreshTokenEntity = $this->refreshTokenManager->get(
            $refreshToken
        );

        if (!$refreshTokenEntity || !$refreshTokenEntity->isValid()) {
            return [
                'tokens' => null,
                'error'  => UsersMessages::INVALID_REFRESH
            ];
        }

        $user = $this->findUserByPhoneNumber(
            $refreshTokenEntity->getUsername()
        );
        if (!$user) {
            return [
                'tokens' => null,
                'error'  => UsersMessages::NOT_FOUND
            ];
        }

        $this->refreshTokenManager->delete($refreshTokenEntity);

        return [
            'tokens' => $this->generateTokens($user),
            'error'  => null
        ];
    }

    /**
     * @param User $user
     * @return array{access_token: string, refresh_token: string|null}
     */
    private function generateTokens(User $user): array
    {
        $user->incrementTokenVersion();
        $this->usersRepository->updateUser($user);

        $accessToken = $this->jwtManager->createFromPayload(
            $user,
            ['version' => $user->getTokenVersion()]
        );

        $refreshToken = (new RefreshToken())
            ->setUsername($user->getUserIdentifier())
            ->setRefreshToken(
                bin2hex(random_bytes(32))
            )
            ->setValid((new DateTime())->modify('+1 month'));

        $this->refreshTokenManager->save($refreshToken);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken->getRefreshToken(),
        ];
    }

    /**
     * @param int $tokenVersion
     * @param string $phoneNumber
     * @return bool
     */
    public function isValidToken(int $tokenVersion, string $phoneNumber): bool
    {
        $user = $this->usersRepository->findOneBy(
            ['phoneNumber' => $phoneNumber]
        );

        if (!$user) {
            return false;
        }

        if ($tokenVersion !== $user->getTokenVersion()) {
            return false;
        }

        return true;
    }

    /**
     * @param array $criteria
     * @return User|null
     */
    public function findUserByCriteria(array $criteria): ?User
    {
        return $this->usersRepository->findOneBy($criteria);
    }
}
