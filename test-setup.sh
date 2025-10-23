#!/bin/bash

echo "🧪 Testing Docker setup..."

# Stop any running containers
echo "🛑 Stopping any running containers..."
docker-compose down

# Remove node_modules to start fresh
echo "🧹 Cleaning up node_modules..."
rm -rf node_modules package-lock.json

# Start containers
echo "🐳 Starting containers..."
docker-compose up -d

# Wait for containers to be ready
echo "⏳ Waiting for containers to be ready..."
sleep 30

# Test if containers are running
echo "🔍 Checking container status..."
docker-compose ps

# Install Node dependencies
echo "📦 Installing Node dependencies..."
docker-compose exec node npm install

# Check if installation was successful
if [ $? -eq 0 ]; then
    echo "✅ Node dependencies installed successfully!"
    
    # Try to build
    echo "🎨 Building frontend assets..."
    docker-compose exec node npm run build
    
    if [ $? -eq 0 ]; then
        echo "✅ Build successful!"
        echo "🌐 Your application should be running at: http://localhost:8000"
    else
        echo "❌ Build failed. Check the logs above."
    fi
else
    echo "❌ Node dependencies installation failed. Check the logs above."
fi


