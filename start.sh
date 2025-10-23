#!/bin/bash

echo "🚀 Starting Cao BNC Bot..."

# Check if containers are already running
if docker-compose ps | grep -q "Up"; then
    echo "✅ Containers are already running!"
    echo "🌐 Your application is available at: http://localhost:8000"
    echo ""
    echo "📋 Useful commands:"
    echo "  - View logs: docker-compose logs -f"
    echo "  - Stop containers: docker-compose down"
    echo "  - Restart containers: docker-compose restart"
    echo "  - Access app container: docker-compose exec app bash"
    echo "  - Access node container: docker-compose exec node sh"
    exit 0
fi

# Start containers
echo "🐳 Starting Docker containers..."
docker-compose up -d

# Wait for containers to be ready
echo "⏳ Waiting for containers to be ready..."
sleep 10

# Check if everything is working
echo "🔍 Checking application status..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8000 | grep -q "200"; then
    echo "✅ Application is running successfully!"
    echo "🌐 Your application is available at: http://localhost:8000"
    echo ""
    echo "📋 Useful commands:"
    echo "  - View logs: docker-compose logs -f"
    echo "  - Stop containers: docker-compose down"
    echo "  - Restart containers: docker-compose restart"
    echo "  - Access app container: docker-compose exec app bash"
    echo "  - Access node container: docker-compose exec node sh"
else
    echo "❌ Application is not responding. Check logs with: docker-compose logs -f"
fi


