#!/bin/bash

#
# USAGE:
# execute from ojs folder as 'sh plugins/generic/dnb/tests/runTests.sh' or with -d for debug mode
#

set -e # Fail on first error

# Identify the tests directory.
TESTS_DIR=`readlink -f "lib/pkp/tests"`

# Shortcuts to the test environments.
TEST_CONF1="--configuration $TESTS_DIR/phpunit.xml"
TEST_CONF2="--configuration $TESTS_DIR/phpunit-env2.xml"

### Command Line Options ###
DEBUG=""
DEBUG_MODE="coverage"

# Parse arguments
while getopts "d" opt; do
	case "$opt" in
		d)	DEBUG="--debug" # --verbose
			DEBUG_MODE="debug"
			export XDEBUG_SESSION=1
			;;
	esac
done

# use xdebug.mode=coverage to run the test and xdebug.mode=debug for debugging
phpunit='php -d xdebug.mode='$DEBUG_MODE' lib/pkp/lib/vendor/phpunit/phpunit/phpunit'

# Ask user which test type to run
echo "=== DNB Plugin Test Runner ==="
echo ""
echo "Select test type:"
echo "  1) Unit tests"
echo "  2) Functional tests"
echo "  3) All tests (unit + functional)"
echo ""
read -p "Enter your choice (1-3): " choice

case $choice in
    1)
        TEST_PATH="plugins/generic/dnb/tests/unit/"
        echo ""
        echo "Running unit tests..."
        ;;
    2)
        TEST_PATH="plugins/generic/dnb/tests/functional/"
        echo ""
        echo "Running functional tests..."
        ;;
    3)
        TEST_PATH="plugins/generic/dnb/tests/"
        echo ""
        echo "Running all tests..."
        ;;
    *)
        echo "Invalid choice. Exiting."
        exit 1
        ;;
esac

# Set output format: always use testdox
OUTPUT_FLAG="--testdox"

# If debug mode, add verbose debug output
if [ "$DEBUG" = "--debug" ]; then
    OUTPUT_FLAG="$OUTPUT_FLAG --display-warnings --display-notices --display-deprecations"
    echo ""
    echo "Debug mode enabled: showing detailed test lifecycle events"
fi

echo "=========================================="
set -x # Show command being executed
$phpunit $DEBUG $TEST_CONF1 $OUTPUT_FLAG "$TEST_PATH"
