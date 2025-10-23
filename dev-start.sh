#!/bin/bash

echo "🚀 Starting Cao BNC Bot in development mode..."

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp env.example .env
else
    echo "✅ .env file already exists"
fi

# Start containers with development configuration
echo "🐳 Starting Docker containers in development mode..."
docker-compose -f docker-compose.dev.yml up -d --build

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 30

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
docker-compose -f docker-compose.dev.yml exec app composer install

# Generate application key
echo "🔑 Generating application key..."
docker-compose -f docker-compose.dev.yml exec app php artisan key:generate

# Run database migrations
echo "🗄️ Running database migrations..."
docker-compose -f docker-compose.dev.yml exec app php artisan migrate

# Install Node dependencies
echo "📦 Installing Node dependencies..."
docker-compose -f docker-compose.dev.yml exec node npm install

# Wait a moment for npm install to complete
sleep 5

echo "✅ Development environment ready!"
echo ""
echo "🌐 Your application is now running at:"
echo "  - Laravel: http://localhost:8000"
echo "  - Vite Dev Server: http://localhost:5173"
echo ""
echo "📋 Useful commands:"
echo "  - View logs: docker-compose -f docker-compose.dev.yml logs -f"
echo "  - Stop containers: docker-compose -f docker-compose.dev.yml down"
echo "  - Restart containers: docker-compose -f docker-compose.dev.yml restart"
echo "  - Access app container: docker-compose -f docker-compose.dev.yml exec app bash"
echo "  - Access node container: docker-compose -f docker-compose.dev.yml exec node sh"
echo ""
echo "🎉 Happy coding with hot reloading!"
