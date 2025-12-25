#!/usr/bin/env bash

# Check code quality script
# Checks for basic code quality issues

set -e

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Running code quality checks${NC}"
echo "=============================="

# Check for PHP syntax errors
echo -e "\n${YELLOW}Checking PHP syntax...${NC}"
find src tests -name "*.php" -print0 | while IFS= read -r -d '' file; do
    php -l "$file" > /dev/null || exit 1
done
echo -e "${GREEN}✓ No syntax errors found${NC}"

# Check for common issues
echo -e "\n${YELLOW}Checking for common issues...${NC}"

# Check for var_dump
if grep -r "var_dump" src/ 2>/dev/null; then
    echo -e "${RED}✗ Found var_dump() in source code${NC}"
    exit 1
fi

# Check for print_r
if grep -r "print_r" src/ 2>/dev/null; then
    echo -e "${RED}✗ Found print_r() in source code${NC}"
    exit 1
fi

# Check for die/exit
if grep -r -E "(^|[^a-zA-Z_])(die|exit)\s*\(" src/ 2>/dev/null; then
    echo -e "${RED}✗ Found die/exit in source code${NC}"
    exit 1
fi

echo -e "${GREEN}✓ No common issues found${NC}"

# Run tests
echo -e "\n${YELLOW}Running unit tests...${NC}"
./vendor/bin/phpunit
echo -e "${GREEN}✓ All tests passed${NC}"

echo -e "\n${GREEN}=============================="
echo -e "All checks passed!${NC}"
