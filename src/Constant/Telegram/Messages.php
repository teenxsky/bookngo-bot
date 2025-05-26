<?php

declare(strict_types=1);

namespace App\Constant\Telegram;

/**
 * This class contains messages related to the Telegram bot.
 */
class Messages
{
    // Welcome & Menu
    public const WELCOME = <<<TXT
    🏠 *Welcome to Book&Go!* 🏠

    I will help you find and book the perfect rest for relaxation.
    Let's find your perfect rest home! 🌊
    TXT;

    public const MENU_OPTIONS = '📋 Please use the menu options below:';

    // User Input Prompts
    public const SELECT_COUNTRY = '🌍 Select a country where you\'d like to stay:';
    public const SELECT_CITY    = '🏙️ Select a city where you\'d like to stay:';
    public const SELECT_DATES   = <<<TXT
    📅 Please enter your booking dates in format:
    YYYY-MM-DD to YYYY-MM-DD

    Example: 2025-06-15 to 2025-06-20
    TXT;

    public const SELECT_BOOKING        = '🏡 Select the address of the house you booked:';
    public const SELECT_HOUSE          = '🏠 Select a house from the list above by entering its code (e.g. 123):';
    public const SELECT_PHONE_NUMBER   = '📞 Please share your phone number so we can contact you about your booking:';
    public const SELECT_COMMENT        = '💬 Write a message about your stay or special requests (send "-" if none):';
    public const SELECT_BOOKING_ACTION = '✏️ What would you like to change in your booking? You can update: phone number, comments, or cancel the booking entirely.';

    // Booking Info
    public const MY_BOOKINGS = <<<TXT
    📚 Your Bookings Management 📚
    
    Here you can view all your accommodation reservations:
    
    ✅ *Actual* - Shows your current and upcoming bookings
    🗄 *Archived* - Displays your completed past stays
    
    Tap the buttons below to view your bookings! 
    Wishing you a wonderful vacation! 🌴✨
    TXT;

    public const BOOKING_INFO_FORMAT = <<<TXT
    🏡 Your Booking Details 🏡
        
    🏠 *House code:* %d
    📍 Address: %s, %s, %s

    📞 Phone number: %s
    💬 Comment: %s
    📅 Dates: %s to %s
        
    ------------------------
    💰 Total price: %d$.
    TXT;

    public const BOOKING_SUMMARY_FORMAT = <<<TXT
    ✨ Booking Summary: ✨

    🏠 *House code:* %d
    📍 Address: %s, %s, %s

    📞 Phone number: %s
    💬 Comment: %s
    📅 Dates: %s to %s

    ------------------------
    💰 Total price: %d$

    (You can modify any details by pressing "Back" button)
    TXT;

    public const CONFIRM_BOOKING = <<<TXT
    ✨ Dear, *%s*!

    Thank you for choosing *Book&Go*! 🌟
    Your booking request has been received and is being processed.
    We will contact you shortly to confirm all the details.

    Have a wonderful day! 🌴
    TXT;

    public const BOOKING_DELETED = '✅ Your booking has been deleted successfully. Thank you!';

    // House Info
    public const HOUSE_INFO_FORMAT = <<<TXT
    🏠 *House code:* %d
    💰 Price per night: %d$
    📍 Address: %s, %s, %s

    Amenities:
    🛏 Bedrooms: %d
    🌊 Sea View: %s
    📶 Wi-Fi: %s
    🧑‍🍳 Kitchen: %s
    🚗 Parking: %s
    ❄️ Air Conditioning: %s
    TXT;

    // Errors & Validation
    public const ERROR_FORMAT = '❗ Error: %s';

    public const UNKNOWN_COMMAND       = '❗ Unknown command. Enter "/start" to begin.';
    public const INCORRECT_DATE_FORMAT = <<<TXT
    ❗ Incorrect date format. Please use:
    YYYY-MM-DD to YYYY-MM-DD
    
    Example: 2025-06-15 to 2025-06-20
    TXT;

    public const DATE_IN_PAST_ERROR         = '❗ Error: Date cannot be in the past.';
    public const START_DATE_AFTER_END_ERROR = '❗ Error: Start date cannot be after end date.';
    public const INVALID_HOUSE_CODE         = '❗ Error: Invalid House Code.';
    public const INCORRECT_PHONE_NUMBER     = '❗ Error: Invalid phone number format. Please enter a valid phone number.';

    public const HOUSES_NOT_FOUND_FORMAT = '😞 No available houses for the selected dates in %s.';
    public const BOOKINGS_NOT_FOUND      = <<<TXT
    Bookings not found. 😞
    This means that it's time to go on vacation! 🌴
    TXT;

    public const ERROR_REPORT_FORMAT = <<<TXT
    [ERROR REPORT]
    ----------------------------------------
    Time: %s
    User Information:
    - ID: %d 
    - Username: @%s

    Error Description:
    %s
    ----------------------------------------
    TXT;
}
