<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\Telegram\Buttons as TelegramButtons;
use App\Constant\Telegram\Messages as TelegramMessages;
use App\DTO\BookingDTO;
use App\DTO\CityDTO;
use App\DTO\CountryDTO;
use App\DTO\DTOFactory;
use App\DTO\HouseDTO;
use App\Entity\Booking;
use App\Service\BookingsService;
use App\Service\CitiesService;
use App\Service\CountriesService;
use App\Service\HousesService;
use App\Service\UsersService;
use App\Telegram\SessionManager;
use App\Telegram\WorkflowStateManager;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Update;

#[Route('/api/v1/telegram', name: 'api_v1_telegram_')]
class TelegramBotController extends AbstractController
{
    private BotApi $telegram;
    private SessionManager $sessionsManager;

    public function __construct(
        private HousesService $housesService,
        private BookingsService $bookingsService,
        private CitiesService $citiesService,
        private CountriesService $countriesService,
        private UsersService $usersService,
        private WorkflowStateManager $stateManager,
        private LoggerInterface $logger,
        private DTOFactory $dtoFactory
    ) {
        $this->sessionsManager = new SessionManager(
            $_ENV['REDIS_HOST'],
            (int) $_ENV['REDIS_PORT'],
            (int) $_ENV['REDIS_TTL']
        );
        $this->telegram = new BotApi($_ENV['TELEGRAM_BOT_TOKEN']);
    }

