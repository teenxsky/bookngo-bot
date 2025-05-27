<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Constant\UsersMessages;
use App\Entity\User;
use App\Repository\UsersRepository;
use App\Service\UsersService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UsersServiceTest extends WebTestCase
{
    private UsersService $usersService;
    /** @var UsersRepository $usersRepository */
    private UsersRepository $usersRepository;

    private UserPasswordHasherInterface $passwordHasher;
    private JWTTokenManagerInterface $jwtManager;
    private RefreshTokenManagerInterface $refreshTokenManager;
    private EntityManagerInterface $entityManager;

    private const TEST_PHONE_NUMBER   = '+1234567890';
    private const TEST_PASSWORD       = 'test123';
    private const TEST_ADMIN_PHONE    = '+9876543210';
    private const TEST_ADMIN_PASSWORD = 'admin123';

    #[Override]
    public function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->assertSame('test', $kernel->getEnvironment());

        $this->entityManager = static::getContainer()
            ->get('doctrine')
            ->getManager();
        $this->usersRepository = $this->entityManager->getRepository(
            User::class
        );
        $this->passwordHasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );
        $this->jwtManager = static::getContainer()->get(
            JWTTokenManagerInterface::class
        );
        $this->refreshTokenManager = static::getContainer()->get(
            RefreshTokenManagerInterface::class
        );

        $this->usersService = new UsersService(
            $this->usersRepository,
            $this->passwordHasher,
            $this->jwtManager,
            $this->refreshTokenManager
        );

        $this->truncateTables();
    }

    private function truncateTables(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement(
            'TRUNCATE TABLE users RESTART IDENTITY CASCADE'
        );
        $connection->executeStatement(
            'TRUNCATE TABLE refresh_tokens RESTART IDENTITY CASCADE'
        );
    }

    public function testFindUserByPhoneNumber(): void
    {
        $testUser = new User();
        $testUser->setPhoneNumber(self::TEST_PHONE_NUMBER);
        $testUser->setPassword($this->passwordHasher->hashPassword(
            $testUser,
            self::TEST_PASSWORD
        ));

        $this->usersRepository->addUser($testUser);

        $foundUser = $this->usersService->findUserByPhoneNumber(
            self::TEST_PHONE_NUMBER
        );
        $this->assertNotNull($foundUser);
        $this->assertEquals(
            self::TEST_PHONE_NUMBER,
            $foundUser->getPhoneNumber()
        );
    }

    public function testRegisterApiUserSuccess(): void
    {
        $result = $this->usersService->registerApiUser(
            self::TEST_PHONE_NUMBER,
            self::TEST_PASSWORD
        );

        $this->assertNull($result);

        $user = $this->usersRepository->findOneBy(
            ['phoneNumber' => self::TEST_PHONE_NUMBER]
        );
        $this->assertNotNull($user);
        $this->assertEquals(
            self::TEST_PHONE_NUMBER,
            $user->getPhoneNumber()
        );
    }

    public function testRegisterApiUserAlreadyExists(): void
    {
        $this->usersService->registerApiUser(
            self::TEST_PHONE_NUMBER,
            self::TEST_PASSWORD
        );

        $result = $this->usersService->registerApiUser(
            self::TEST_PHONE_NUMBER,
            self::TEST_PASSWORD
        );

        $this->assertEquals(
            UsersMessages::ALREADY_EXISTS,
            $result
        );
    }

    public function testRegisterApiUserAsAdmin(): void
    {
        $result = $this->usersService->registerApiUser(
            self::TEST_ADMIN_PHONE,
            self::TEST_ADMIN_PASSWORD,
            true
        );

        $this->assertNull($result);

        $admin = $this->usersRepository->findOneBy(
            ['phoneNumber' => self::TEST_ADMIN_PHONE]
        );
        $this->assertNotNull($admin);
        $this->assertTrue(
            in_array(
                'ROLE_ADMIN',
                $admin->getRoles(),
                true
            )
        );
    }

    public function testLoginApiUserSuccess(): void
    {
        $this->usersService->registerApiUser(self::TEST_PHONE_NUMBER, self::TEST_PASSWORD);

        $result = $this->usersService->loginApiUser(
            self::TEST_PHONE_NUMBER,
            self::TEST_PASSWORD
        );

        $this->assertNull(
            $result['error']
        );
        $this->assertArrayHasKey(
            'access_token',
            $result['tokens']
        );
        $this->assertArrayHasKey(
            'refresh_token',
            $result['tokens']
        );
    }

    public function testLoginApiUserNotFound(): void
    {
        $result = $this->usersService->loginApiUser(
            self::TEST_PHONE_NUMBER,
            self::TEST_PASSWORD
        );

        $this->assertEquals(
            UsersMessages::NOT_FOUND,
            $result['error']
        );
        $this->assertNull($result['tokens']);
    }

    public function testLoginApiUserInvalidCredentials(): void
    {
        $this->usersService->registerApiUser(
            self::TEST_PHONE_NUMBER,
            self::TEST_PASSWORD
        );

        $result = $this->usersService->loginApiUser(
            self::TEST_PHONE_NUMBER,
            'wrongPassword'
        );

        $this->assertEquals(
            UsersMessages::INVALID_CREDENTIALS,
            $result['error']
        );
        $this->assertNull($result['tokens']);
    }

    public function testLogoutSuccess(): void
    {
        $this->usersService->registerApiUser(
            self::TEST_PHONE_NUMBER,
            self::TEST_PASSWORD
        );
        $loginResult = $this->usersService->loginApiUser(
            self::TEST_PHONE_NUMBER,
            self::TEST_PASSWORD
        );

        $result = $this->usersService->logout(
            $loginResult['tokens']['refresh_token']
        );
        $this->assertNull($result);

        $refreshToken = $this->refreshTokenManager->get(
            $loginResult['tokens']['refresh_token']
        );
        $this->assertNull($refreshToken);
    }

    public function testLogoutInvalidToken(): void
    {
        $result = $this->usersService->logout('invalid_token');
        $this->assertEquals(
            UsersMessages::INVALID_REFRESH,
            $result
        );
    }

    public function testRefreshTokensSuccess(): void
    {
        $this->usersService->registerApiUser(
            self::TEST_PHONE_NUMBER,
            self::TEST_PASSWORD
        );
        $loginResult = $this->usersService->loginApiUser(
            self::TEST_PHONE_NUMBER,
            self::TEST_PASSWORD
        );

        $result = $this->usersService->refresh(
            $loginResult['tokens']['refresh_token']
        );

        $this->assertNull($result['error']);
        $this->assertArrayHasKey(
            'access_token',
            $result['tokens']
        );
        $this->assertArrayHasKey(
            'refresh_token',
            $result['tokens']
        );
    }

    public function testRefreshTokensInvalidToken(): void
    {
        $result = $this->usersService->refresh('invalid_token');
        $this->assertEquals(
            UsersMessages::INVALID_REFRESH,
            $result['error']
        );
        $this->assertNull($result['tokens']);
    }

    public function testGenerateTokens(): void
    {
        $user = new User();
        $user->setPhoneNumber(self::TEST_PHONE_NUMBER);
        $user->setPassword(
            $this->passwordHasher->hashPassword(
                $user,
                self::TEST_PASSWORD
            )
        );

        $this->usersRepository->addUser($user);

        $result = $this->usersService->generateTokens($user);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertEquals(1, $user->getTokenVersion());
    }

    public function testIsValidToken(): void
    {
        $user = new User();
        $user->setPhoneNumber(self::TEST_PHONE_NUMBER);
        $user->setPassword(
            $this->passwordHasher->hashPassword(
                $user,
                self::TEST_PASSWORD
            )
        );
        $user->setTokenVersion(2);

        $this->usersRepository->addUser($user);

        $this->assertTrue(
            $this->usersService->isValidToken(
                2,
                self::TEST_PHONE_NUMBER
            )
        );

        $this->assertFalse(
            $this->usersService->isValidToken(
                1,
                self::TEST_PHONE_NUMBER
            )
        );

        $this->assertFalse(
            $this->usersService->isValidToken(
                1,
                'nonexistent'
            )
        );
    }
}
