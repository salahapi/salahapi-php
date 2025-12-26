#!/usr/bin/env bash

# Calculate prayer times for a date range using SalahAPI configuration
# Usage: ./scripts/calculate.sh <start-date> <end-date> <json-file>
#
# Arguments:
#   start-date   Start date in YYYY-MM-DD format
#   end-date     End date in YYYY-MM-DD format
#   json-file    Path to JSON file containing SalahAPI configuration
#
# Example:
#   ./scripts/calculate.sh 2024-01-01 2024-01-31 config.json

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to display usage
usage() {
    echo "Usage: $0 <start-date> <end-date> <json-file>"
    echo ""
    echo "Arguments:"
    echo "  start-date   Start date in YYYY-MM-DD format"
    echo "  end-date     End date in YYYY-MM-DD format"
    echo "  json-file    Path to JSON file containing SalahAPI configuration"
    echo ""
    echo "Example:"
    echo "  $0 2024-01-01 2024-01-31 config.json"
    exit 1
}

# Check for required arguments
if [ $# -ne 3 ]; then
    echo -e "${RED}Error: Missing required arguments${NC}"
    usage
fi

START_DATE="$1"
END_DATE="$2"
JSON_FILE="$3"

# Validate date format
if ! [[ "$START_DATE" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
    echo -e "${RED}Error: Invalid start date format. Expected YYYY-MM-DD${NC}"
    exit 1
fi

if ! [[ "$END_DATE" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
    echo -e "${RED}Error: Invalid end date format. Expected YYYY-MM-DD${NC}"
    exit 1
fi

# Check if JSON file exists
if [ ! -f "$JSON_FILE" ]; then
    echo -e "${RED}Error: JSON file not found: $JSON_FILE${NC}"
    exit 1
fi

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}Vendor directory not found. Running composer install...${NC}" >&2
    composer install --quiet
fi

# Create temporary PHP script to calculate prayer times
TEMP_PHP=$(mktemp /tmp/calculate_prayer_times_XXXXXX.php)

cat > "$TEMP_PHP" << 'EOPHP'
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use SalahAPI\SalahAPI;
use SalahAPI\Calculations\Builder;

// Get command line arguments
if ($argc !== 4) {
    fwrite(STDERR, "Error: Invalid number of arguments\n");
    exit(1);
}

$startDate = $argv[1];
$endDate = $argv[2];
$jsonFile = $argv[3];

// Read and parse JSON file
$jsonContent = @file_get_contents($jsonFile);
if ($jsonContent === false) {
    fwrite(STDERR, "Error: Failed to read JSON file: $jsonFile\n");
    exit(1);
}

try {
    $salahApi = SalahAPI::fromJson($jsonContent);
} catch (Exception $e) {
    fwrite(STDERR, "Error: Failed to parse JSON: " . $e->getMessage() . "\n");
    exit(1);
}

// Validate required fields
if ($salahApi->location === null) {
    fwrite(STDERR, "Error: JSON file must include 'location' object with latitude, longitude, and timezone\n");
    exit(1);
}

if ($salahApi->calculationMethod === null) {
    fwrite(STDERR, "Error: JSON file must include 'calculationMethod' object\n");
    exit(1);
}

// Create builder
try {
    $builder = new Builder(
        $salahApi->location,
        $salahApi->calculationMethod,
        $salahApi->location->elevation ?? 0
    );
    
    // Calculate prayer times and output CSV
    $csv = $builder->buildCsv($startDate, $endDate);
    echo $csv;
    
} catch (Exception $e) {
    fwrite(STDERR, "Error: Failed to calculate prayer times: " . $e->getMessage() . "\n");
    exit(1);
}
EOPHP

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_DIR="$( cd "$SCRIPT_DIR/.." && pwd )"

# Replace __DIR__ in the temp PHP script with the actual scripts directory
sed -i.bak "s|__DIR__ . '/..|'$PROJECT_DIR|g" "$TEMP_PHP"
rm -f "$TEMP_PHP.bak"

# Run the PHP script and capture output
php "$TEMP_PHP" "$START_DATE" "$END_DATE" "$JSON_FILE"
EXIT_CODE=$?

# Clean up temporary file
rm -f "$TEMP_PHP"

exit $EXIT_CODE
