#!/bin/bash

# School Management System Test Runner
# This script runs the complete test suite for the SMS plugin

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test configuration
DB_NAME="sms_test"
DB_USER="root"
DB_PASS=""
DB_HOST="localhost"
WP_VERSION="latest"

echo -e "${BLUE}School Management System Test Suite${NC}"
echo "======================================"

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Error: Composer is not installed. Please install Composer first.${NC}"
    exit 1
fi

# Check if PHPUnit is available
if [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${YELLOW}Installing test dependencies...${NC}"
    composer install --dev
fi

# Install WordPress test suite if not exists
if [ ! -d "/tmp/wordpress-tests-lib" ]; then
    echo -e "${YELLOW}Installing WordPress test suite...${NC}"
    bash bin/install-wp-tests.sh $DB_NAME $DB_USER $DB_PASS $DB_HOST $WP_VERSION
fi

# Run different test suites based on argument
case "${1:-all}" in
    "unit")
        echo -e "${BLUE}Running Unit Tests...${NC}"
        vendor/bin/phpunit --testsuite unit --colors=always
        ;;
    "integration")
        echo -e "${BLUE}Running Integration Tests...${NC}"
        vendor/bin/phpunit --testsuite integration --colors=always
        ;;
    "payment")
        echo -e "${BLUE}Running Payment Gateway Tests...${NC}"
        vendor/bin/phpunit tests/integration/test-sms-payment-gateways.php --colors=always
        ;;
    "workflow")
        echo -e "${BLUE}Running End-to-End Workflow Tests...${NC}"
        vendor/bin/phpunit tests/integration/test-sms-end-to-end-workflows.php --colors=always
        ;;
    "coverage")
        echo -e "${BLUE}Running Tests with Coverage Report...${NC}"
        vendor/bin/phpunit --coverage-html tests/coverage --colors=always
        echo -e "${GREEN}Coverage report generated in tests/coverage/index.html${NC}"
        ;;
    "all"|*)
        echo -e "${BLUE}Running All Tests...${NC}"
        vendor/bin/phpunit --colors=always
        ;;
esac

# Check test results
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo -e "${RED}✗ Some tests failed!${NC}"
    exit 1
fi

# Run code quality checks if requested
if [ "$2" = "quality" ]; then
    echo -e "${BLUE}Running Code Quality Checks...${NC}"
    
    # PHP Code Sniffer
    if [ -f "vendor/bin/phpcs" ]; then
        echo -e "${YELLOW}Running PHP Code Sniffer...${NC}"
        vendor/bin/phpcs --standard=WordPress includes/ --extensions=php --ignore=*/vendor/*
    fi
    
    # PHPStan
    if [ -f "vendor/bin/phpstan" ]; then
        echo -e "${YELLOW}Running PHPStan Analysis...${NC}"
        vendor/bin/phpstan analyse includes/ --level=5
    fi
fi

echo -e "${GREEN}Test suite completed successfully!${NC}"