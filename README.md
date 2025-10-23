# Cao BNC Bot

A comprehensive Laravel + React platform for tracking Binance transactions and managing trading bots.

## Features

- **User Authentication**: Complete login/register system with Laravel Breeze
- **Dashboard**: Overview of trading statistics and quick actions
- **Transaction Tracking**: Monitor your Binance trading history
- **Bot Management**: Configure and manage automated trading bots
- **Modern UI**: Built with React, Inertia.js, and Tailwind CSS

## Tech Stack

- **Backend**: Laravel 11
- **Frontend**: React 18 + Inertia.js
- **Styling**: Tailwind CSS
- **Database**: MySQL
- **Build Tool**: Vite

## Prerequisites

Before you begin, ensure you have the following installed:

- Docker and Docker Compose
- Git

## Quick Start with Docker (Recommended)

### 1. Clone the repository

```bash
git clone <repository-url>
cd cao-bnc-bot
```

### 2. Run the setup script

```bash
./docker-setup.sh
```

This script will:
- Create the `.env` file
- Build and start all Docker containers
- Install all dependencies
- Run database migrations
- Build frontend assets

The application will be available at `http://localhost:8000`

## Manual Docker Setup

If you prefer to run commands manually:

### 1. Clone and setup environment

```bash
git clone <repository-url>
cd cao-bnc-bot
cp env.example .env
```

### 2. Start containers

```bash
docker-compose up -d --build
```

### 3. Install dependencies and setup

```bash
# Install PHP dependencies
docker-compose exec app composer install

# Generate application key
docker-compose exec app php artisan key:generate

# Run database migrations
docker-compose exec app php artisan migrate

# Install Node dependencies
docker-compose exec node npm install

# Build frontend assets
docker-compose exec node npm run build
```

## Development

### Using Docker (Recommended)

The application runs in Docker containers with hot reloading:

- **Laravel**: Available at `http://localhost:8000`
- **Database**: MySQL on port `3306`
- **Redis**: Available on port `6379`

### Useful Docker Commands

```bash
# View logs
docker-compose logs -f

# Stop containers
docker-compose down

# Restart containers
docker-compose restart

# Access app container
docker-compose exec app bash

# Access node container
docker-compose exec node sh

# Run Laravel commands
docker-compose exec app php artisan [command]

# Run Node commands
docker-compose exec node npm [command]
```

### Local Development (Without Docker)

If you prefer to run locally without Docker:

1. Install PHP 8.2+, Composer, Node.js 18+, and MySQL
2. Follow the original installation steps in the README
3. Update `.env` to use `DB_HOST=127.0.0.1`

### Frontend Development

The frontend is built with React and Inertia.js. Key directories:

- `resources/js/Pages/` - React page components
- `resources/js/Layouts/` - Layout components
- `resources/js/Components/` - Reusable React components
- `resources/css/` - Tailwind CSS styles

### Backend Development

The backend follows Laravel conventions:

- `app/Http/Controllers/` - API and web controllers
- `app/Models/` - Eloquent models
- `database/migrations/` - Database migrations
- `routes/` - Application routes

## Project Structure

```
cao-bnc-bot/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/           # Authentication controllers
│   │   │   ├── BinanceController.php
│   │   │   ├── BotController.php
│   │   │   └── TransactionController.php
│   │   └── Middleware/
│   └── Models/
├── database/
│   └── migrations/
├── resources/
│   ├── js/
│   │   ├── Pages/              # React page components
│   │   ├── Layouts/            # Layout components
│   │   └── app.jsx            # Main React entry point
│   ├── css/
│   └── views/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── auth.php
└── public/
```

## Next Steps

This is a foundation project. To complete the Binance integration, you'll need to:

1. **Set up Binance API credentials** in the Settings section
2. **Implement Binance API integration** for:
   - Account information
   - Trading history
   - Real-time price data
3. **Create bot management system** for:
   - Strategy configuration
   - Bot monitoring
   - Performance analytics
4. **Add transaction tracking** with:
   - Historical data sync
   - Performance metrics
   - Export functionality

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

For support, please open an issue in the repository or contact the development team.
