#!/usr/bin/env bash

# Installation script for SalahAPI PHP
# This script helps set up the development environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}  SalahAPI PHP Setup Script     ${NC}"
echo -e "${BLUE}================================${NC}"
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo -e "${RED}Error: PHP is not installed${NC}"
    echo "Please install PHP 7.4 or higher"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo -e "${GREEN}✓${NC} PHP version: $PHP_VERSION"

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${YELLOW}Warning: Composer is not installed${NC}"
    echo "Please install Composer from https://getcomposer.org"
    exit 1
fi

echo -e "${GREEN}✓${NC} Composer is installed"

# Install dependencies
echo ""
echo -e "${YELLOW}Installing dependencies...${NC}"
composer install

# Make scripts executable
chmod +x scripts/test.sh

echo ""
echo -e "${GREEN}✓${NC} Dependencies installed successfully"
echo ""

# Run tests to verify installation
echo -e "${YELLOW}Running tests to verify installation...${NC}"
./vendor/bin/phpunit

echo ""
echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}  Setup completed successfully!  ${NC}"
echo -e "${GREEN}================================${NC}"
echo ""
echo "Available commands:"
echo "  composer test              - Run all tests"
echo "  composer test-coverage     - Run tests with coverage"
echo "  ./scripts/test.sh          - Run tests with custom options"
echo "  php scripts/example.php    - Run usage examples"
echo ""
echo "Happy coding! 🕌"
