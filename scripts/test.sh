#!/usr/bin/env bash

# Test runner script for SalahAPI PHP
# Usage: ./scripts/test.sh [options]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Running SalahAPI PHP Tests${NC}"
echo "================================"

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}Vendor directory not found. Running composer install...${NC}"
    composer install
fi

# Parse arguments
COVERAGE=false
VERBOSE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --coverage)
            COVERAGE=true
            shift
            ;;
        --verbose|-v)
            VERBOSE=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --coverage    Generate coverage report"
            echo "  --verbose     Verbose output"
            echo "  --help        Show this help message"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

# Run tests
if [ "$COVERAGE" = true ]; then
    echo -e "${GREEN}Running tests with coverage...${NC}"
    ./vendor/bin/phpunit --coverage-html coverage --coverage-text
    echo -e "${GREEN}Coverage report generated in coverage/index.html${NC}"
elif [ "$VERBOSE" = true ]; then
    echo -e "${GREEN}Running tests in verbose mode...${NC}"
    ./vendor/bin/phpunit --verbose
else
    echo -e "${GREEN}Running tests...${NC}"
    ./vendor/bin/phpunit
fi

echo -e "${GREEN}✓ All tests passed!${NC}"
