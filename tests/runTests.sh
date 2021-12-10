#!/bin/bash

#
# USAGE:
# execute from ojs folder as 'sh plugins/importexport/dnb/tests/runTests.sh -d'
#

set -xe # Fail on first error

# Identify the tests directory.
TESTS_DIR=`readlink -f "lib/pkp/tests"`

# Shortcuts to the test environments.
TEST_CONF1="--configuration $TESTS_DIR/phpunit-env1.xml"
TEST_CONF2="--configuration $TESTS_DIR/phpunit-env2.xml"

### Command Line Options ###
DEBUG=""

# Parse arguments
while getopts "d" opt; do
	case "$opt" in
		d)	DEBUG="--debug --verbose"
			export XDEBUG_SESSION=1
			;;
	esac
done

phpunit='php -d xdebug.mode=debug lib/pkp/lib/vendor/phpunit/phpunit/phpunit'
find "plugins/importexport/dnb" -maxdepth 3 -name tests -type d -exec $phpunit $DEBUG $TEST_CONF1 --testdox -v "{}" ";"
