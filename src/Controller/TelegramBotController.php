<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\Telegram\Buttons as TelegramButtons;
use App\Constant\Telegram\Messages as TelegramMessages;
use App\Entity\Booking;
use App\Service\BookingsService;
use App\Service\CitiesService;
use App\Service\CountriesService;
use App\Service\HousesService;
use App\Telegram\SessionManager;
use App\Telegram\WorkflowStateManager;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Update;

#[Route('/api/v1/telegram')]
class TelegramBotController extends AbstractController
{
    private BotApi $telegram;
    private SessionManager $sessionsManager;

    public function __construct(
        private HousesService $housesService,
        private BookingsService $bookingsService,
        private CitiesService $citiesService,
        private CountriesService $countriesService,
        private SerializerInterface $serializer,
        private WorkflowStateManager $stateManager,
        private LoggerInterface $logger
    ) {
        $this->sessionsManager = new SessionManager(
            $_ENV['REDIS_HOST'],
            (int) $_ENV['REDIS_PORT'],
            (int) $_ENV['REDIS_TTL']
        );
        $this->telegram = new BotApi($_ENV['TELEGRAM_BOT_TOKEN']);
    }

    #[Route('/webhook', name: 'telegram_webhook', methods: ['POST'])]
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
                $update->getMessage()?->getFrom()?->getId()       ?? -1,
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

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
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
            case WorkflowStateManager::PHONE_NUMBER:
                $this->handlePhoneNumber($chatId, $message->getText());
                break;
            case WorkflowStateManager::EDIT_PHONE_NUMBER:
                $this->handlePhoneNumber($chatId, $message->getText());
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
            WorkflowStateManager::EDIT_PHONE_NUMBER
        )) {
            $this->requestPhoneNumber(
                $chatId,
                $this->stateManager::EDIT_PHONE_NUMBER
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
            WorkflowStateManager::PHONE_NUMBER
        )) {
            $this->requestPhoneNumber(
                $chatId,
                WorkflowStateManager::PHONE_NUMBER
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
            ],
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

        $bookings = $this->bookingsService->findBookingsByCriteria(['telegramUserId' => $userId], $isActual);

        $buttons = [];
        foreach ($bookings as $booking) {
            $buttons[] = [
                TelegramButtons::bookingAddress(
                    "{$booking->getHouse()->getCity()->getName()}, {$booking->getHouse()->getAddress()}",
                    $this->stateManager->buildCallback(
                        $this->stateManager::BOOKING_INFO,
                        [],
                        $booking->getId()
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

        $booking    = $this->bookingsService->findBookingById($bookingId);
        $totalPrice = $this->bookingsService->calculateTotalPrice(
            $booking->getHouse(),
            $booking->getStartDate(),
            $booking->getEndDate(),
        );

        $message = sprintf(
            TelegramMessages::BOOKING_SUMMARY_FORMAT,
            $booking->getHouse()->getId(),
            $booking->getHouse()->getCity()->getCountry()->getName(),
            $booking->getHouse()->getCity()->getName(),
            $booking->getHouse()->getAddress(),
            $booking->getPhoneNumber(),
            $booking->getComment() ?? 'None',
            $booking->getStartDate()->format('Y-m-d'),
            $booking->getEndDate()->format('Y-m-d'),
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
                TelegramButtons::editPhoneNumber(
                    $this->stateManager->buildCallback(
                        $this->stateManager::EDIT_PHONE_NUMBER,
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

        $result = $this->bookingsService->deleteBooking(
            $session['data']['booking_id']
        );
        if ($result['error'] !== null) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $result['error']),
                $messageId,
                new InlineKeyboardMarkup($buttons)
            );
            return;
        }

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

        $buttons = [];
        foreach ($countries as $country) {
            $buttons[] = [
                TelegramButtons::country(
                    $country->getName(),
                    $this->stateManager->buildCallback(
                        $this->stateManager::getNext(
                            $state
                        ),
                        [],
                        $country->getId()
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

        $buttons = [];
        foreach ($cities as $city) {
            $buttons[] = [
                TelegramButtons::city(
                    $city->getName(),
                    $this->stateManager->buildCallback(
                        $this->stateManager::getNext($state),
                        [],
                        $city->getId()
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

        $error = $this->bookingsService->validateBookingDates(
            $startDate,
            $endDate
        );
        if ($error !== null) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $error),
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

        foreach ($houses as $house) {
            $message = sprintf(
                TelegramMessages::HOUSE_INFO_FORMAT,
                $house->getId(),
                $house->getPricePerNight(),
                $house->getCity()->getCountry()->getName(),
                $house->getCity()->getName(),
                $house->getAddress(),
                $house->getBedroomsCount(),
                $house->hasSeaView() ? 'Yes' : 'No',
                $house->hasWifi() ? 'Yes' : 'No',
                $house->hasKitchen() ? 'Yes' : 'No',
                $house->hasParking() ? 'Yes' : 'No',
                $house->hasAirConditioning() ? 'Yes' : 'No',
            );
            $this->sendMessage(
                $chatId,
                $message,
                null,
                null,
                $house->getImageUrl()
            );
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
            empty($houses) ?
                sprintf(
                    TelegramMessages::HOUSES_NOT_FOUND_FORMAT,
                    $this->citiesService->findCityById($cityId)['city']->getName()
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

        $result = $this->housesService->findHouseById((int) $houseId);
        if ($result['error'] !== null) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $result['error']),
            );
            return;
        }

        $session = $this->sessionsManager->getSession($chatId);
        $error   = $this->housesService->validateHouseCity(
            $result['house'],
            (int)$session['data']['city_id']
        );
        if ($error !== null) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $error),
            );
            return;
        }

        $error = $this->bookingsService->validateHouseAvailability(
            $result['house'],
            new DateTimeImmutable($session['data']['start_date']),
            new DateTimeImmutable($session['data']['end_date']),
        );
        if ($error !== null) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $error),
            );
            return;
        }

        $this->sessionsManager->saveSession(
            $chatId,
            $session['state'],
            ['house_id' => (int)$houseId] + ($session['data'] ?? [])
        );
        $this->requestPhoneNumber(
            $chatId,
            $this->stateManager->getNext(
                $session['state'],
            ),
        );
    }

    private function requestPhoneNumber(int $chatId, string $state): void
    {
        $session = $this->sessionsManager->getSession($chatId);

        $buttons[] = [
            TelegramButtons::back(
                $this->stateManager->buildCallback(
                    $this->stateManager::getPrev($state),
                    $session['data']
                )
            ),
            TelegramButtons::mainMenu(
                $this->stateManager->buildCallback(
                    $this->stateManager::MAIN_MENU
                )
            ),
        ];

        $this->sendMessage(
            $chatId,
            TelegramMessages::SELECT_PHONE_NUMBER,
            null,
            new InlineKeyboardMarkup($buttons),
        );
        $this->sessionsManager->saveSession(
            $chatId,
            $state,
            $session['data'] ?? []
        );
    }

    private function handlePhoneNumber(int $chatId, string $phoneNumber): void
    {
        $session = $this->sessionsManager->getSession($chatId);
        if (!preg_match('/^\+?[0-9]{1,3}?[0-9]{7,14}$/', $phoneNumber)) {
            $this->sendMessage(
                $chatId,
                TelegramMessages::INCORRECT_PHONE_NUMBER,
            );
            return;
        }

        if ($session['state'] === $this->stateManager::PHONE_NUMBER) {
            $this->sessionsManager->saveSession(
                $chatId,
                $session['state'],
                ['phone_number' => $phoneNumber] + ($session['data'] ?? [])
            );

            $this->requestComment(
                $chatId,
                $this->stateManager->getNext($session['state']),
            );
        } else {
            $updatedBooking = (new Booking())
                ->setPhoneNumber($phoneNumber);
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

    private function requestComment(int $chatId, string $state): void
    {
        $session = $this->sessionsManager->getSession($chatId);

        $buttons[] = [
            TelegramButtons::back(
                $this->stateManager->buildCallback(
                    $this->stateManager::getPrev($state),
                    $session['data']
                )
            ),
            TelegramButtons::mainMenu(
                $this->stateManager->buildCallback(
                    $this->stateManager::MAIN_MENU
                )
            ),
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

        $comment = $comment === '-' ? 'None' : $comment;
        if ($session['state'] === $this->stateManager::COMMENT) {
            $this->sessionsManager->saveSession(
                $chatId,
                $session['state'],
                ['comment' => $comment] + ($session['data'] ?? [])
            );

            $this->showBookingSummary($chatId);
        } else {
            $updatedBooking = (new Booking())
                ->setComment($comment);
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

        $buttons[] = [
            TelegramButtons::confirm(
                $this->stateManager->buildCallback(
                    $this->stateManager::getNext($state),
                    $session['data']
                ),
            )
        ];

        $buttons[] = [
            TelegramButtons::back(
                $this->stateManager->buildCallback(
                    $this->stateManager::getPrev($state),
                    $session['data']
                )
            ),
            TelegramButtons::mainMenu(
                $this->stateManager->buildCallback(
                    $this->stateManager::MAIN_MENU
                )
            ),
        ];

        $startDate = new DateTimeImmutable($session['data']['start_date']);
        $endDate   = new DateTimeImmutable($session['data']['end_date']);

        $totalPrice = $this->bookingsService->calculateTotalPrice(
            $this->housesService->findHouseById(
                (int)$session['data']['house_id']
            )['house'],
            $startDate,
            $endDate
        );

        $result = $this->housesService->findHouseById(
            (int) $session['data']['house_id']
        );
        if ($result['error'] !== null) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $result['error']),
                null,
                new InlineKeyboardMarkup($buttons),
            );
            return;
        }
        $house = $result['house'];

        $message = sprintf(
            TelegramMessages::BOOKING_SUMMARY_FORMAT,
            $session['data']['house_id'],
            $house->getCity()->getCountry()->getName(),
            $house->getCity()->getName(),
            $house->getAddress(),
            $session['data']['phone_number'],
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

        $error = $this->bookingsService->createBooking(
            (int) $session['data']['house_id'],
            $session['data']['phone_number'],
            $session['data']['comment'],
            new DateTimeImmutable($session['data']['start_date']),
            new DateTimeImmutable($session['data']['end_date']),
            $chatId,
            $userId,
            $username
        );

        if ($error !== null) {
            $this->sendMessage(
                $chatId,
                sprintf(TelegramMessages::ERROR_FORMAT, $error),
                $messageId,
            );
            return;
        }

        $buttons[] = [
            TelegramButtons::mainMenu(
                $this->stateManager->buildCallback(
                    $this->stateManager::MAIN_MENU
                )
            ),
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
