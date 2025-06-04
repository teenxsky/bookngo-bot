# Book&Go Telegram Bot

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-blue" alt="PHP Version">
  <img src="https://img.shields.io/badge/Symfony-6.x-black" alt="Symfony Version">
  <img src="https://img.shields.io/badge/Docker-Required-blue" alt="Docker Required">
  <img src="https://img.shields.io/badge/PostgreSQL-Latest-blue" alt="PostgreSQL">
  <img src="https://img.shields.io/badge/Redis-Latest-red" alt="Redis">
</p>

<p align="center">
  <img src="https://s.iimg.su/s/20/th_K7lqKeWZsjo0vezqHCG3ia4bJw8OJcdm6w5Wj9Eb.jpg" alt="Book&Go Bot">
</p>

## 🏠 About

Book&Go is a Telegram bot that simplifies the house booking process. Users can browse available properties, make reservations, and manage their bookings through a convenient Telegram interface.

## ✨ Features

### User Features

- Browse houses by location (countries and cities)
- Check real-time availability
- Make and manage bookings
- View booking history
- Receive booking confirmations
- Add and edit booking comments

### Admin Features

- **Admin Dashboard**: Comprehensive web-based administration interface
- **Houses Management**: Add, edit, and manage property listings with amenities
- **Location Management**: Manage countries and cities
- **Booking Management**: Oversee all reservations and bookings
- **User Management**: Administer user accounts and permissions
- **Multi-language Support**: Admin interface available in English and Russian
- **Custom Templates**: Enhanced UI with image previews and data visualization

### Technical Features

- Symfony 6.x framework
- Docker containerization
- PostgreSQL database
- Redis session management
- Telegram Bot API integration
- RESTful API architecture
- **Sonata Admin Bundle**: Professional admin interface
- **Internationalization**: Full i18n support with translation management

## 🛠 Tech Stack

### Backend

- PHP 8.1+
- Symfony 6.x
- Doctrine ORM
- PostgreSQL
- Redis
- Sonata Admin Bundle
- Sonata Translation Bundle

### Infrastructure

- Docker
- Nginx
- Xdebug for development

## 🚀 Quick Start

### Prerequisites

- Docker and Docker Compose
- Make utility
- Telegram Bot Token

### Installation

1. Set up environment variables:

```bash
cp .env .env.local
cp .env.dev .env.dev.local
cp .env.test .env.test.local
```

2. Generate JWT keypair:

```bash
make generate-jwt-keypair
```

3. Add Telegram Bot Token:

```bash
# .env.local
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
TELEGRAM_WEBHOOK_URL=<your_host>/api/v1/telegram/webhook
TELEGRAM_BOT_USERNAME=your_telegram_bot_username
TELEGRAM_ADMIN_CHAT_ID=telegram_admin_chat
```

4. Build and start containers:

```bash
make build
make up
```

5. Initialize database:

```bash
make migrate-db
```

6. Run command to set Telegram webhook:

```bash
make set-webhook
```

## 🔧 Development

### Running Tests

```bash
# Create test database
make create-test-db

# Run migrations for test database
make migrate-test-db

# Run all tests
make run-tests

# Run only repository tests
make run-tests-repository

# Run only controller tests
make run-tests-controller
```

### Debugging

```bash
# Enable Xdebug
make xdebug-enable

# Check Xdebug status
make xdebug-status

# Disable Xdebug
make xdebug-disable
```

### Database Management

```bash
# Create new migration
make make-migrations

# Apply migrations
make migrate-db

# Create database backup
make create-dump
```

### Docker Commands

```bash
# Start services with logs
make up-logs

# Stop services
make down

# Clean up containers and volumes
make clean

# Clean up only volumes
make clean-volumes

# Access backend shell
make shell-backend
```

## 🤖 Bot Commands

### Basic Commands

- `/start` - Display main menu

### Bot Workflow

1. Select country
2. Choose city
3. Select dates
4. Pick available house
5. Provide contact details
6. Add comments (optional)
7. Confirm booking

## 🔧 Admin Interface

### Access

The admin interface is available at `/admin` and provides comprehensive management tools.

### Admin Features

#### Houses Management
- Create and manage property listings
- Set pricing and availability
- Upload and manage property images
- Configure amenities (WiFi, AC, Kitchen, Parking, Sea View)
- Track booking statistics

#### Location Management
- Manage countries and cities
- Organize geographical hierarchy
- Control available booking locations

#### Booking Management
- View all reservations
- Track booking status and details
- Access customer information
- Generate booking reports

#### User Management
- Administer user accounts
- Manage user permissions
- View user booking history

### Internationalization
- **English**: Full admin interface in English
- **Russian**: Complete Russian translation
- **Extensible**: Easy to add more languages

## 🔒 Security

### Features

- Secure session management with Redis
- Input validation and sanitization
- Rate limiting for API endpoints
- Environment-based configuration

## 🧪 Testing

### Test Suites

- Service Tests

  - BookingsServiceTest
  - CitiesServiceTest
  - CountriesServiceTest
  - HousesServiceTest

- Controller Tests
  - BookingsControllerTest
  - HousesControllerTest

## 📝 License

This project is proprietary software. All rights reserved.

## 📮 Support

For support and inquiries, please create an issue in the repository.

---

© 2024 Book&Go. All rights reserved.