    #[Route('/webhook', name: 'webhook', methods: ['POST'])]
    public function webhook(): Response
    {
        try {
            $update = Update::fromResponse(
                json_decode(
                    file_get_contents('php://input'),
                    true
                )
            );

            if ($update->getMessage()) {
                $this->handleMessage($update);
            } elseif ($update->getCallbackQuery()) {
                $this->handleCallback($update);
            }
        } catch (Exception $e) {
            $message = sprintf(
                TelegramMessages::ERROR_REPORT_FORMAT,
                (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                $update->getMessage()?->getFrom()?->getId         ?? -1,
                $update->getMessage()?->getFrom()?->getUsername() ?? '',
                $e->getMessage()
            );

            if ($_ENV['TELEGRAM_ADMIN_CHAT_ID']) {
                $this->sendMessage(
                    (int) $_ENV['TELEGRAM_ADMIN_CHAT_ID'],
                    $message,
                );
            }
            $this->logger->critical($message);
        }

        return new JsonResponse(status: Response::HTTP_OK);
    }

    private function handleMessage(Update $update): void
    {
        $message = $update->getMessage();
        $chatId  = (int) $message->getChat()->getId();
        $session = $this->sessionsManager->getSession($chatId);

        if ($message->getText() === WorkflowStateManager::START) {
            $this->showMainMenu($chatId);
            return;
        }

        if (!$session) {
            $this->sendMessage($chatId, TelegramMessages::UNKNOWN_COMMAND);
            $this->showMainMenu($chatId);
            return;
        }

        switch ($session['state']) {
            case WorkflowStateManager::DATES:
                $this->handleDatesInput($chatId, $message->getText());
                break;
            case WorkflowStateManager::HOUSES_LIST:
                $this->handleHouseIdInput($chatId, $message->getText());
                break;
            case WorkflowStateManager::COMMENT:
                $this->handleComment($chatId, $message->getText());
                break;
            case WorkflowStateManager::EDIT_COMMENT:
                $this->handleComment($chatId, $message->getText());
                break;
            default:
                $this->sendMessage($chatId, TelegramMessages::UNKNOWN_COMMAND);
                $this->showMainMenu($chatId);
        }
    }

    private function handleCallback(Update $update): void
    {
        $callback      = $update->getCallbackQuery();
        $callbackQuery = $callback->getData();
        $chatId        = (int) $callback->getMessage()->getChat()->getId();
        $messageId     = $callback->getMessage()->getMessageId();
        $username      = $callback->getFrom()->getUsername();
        $userId        = $callback->getFrom()->getId();

        if (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::MAIN_MENU
        )) {
            $this->showMainMenu($chatId, $messageId);

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::BOOKINGS_MENU
        )) {
            $this->showBookingsMenu($chatId, $messageId);

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::BOOKINGS_LIST
        )) {
            $params = $this->stateManager->extractCallbackData(
                $this->stateManager::BOOKINGS_LIST,
                $callbackQuery
            );
            $this->showBookings(
                $chatId,
                $userId,
                (bool) $params['is_actual'],
                $messageId
            );

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::BOOKING_INFO
        )) {
            $params = $this->stateManager->extractCallbackData(
                $this->stateManager::BOOKING_INFO,
                $callbackQuery
            );
            $this->showBookingInfo(
                $chatId,
                $params['booking_id'],
            );

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::EDIT_COMMENT
        )) {
            $this->requestComment(
                $chatId,
                $this->stateManager::EDIT_COMMENT
            );

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::DELETE_BOOKING
        )) {
            $this->showDeleteBooking($chatId);

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::NEW_BOOKING
        )) {
            $this->showCountries($chatId, $messageId);

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::CITIES
        )) {
            $params = $this->stateManager->extractCallbackData(
                $this->stateManager::CITIES,
                $callbackQuery
            );
            $this->showCities(
                $chatId,
                $params['country_id'],
                $messageId,
            );

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::DATES
        )) {
            $params = $this->stateManager->extractCallbackData(
                $this->stateManager::DATES,
                $callbackQuery
            );
            $this->requestDates(
                $chatId,
                $params['city_id'],
                $messageId,
            );

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::HOUSES_LIST
        )) {
            $params = $this->stateManager->extractCallbackData(
                $this->stateManager::HOUSES_LIST,
                $callbackQuery
            );
            $this->showHouses(
                $chatId,
                $params['city_id'],
                new DateTimeImmutable((string) $params['start_date']),
                new DateTimeImmutable((string) $params['end_date']),
            );

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::COMMENT
        )) {
            $this->requestComment(
                $chatId,
                WorkflowStateManager::COMMENT
            );

        } elseif (str_starts_with(
            $callbackQuery,
            WorkflowStateManager::BOOKING_CONFIRM
        )) {
            $this->confirmBooking(
                $chatId,
                $messageId,
                $userId,
                $username
            );

        }

        $this->telegram->answerCallbackQuery($callback->getId());
    }

    private function showMainMenu(int $chatId, ?int $messageId = null): void
    {
        $state = WorkflowStateManager::MAIN_MENU;
        $this->sessionsManager->deleteSession($chatId);

        $buttons = [
            [
                TelegramButtons::newBooking(
                    $this->stateManager->buildCallback(
                        $this->stateManager::NEW_BOOKING,
                    )
                ),
                TelegramButtons::myBookings(
                    $this->stateManager->buildCallback(
                        $this->stateManager::BOOKINGS_MENU,
                    )
                )
            ]
        ];
        $this->sendMessage(
            $chatId,
            TelegramMessages::WELCOME,
            $messageId,
            new InlineKeyboardMarkup($buttons),
        );

        $this->sessionsManager->saveSession(
            $chatId,
            $state
        );
    }

    private function showBookingsMenu(int $chatId, ?int $messageId = null): void
    {
        $state = $this->stateManager::BOOKINGS_MENU;

        $buttons = [
            [
                TelegramButtons::actualBookings(
                    $this->stateManager->buildCallback(
                        $this->stateManager::BOOKINGS_LIST,
                        [],
                        (int) true
                    ),
                ),
                TelegramButtons::archivedBookings(
                    $this->stateManager->buildCallback(
                        $this->stateManager::BOOKINGS_LIST,
                        [],
                        (int) false
                    ),
                ),
            ],
            [
                TelegramButtons::mainMenu(
                    $this->stateManager->buildCallback(
                        $this->stateManager::MAIN_MENU
                    )
                ),
            ]
        ];

        $this->sendMessage(
            $chatId,
            TelegramMessages::MY_BOOKINGS,
            $messageId,
            new InlineKeyboardMarkup($buttons),
        );

        $this->sessionsManager->saveSession(
            $chatId,
            $state
        );
    }

    private function showBookings(
        int $chatId,
        int $userId,
        bool $isActual = true,
        ?int $messageId = null,
    ): void {
        $state   = $this->stateManager::BOOKINGS_LIST;
        $session = $this->sessionsManager->getSession($chatId);

        $user     = $this->usersService->findUserByCriteria(['telegramUserId' => $userId]);
        $bookings = $this->bookingsService->findBookingsByUserId(
            userId: $user->getId(),
            isActual: $isActual
        );

        /** @var BookingDTO[] $bookingDTOs */
        $bookingDTOs = $this->dtoFactory->createFromEntities($bookings);

        $buttons = [];
        foreach ($bookingDTOs as $bookingDTO) {
            $booking   = $this->bookingsService->findBookingById($bookingDTO->id);
            $buttons[] = [
                TelegramButtons::bookingAddress(
                    "{$booking->getHouse()->getCity()->getName()}, {$booking->getHouse()->getAddress()}",
                    $this->stateManager->buildCallback(
                        $this->stateManager::BOOKING_INFO,
                        [],
                        $bookingDTO->id
                    )
                )
            ];
        }

        $buttons[] = [
            TelegramButtons::back(
                $this->stateManager->buildCallback(
                    $this->stateManager::getPrev($state),
                    $session['data']
                )
            ),
            TelegramButtons::mainMenu(
                $this->stateManager->buildCallback(
                    $this->stateManager::MAIN_MENU,
                )
            ),
        ];
        $this->sendMessage(
            $chatId,
            $bookings === [] ?
                TelegramMessages::BOOKINGS_NOT_FOUND :
                TelegramMessages::SELECT_BOOKING,
            $messageId,
            new InlineKeyboardMarkup($buttons),
        );

        $this->sessionsManager->saveSession(
            $chatId,
            $state,
            ['is_actual' => (int) $isActual],
        );
    }

    private function showBookingInfo(
        int $chatId,
        int $bookingId,
    ): void {
        $state   = $this->stateManager::BOOKING_INFO;
        $session = $this->sessionsManager->getSession($chatId);

        $booking = $this->bookingsService->findBookingById($bookingId);

        /** @var BookingDTO $bookingDTO */
        $bookingDTO = $this->dtoFactory->createFromEntity($booking);

        $totalPrice = $this->bookingsService->calculateTotalPrice(
            $booking->getHouse(),
            $booking->getStartDate(),
            $booking->getEndDate(),
        );

        $message = sprintf(
            TelegramMessages::BOOKING_INFO_FORMAT,
            $booking->getHouse()->getId(),
            $booking->getHouse()->getCity()->getCountry()->getName(),
            $booking->getHouse()->getCity()->getName(),
            $booking->getHouse()->getAddress(),
            $bookingDTO->comment ?? 'None',
            $bookingDTO->startDate->format('Y-m-d'),
            $bookingDTO->endDate->format('Y-m-d'),
            $totalPrice
        );

        $buttons = [
            [
                TelegramButtons::editComment(
                    $this->stateManager->buildCallback(
                        $this->stateManager::EDIT_COMMENT,
                    ),
                ),
            ],
            [
                TelegramButtons::deleteBooking(
                    $this->stateManager->buildCallback(
                        $this->stateManager::DELETE_BOOKING,
                    ),
                ),
            ],
            [
                TelegramButtons::back(
                    $this->stateManager->buildCallback(
                        $this->stateManager::getPrev($state),
                        $session['data']
                    )
                ),
                TelegramButtons::mainMenu(
                    $this->stateManager->buildCallback(
                        $this->stateManager::MAIN_MENU,
                    )
                ),
            ],
        ];

        $this->sendMessage(
            $chatId,
            $message,
            null,
            null,
            $booking->getHouse()->getImageUrl()
        );
        $this->sendMessage(
            $chatId,
            TelegramMessages::SELECT_BOOKING_ACTION,
            null,
            new InlineKeyboardMarkup($buttons)
        );
        $this->sessionsManager->saveSession(
            $chatId,
            $state,
            ['booking_id' => $bookingId] + ($session['data'] ?? []),
        );
    }

    private function showDeleteBooking(int $chatId, ?int $messageId = null): void
    {
        $state   = $this->stateManager::BOOKING_INFO;
        $session = $this->sessionsManager->getSession($chatId);

        $buttons = [
            [
                TelegramButtons::back(
                    $this->stateManager->buildCallback(
                        $this->stateManager::getPrev($state),
                        $session['data']
                    )
                ),
                TelegramButtons::mainMenu(
                    $this->stateManager->buildCallback(
                        $this->stateManager::MAIN_MENU,
                    )
                ),
            ],
        ];

        $validationError = $this->bookingsService->validateBookingDeletion(
            $session['data']['booking_id']
        );
        if ($validationError) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $validationError),
                $messageId,
                new InlineKeyboardMarkup($buttons)
            );
            return;
        }

        $this->bookingsService->deleteBooking(
            $session['data']['booking_id']
        );

        $this->sendMessage(
            $chatId,
            TelegramMessages::BOOKING_DELETED,
            $messageId,
            new InlineKeyboardMarkup($buttons)
        );
    }

    private function showCountries(int $chatId, ?int $messageId = null): void
    {
        $state     = $this->stateManager::NEW_BOOKING;
        $session   = $this->sessionsManager->getSession($chatId);
        $countries = $this->countriesService->findAllCountries();

        /** @var CountryDTO[] $countryDTOs */
        $countryDTOs = $this->dtoFactory->createFromEntities($countries);

        $buttons = [];
        foreach ($countryDTOs as $countryDTO) {
            $buttons[] = [
                TelegramButtons::country(
                    $countryDTO->name,
                    $this->stateManager->buildCallback(
                        $this->stateManager::getNext(
                            $state
                        ),
                        [],
                        $countryDTO->id
                    )
                )
            ];
        }
        $buttons[] = [
            TelegramButtons::mainMenu(
                $this->stateManager->buildCallback(
                    $this->stateManager::getPrev($state),
                    $session['data']
                )
            )
        ];

        $this->sendMessage(
            $chatId,
            TelegramMessages::SELECT_COUNTRY,
            $messageId,
            new InlineKeyboardMarkup($buttons),
        );

        $this->sessionsManager->saveSession(
            $chatId,
            $state
        );
    }

    private function showCities(
        int $chatId,
        int $countryId,
        ?int $messageId = null
    ): void {
        $state   = $this->stateManager::CITIES;
        $session = $this->sessionsManager->getSession($chatId);

        $cities = $this->citiesService->findCitiesByCountryId($countryId);

        /** @var CityDTO[] $cityDTOs */
        $cityDTOs = $this->dtoFactory->createFromEntities($cities);

        $buttons = [];
        foreach ($cityDTOs as $cityDTO) {
            $buttons[] = [
                TelegramButtons::city(
                    $cityDTO->name,
                    $this->stateManager->buildCallback(
                        $this->stateManager::getNext($state),
                        [],
                        $cityDTO->id
                    )
                )
            ];
        }
        $buttons[] = [
            TelegramButtons::back(
                $this->stateManager->buildCallback(
                    $this->stateManager::getPrev($state),
                    $session['data']
                )
            ),
            TelegramButtons::mainMenu(
                $this->stateManager->buildCallback(
                    $this->stateManager::MAIN_MENU,
                )
            ),
        ];

        $this->sendMessage(
            $chatId,
            TelegramMessages::SELECT_CITY,
            $messageId,
            new InlineKeyboardMarkup($buttons),
        );

        $this->sessionsManager->saveSession(
            $chatId,
            $state,
            ['country_id' => $countryId] + ($session['data'] ?? [])
        );
    }

    private function requestDates(
        int $chatId,
        int $cityId,
        ?int $messageId = null
    ): void {
        $state   = $this->stateManager::DATES;
        $session = $this->sessionsManager->getSession($chatId);

        $buttons = [
            [
                TelegramButtons::back(
                    $this->stateManager->buildCallback(
                        $this->stateManager::getPrev($state),
                        $session['data']
                    )
                ),
                TelegramButtons::mainMenu(
                    $this->stateManager->buildCallback(
                        $this->stateManager::MAIN_MENU,
                    )
                ),
            ],
        ];

        $this->sendMessage(
            $chatId,
            TelegramMessages::SELECT_DATES,
            $messageId,
            new InlineKeyboardMarkup($buttons),
        );

        $this->sessionsManager->saveSession(
            $chatId,
            $state,
            ['city_id' => $cityId] + $session['data']
        );
    }

    private function handleDatesInput(int $chatId, string $dates): void
    {
        $session = $this->sessionsManager->getSession($chatId);

        if (!preg_match('/^\d{4}-\d{2}-\d{2} to \d{4}-\d{2}-\d{2}$/', $dates)) {
            $this->sendMessage(
                $chatId,
                TelegramMessages::INCORRECT_DATE_FORMAT,
            );
            return;
        }

        [$startDate, $endDate] = array_map(
            'trim',
            explode('to', $dates)
        );

        $startDate = new DateTimeImmutable($startDate);
        $endDate   = new DateTimeImmutable($endDate);

        $validationError = $this->bookingsService->validateBookingDates(
            $startDate,
            $endDate
        );
        if ($validationError) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $validationError),
            );
            return;
        }

        $this->sessionsManager->saveSession(
            $chatId,
            $session['state'],
            [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d')
            ] + ($session['data'] ?? [])
        );

        $cityId = $session['data']['city_id'];
        $this->showHouses(
            $chatId,
            (int) $cityId,
            $startDate,
            $endDate,
        );
    }

    private function showHouses(
        int $chatId,
        int $cityId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
    ): void {
        $state   = $this->stateManager::HOUSES_LIST;
        $session = $this->sessionsManager->getSession($chatId);

        $houses = $this->housesService->findAvailableHouses(
            $cityId,
            $startDate,
            $endDate
        );

        /** @var HouseDTO[] $houseDTOs */
        $houseDTOs = $this->dtoFactory->createFromEntities($houses);

        foreach ($houseDTOs as $houseDTO) {
            $house = $this->housesService->findHouseById($houseDTO->id);

            $message = sprintf(
                TelegramMessages::HOUSE_INFO_FORMAT,
                $houseDTO->id,
                $houseDTO->pricePerNight,
                $house->getCity()->getCountry()->getName(),
                $house->getCity()->getName(),
                $houseDTO->address,
                $houseDTO->bedroomsCount,
                $houseDTO->hasSeaView ? 'Yes' : 'No',
                $houseDTO->hasWifi ? 'Yes' : 'No',
                $houseDTO->hasKitchen ? 'Yes' : 'No',
                $houseDTO->hasParking ? 'Yes' : 'No',
                $houseDTO->hasAirConditioning ? 'Yes' : 'No',
            );
            $this->sendMessage(
                $chatId,
                $message,
                null,
                null,
                $houseDTO->imageUrl
            );
        }

        $buttons = [
            [
                TelegramButtons::back(
                    $this->stateManager->buildCallback(
                        $this->stateManager::getPrev($state),
                        $session['data']
                    )
                ),
                TelegramButtons::mainMenu(
                    $this->stateManager->buildCallback(
                        $this->stateManager::MAIN_MENU,
                    )
                ),
            ],
        ];

        $this->sendMessage(
            $chatId,
            empty($houses) ?
                sprintf(
                    TelegramMessages::HOUSES_NOT_FOUND_FORMAT,
                    $this->citiesService->findCityById($cityId)->getName()
                ) :
                TelegramMessages::SELECT_HOUSE,
            null,
            new InlineKeyboardMarkup($buttons),
        );
        $this->sessionsManager->saveSession(
            $chatId,
            $state,
            $session['data'] ?? []
        );
    }

    private function handleHouseIdInput(int $chatId, string $houseId): void
    {
        if (!is_numeric($houseId)) {
            $this->sendMessage(
                $chatId,
                TelegramMessages::INVALID_HOUSE_CODE,
            );
            return;
        }

        $houseValidation = $this->housesService->validateHouseExists((int) $houseId);
        if ($houseValidation) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $houseValidation),
            );
            return;
        }
        $house = $this->housesService->findHouseById((int) $houseId);

        $session        = $this->sessionsManager->getSession($chatId);
        $cityValidation = $this->housesService->validateHouseCity(
            $house,
            (int)$session['data']['city_id']
        );
        if ($cityValidation) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $cityValidation),
            );
            return;
        }

        $availabilityError = $this->bookingsService->validateHouseAvailability(
            $house,
            new DateTimeImmutable($session['data']['start_date']),
            new DateTimeImmutable($session['data']['end_date']),
        );
        if ($availabilityError) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $availabilityError),
            );
            return;
        }

        $this->sessionsManager->saveSession(
            $chatId,
            $session['state'],
            ['house_id' => (int)$houseId] + ($session['data'] ?? [])
        );
        $this->requestComment(
            $chatId,
            $this->stateManager->getNext($session['state']),
        );
    }

    private function requestComment(int $chatId, string $state): void
    {
        $session = $this->sessionsManager->getSession($chatId);

        $buttons = [
            [
                TelegramButtons::back(
                    $this->stateManager->buildCallback(
                        $this->stateManager::getPrev($state),
                        $session['data']
                    )
                ),
                TelegramButtons::mainMenu(
                    $this->stateManager->buildCallback(
                        $this->stateManager::MAIN_MENU,
                    )
                ),
            ],
        ];

        $this->sendMessage(
            $chatId,
            TelegramMessages::SELECT_COMMENT,
            null,
            new InlineKeyboardMarkup($buttons),
        );
        $this->sessionsManager->saveSession(
            $chatId,
            $state,
            $session['data'] ?? []
        );
    }

    private function handleComment(int $chatId, string $comment): void
    {
        $session = $this->sessionsManager->getSession($chatId);

        $comment = $comment === '-' ? null : $comment;
        if ($session['state'] === $this->stateManager::COMMENT) {
            $this->sessionsManager->saveSession(
                $chatId,
                $session['state'],
                ['comment' => $comment] + ($session['data'] ?? [])
            );

            $this->showBookingSummary($chatId);
        } else {
            $bookingDTO          = new BookingDTO();
            $bookingDTO->comment = $comment;

            $updatedBooking = new Booking();
            $this->dtoFactory->mapToEntity($bookingDTO, $updatedBooking);

            $this->bookingsService->updateBooking(
                $updatedBooking,
                $session['data']['booking_id']
            );

            $this->showBookingInfo(
                $chatId,
                $session['data']['booking_id']
            );
        }
    }

    private function showBookingSummary(int $chatId): void
    {
        $state   = $this->stateManager::BOOKING_SUMMARY;
        $session = $this->sessionsManager->getSession($chatId);

        $buttons = [
            [
                TelegramButtons::confirm(
                    $this->stateManager->buildCallback(
                        $this->stateManager::getNext($state),
                        $session['data']
                    ),
                )
            ],
            [
                TelegramButtons::back(
                    $this->stateManager->buildCallback(
                        $this->stateManager::getPrev($state),
                        $session['data']
                    )
                ),
                TelegramButtons::mainMenu(
                    $this->stateManager->buildCallback(
                        $this->stateManager::MAIN_MENU,
                    )
                ),
            ]
        ];

        $startDate = new DateTimeImmutable($session['data']['start_date']);
        $endDate   = new DateTimeImmutable($session['data']['end_date']);

        $totalPrice = $this->bookingsService->calculateTotalPrice(
            $this->housesService->findHouseById(
                (int)$session['data']['house_id']
            ),
            $startDate,
            $endDate
        );

        $house = $this->housesService->findHouseById(
            (int) $session['data']['house_id']
        );

        $message = sprintf(
            TelegramMessages::BOOKING_SUMMARY_FORMAT,
            $session['data']['house_id'],
            $house->getCity()->getCountry()->getName(),
            $house->getCity()->getName(),
            $house->getAddress(),
            $session['data']['comment'] ?? 'None',
            $session['data']['start_date'],
            $session['data']['end_date'],
            $totalPrice
        );

        $this->sendMessage(
            $chatId,
            $message,
            null,
            new InlineKeyboardMarkup($buttons),
        );
        $this->sessionsManager->saveSession(
            $chatId,
            $state,
            $session['data'] ?? []
        );
    }

    private function confirmBooking(
        int $chatId,
        int $messageId,
        int $userId,
        string $username
    ): void {
        $session = $this->sessionsManager->getSession($chatId);

        $bookingError = $this->bookingsService->createBooking(
            (int) $session['data']['house_id'],
            null,
            $session['data']['comment'],
            new DateTimeImmutable($session['data']['start_date']),
            new DateTimeImmutable($session['data']['end_date']),
            $chatId,
            $userId,
            $username,
            true
        );

        if ($bookingError) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $bookingError),
                $messageId,
            );
            return;
        }

        $buttons = [
            [
                TelegramButtons::mainMenu(
                    $this->stateManager->buildCallback(
                        $this->stateManager::MAIN_MENU
                    )
                ),
            ]
        ];

        $this->sendMessage(
            $chatId,
            sprintf(TelegramMessages::CONFIRM_BOOKING, $username),
            $messageId,
            new InlineKeyboardMarkup($buttons),
        );

        $this->sessionsManager->deleteSession($chatId);
    }

    private function sendMessage(
        int $chatId,
        string $text,
        ?int $messageId = null,
        ?InlineKeyboardMarkup $keyboard = null,
        ?string $imageUrl = null,
    ): void {
        if ($messageId) {
            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                $text,
                'Markdown',
                false,
                $keyboard
            );
        } else {
            if ($imageUrl) {
                $this->telegram->sendPhoto(
                    $chatId,
                    $imageUrl,
                    $text,
                    null,
                    null,
                    false,
                    'Markdown'
                );
            } else {
                $this->telegram->sendMessage(
                    $chatId,
                    $text,
                    'Markdown',
                    false,
                    null,
                    $keyboard
                );
            }
        }
    }
}
