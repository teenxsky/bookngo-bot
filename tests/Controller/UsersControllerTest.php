<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Constant\UsersMessages;
use App\DTO\DTOFactory;
use App\Entity\User;
use App\Repository\UsersRepository;
use App\Service\UsersService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UsersControllerTest extends WebTestCase
{
    /** @var UsersRepository $usersRepository */
    private static UsersRepository $usersRepository;
    private static UsersService $usersService;

    private DTOFactory $dtoFactory;

    private static KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    // Test Credentials
    private const USER_PHONE_NUMBER  = '+1234567890';
    private const USER_PASSWORD      = 'user123';
    private const ADMIN_PHONE_NUMBER = '+9876543210';
    private const ADMIN_PASSWORD     = 'admin123';

    /** @var array{access_token: string, refresh_token: string} */
    private static array $userTokens;
    /** @var array{access_token: string, refresh_token: string} */
    private static array $adminTokens;

    // API Endpoints
    private const API_USERS_REGISTER = '/api/v1/users/register';
    private const API_USERS_LOGIN    = '/api/v1/users/login';
    private const API_USERS_LOGOUT   = '/api/v1/users/logout';
    private const API_USERS_REFRESH  = '/api/v1/users/refresh';
    private const API_USERS_ME       = '/api/v1/users/me';

    #[Override]
    public static function setUpBeforeClass(): void
    {
        self::initializeDatabase();
    }

    protected static function initializeDatabase(): void
    {
        // Initialize the client
        self::$client = static::createClient();
        self::assertSame(
            'test',
            self::$client->getKernel()->getEnvironment()
        );

        // Initialize the entity manager
        $entityManager = self::$client->getContainer()
            ->get('doctrine')
            ->getManager();

        // Clear all tables
        $connection = $entityManager->getConnection();
        $connection->executeStatement(
            'TRUNCATE TABLE refresh_tokens RESTART IDENTITY CASCADE'
        );
        $connection->executeStatement(
            'TRUNCATE TABLE users RESTART IDENTITY CASCADE'
        );

        // Initialize services
        self::$usersRepository = $entityManager->getRepository(User::class);

        self::$usersService = new UsersService(
            self::$usersRepository,
            static::getContainer()->get(
                UserPasswordHasherInterface::class
            ),
            static::getContainer()->get(
                JWTTokenManagerInterface::class
            ),
            static::getContainer()->get(
                RefreshTokenManagerInterface::class
            )
        );

        // Register test users
        self::$usersService->registerApiUser(
            self::USER_PHONE_NUMBER,
            self::USER_PASSWORD,
            false
        );
        self::$usersService->registerApiUser(
            self::ADMIN_PHONE_NUMBER,
            self::ADMIN_PASSWORD,
            true
        );
    }

    #[Override]
    public function setUp(): void
    {
        self::$client->getKernel()->boot();

        $this->entityManager = self::$client->getContainer()
            ->get('doctrine')
            ->getManager();
        self::$usersRepository = $this->entityManager->getRepository(
            User::class
        );

        if (!self::$usersService->validateCredentials(
            self::USER_PHONE_NUMBER,
            self::USER_PASSWORD
        )) {
            self::$userTokens = self::$usersService->loginApiUser(
                self::USER_PHONE_NUMBER
            );
        }

        if (!self::$usersService->validateCredentials(
            self::ADMIN_PHONE_NUMBER,
            self::ADMIN_PASSWORD
        )) {
            self::$adminTokens = self::$usersService->loginApiUser(
                self::ADMIN_PHONE_NUMBER,
            );
        }

        $this->dtoFactory = new DTOFactory();
    }

    private function assertResponse(
        Response $response,
        int $expectedStatusCode,
        ?array $expectedContent = null
    ): void {
        $this->assertEquals(
            $expectedStatusCode,
            $response->getStatusCode()
        );
        $this->assertJson($response->getContent());

        if ($expectedContent) {
            $this->assertEquals(
                json_encode($expectedContent),
                $response->getContent()
            );
        }
    }

    /*
     * Scenario: Registering a new user
     * Given valid user data
     * When I register a new user
     * Then the user should be created with status 201
     */
    public function testRegisterUserSuccess(): void
    {
        $newUserData = [
            'phone_number' => '+1112223333',
            'password'     => 'newuser123'
        ];

        self::$client->request(
            method: 'POST',
            uri: self::API_USERS_REGISTER,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($newUserData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_CREATED,
            UsersMessages::register()
        );

        $user = self::$usersRepository->findOneBy(
            ['phoneNumber' => '+1112223333']
        );
        $this->assertNotNull($user);
    }

    /*
     * Scenario: Registering with existing phone number
     * Given a phone number that already exists
     * When I try to register
     * Then I should receive an error with status 400
     */
    public function testRegisterUserAlreadyExists(): void
    {
        $existingUserData = [
            'phone_number' => self::USER_PHONE_NUMBER,
            'password'     => 'password123'
        ];

        self::$client->request(
            method: 'POST',
            uri: self::API_USERS_REGISTER,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($existingUserData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_BAD_REQUEST,
            UsersMessages::registrationFailed(
                [UsersMessages::ALREADY_EXISTS]
            )
        );
    }

    /*
     * Scenario: Logging in with valid credentials
     * Given valid user credentials
     * When I log in
     * Then I should receive access and refresh tokens with status 201
     */
    public function testLoginSuccess(): void
    {
        $loginData = [
            'phone_number' => self::USER_PHONE_NUMBER,
            'password'     => self::USER_PASSWORD
        ];

        self::$client->request(
            method: 'POST',
            uri: self::API_USERS_LOGIN,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($loginData)
        );

        $response = self::$client->getResponse();
        $this->assertResponse(
            $response,
            Response::HTTP_CREATED,
        );

        $responseData = json_decode(
            $response->getContent(),
            true
        );
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals(
            UsersMessages::LOGIN,
            $responseData['message']
        );

        $this->assertArrayHasKey('tokens', $responseData);
        $this->assertArrayHasKey(
            'access_token',
            $responseData['tokens']
        );
        $this->assertArrayHasKey(
            'refresh_token',
            $responseData['tokens']
        );
    }

    /*
     * Scenario: Logging in with invalid credentials
     * Given invalid user credentials
     * When I try to log in
     * Then I should receive an error with status 400
     */
    public function testLoginInvalidCredentials(): void
    {
        $invalidLoginData = [
            'phone_number' => self::USER_PHONE_NUMBER,
            'password'     => 'wrong_password'
        ];

        self::$client->request(
            method: 'POST',
            uri: self::API_USERS_LOGIN,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($invalidLoginData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_BAD_REQUEST,
            UsersMessages::loginFailed(
                [UsersMessages::INVALID_CREDENTIALS]
            )
        );
    }

    /*
     * Scenario: Logging in with missed credentials
     * Given missed user credentials
     * When I try to log in
     * Then I should receive an error with status 400
     */
    public function testLoginValidationError(): void
    {
        $invalidLoginData = [
            'phone_number' => self::USER_PHONE_NUMBER,
        ];

        self::$client->request(
            method: 'POST',
            uri: self::API_USERS_LOGIN,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($invalidLoginData)
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_BAD_REQUEST,
            UsersMessages::validationFailed(
                [[
                    'field'   => 'password',
                    'message' => 'This value should not be null.'
                ]]
            )
        );
    }

    /*
     * Scenario: Getting current user info
     * Given an authenticated user
     * When I request my info
     * Then I should receive my user details with status 200
     */
    public function testGetCurrentUserInfo(): void
    {
        self::$client->request(
            method: 'GET',
            uri: self::API_USERS_ME,
            server: [
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ]
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_OK,
            $this->dtoFactory->createFromEntity(
                self::$usersRepository->find(1)
            )->toArray(),
        );
    }

    /*
     * Scenario: Getting current user info with invalid token
     * Given an invalid authentication token
     * When I request my info
     * Then I should receive unauthorized error with status 401
     */
    public function testGetCurrentUserInfoUnauthorizedError(): void
    {
        self::$client->request(
            method: 'GET',
            uri: self::API_USERS_ME,
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_UNAUTHORIZED,
            [
              'code'    => 401,
              'message' => 'JWT Token not found'
            ]
        );
    }

    /*
     * Scenario: Refreshing tokens
     * Given a valid refresh token
     * When I refresh tokens
     * Then I should receive new access and refresh tokens with status 201
     */
    public function testRefreshTokens(): void
    {
        $refreshData = [
            'refresh_token' => self::$userTokens['refresh_token']
        ];

        self::$client->request(
            method: 'POST',
            uri: self::API_USERS_REFRESH,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($refreshData)
        );

        $response = self::$client->getResponse();
        $this->assertResponse(
            $response,
            Response::HTTP_CREATED,
        );

        $responseData = json_decode(
            $response->getContent(),
            true
        );
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals(
            UsersMessages::REFRESH,
            $responseData['message']
        );

        $this->assertArrayHasKey('tokens', $responseData);
        $this->assertArrayHasKey(
            'access_token',
            $responseData['tokens']
        );
        $this->assertArrayHasKey(
            'refresh_token',
            $responseData['tokens']
        );
    }

    /*
     * Scenario: Logging out
     * Given an authenticated user
     * When I log out
     * Then I should receive success message with status 200
     */
    public function testLogout(): void
    {
        $logoutData = [
            'refresh_token' => self::$userTokens['refresh_token']
        ];

        self::$client->request(
            method: 'POST',
            uri: self::API_USERS_LOGOUT,
            server: [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ],
            content: json_encode($logoutData)
        );
        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_OK,
            UsersMessages::logout()
        );

        self::$client->request(
            method: 'GET',
            uri: self::API_USERS_ME,
            server: [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$userTokens['access_token']
                )
            ],
            content: json_encode($logoutData)
        );
        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_UNAUTHORIZED,
            [
                'code'    => 401,
                'message' => 'JWT Token not found'
            ]
        );
    }

    /*
     * Scenario: Admin accessing user info
     * Given an admin user
     * When admin requests user info
     * Then admin should receive user details with status 200
     */
    public function testAdminCanAccessUserInfo(): void
    {
        self::$client->request(
            method: 'GET',
            uri: self::API_USERS_ME,
            server: [
                'HTTP_Authorization' => sprintf(
                    'Bearer %s',
                    self::$adminTokens['access_token']
                )
            ]
        );

        $this->assertResponse(
            self::$client->getResponse(),
            Response::HTTP_OK,
            $this->dtoFactory->createFromEntity(
                self::$usersRepository->find(2)
            )->toArray(),
        );
    }
}
