#!/bin/bash

echo "🔧 Fixing Node dependencies..."

# Stop any running containers
echo "🛑 Stopping containers..."
docker-compose down

# Remove node_modules and package-lock.json if they exist
echo "🧹 Cleaning up existing node_modules..."
rm -rf node_modules package-lock.json

# Start containers
echo "🐳 Starting containers..."
docker-compose up -d

# Wait for containers to be ready
echo "⏳ Waiting for containers to be ready..."
sleep 30

# Install Node dependencies
echo "📦 Installing Node dependencies..."
docker-compose exec node npm install

# Wait for installation to complete
echo "⏳ Waiting for npm install to complete..."
sleep 10

# Build frontend assets
echo "🎨 Building frontend assets..."
docker-compose exec node npm run build

echo "✅ Node dependencies fixed!"
echo "🌐 Your application should now be running at: http://localhost:8000"


