#!/bin/bash
# Quick start script for YOURLS Docker setup

set -e

# Ensure Homebrew binaries are in PATH (for Colima/Docker on macOS)
export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"

echo "🚀 YOURLS Docker Setup"
echo "======================"
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file from env.example..."
    cp env.example .env
    echo "✅ .env file created. Please edit it with your settings before continuing."
    echo ""
    echo "⚠️  Important: Update the following in .env:"
    echo "   - YOURLS_SITE (your domain)"
    echo "   - YOURLS_DB_PASS (secure password)"
    echo "   - MYSQL_ROOT_PASSWORD (secure password)"
    echo "   - YOURLS_COOKIEKEY (generate with: openssl rand -hex 32)"
    echo "   - YOURLS_USER and YOURLS_PASS (admin credentials)"
    echo ""
    read -p "Press Enter after you've edited .env, or Ctrl+C to cancel..."
fi

# Check for Colima (common on macOS)
if command -v colima &> /dev/null; then
    echo "🐳 Detected Colima..."
    
    # Check if Docker CLI is installed (required for Colima)
    if ! command -v docker &> /dev/null; then
        echo "❌ Docker CLI is not installed."
        echo ""
        echo "📥 Colima requires Docker CLI. Install it with:"
        echo "   brew install docker"
        echo ""
        echo "Then run this script again."
        exit 1
    fi
    
    # Check if Colima is running
    if ! colima status &> /dev/null; then
        echo "🚀 Colima is not running. Starting Colima..."
        if colima start; then
            echo "⏳ Waiting for Colima to be ready..."
            sleep 5
        else
            echo "❌ Failed to start Colima. Please check the error above."
            exit 1
        fi
    else
        echo "✅ Colima is running"
    fi
    
    # Set Docker context to Colima if needed
    if docker context ls 2>/dev/null | grep -q "colima"; then
        docker context use colima &> /dev/null || true
    fi
fi

# Check if Docker is available
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed or not in your PATH."
    echo ""
    echo "📥 Please install Docker CLI:"
    echo "   brew install docker"
    echo ""
    echo "Or install Docker Desktop:"
    echo "   https://www.docker.com/products/docker-desktop"
    exit 1
fi

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is installed but not running."
    echo ""
    if command -v colima &> /dev/null; then
        echo "🚀 Starting Colima..."
        colima start
        echo "⏳ Waiting for Colima to be ready..."
        sleep 5
    else
        echo "🚀 Please start Docker Desktop and wait for it to fully start, then try again."
        echo "   On macOS, you can start it with: open -a Docker"
        exit 1
    fi
fi

# Final check
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is still not available. Please check your setup."
    exit 1
fi

# Check for docker-compose (prefer plugin version)
if docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
elif command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
else
    echo "❌ docker-compose is not available."
    echo ""
    echo "📥 Install it with one of:"
    echo "   - Modern Docker includes 'docker compose' plugin"
    echo "   - Or install separately: brew install docker-compose"
    exit 1
fi

# Build and start containers
echo "🔨 Building Docker images..."
$DOCKER_COMPOSE build

echo "🚀 Starting containers..."
$DOCKER_COMPOSE up -d

echo ""
echo "✅ YOURLS is starting up!"
echo ""
echo "📋 Next steps:"
echo "   1. Wait a few seconds for the database to initialize"
echo "   2. Open http://localhost:8080/admin/install.php in your browser"
echo "   3. Complete the installation"
echo ""
echo "📊 Useful commands:"
echo "   - View logs: $DOCKER_COMPOSE logs -f"
echo "   - Stop: $DOCKER_COMPOSE down"
echo "   - Restart: $DOCKER_COMPOSE restart"
echo ""
