#!/bin/bash

echo "🚀 Setting up Cao BNC Bot with Docker..."

# Copy environment file
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp env.example .env
else
    echo "✅ .env file already exists"
fi

# Build and start containers
echo "🐳 Building and starting Docker containers..."
docker-compose up -d --build

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 30

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
docker-compose exec app composer install

# Generate application key
echo "🔑 Generating application key..."
docker-compose exec app php artisan key:generate

# Run database migrations
echo "🗄️ Running database migrations..."
docker-compose exec app php artisan migrate

# Install Node dependencies
echo "📦 Installing Node dependencies..."
docker-compose exec node npm install

# Wait a moment for npm install to complete
sleep 5

# Build frontend assets
echo "🎨 Building frontend assets..."
docker-compose exec node npm run build

echo "✅ Setup complete!"
echo ""
echo "🌐 Your application is now running at: http://localhost:8000"
echo ""
echo "📋 Useful commands:"
echo "  - View logs: docker-compose logs -f"
echo "  - Stop containers: docker-compose down"
echo "  - Restart containers: docker-compose restart"
echo "  - Access app container: docker-compose exec app bash"
echo "  - Access node container: docker-compose exec node sh"
echo ""
echo "🎉 Happy coding!"
