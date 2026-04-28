#!/bin/bash

# Comprehensive test runner for ai_provider_openrouter module
# Usage: ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-all-tests.sh

set -e

MODULE_PATH="web/modules/contrib/ai_provider_openrouter"
PHPUNIT="vendor/bin/phpunit"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=========================================="
echo "AI Provider OpenRouter - Test Suite"
echo "=========================================="
echo ""

# Check if OPENROUTER_API_KEY is set
if [ -z "$OPENROUTER_API_KEY" ]; then
    echo -e "${YELLOW}WARNING: OPENROUTER_API_KEY not set.${NC}"
    echo "Real API tests will be skipped."
    echo "To run full tests: export OPENROUTER_API_KEY=sk-or-v1-..."
    echo ""
    export OPENROUTER_SKIP_REAL_API=1
else
    echo -e "${GREEN}✓ OPENROUTER_API_KEY is set${NC}"
    echo "Real API tests will run."
    echo ""
fi

# Function to run tests
run_test_suite() {
    local suite_name=$1
    local test_path=$2
    local test_group=$3
    
    echo "=========================================="
    echo "Running: $suite_name"
    echo "=========================================="
    
    if [ -n "$test_group" ]; then
        $PHPUNIT --group "$test_group" "$test_path" || {
            echo -e "${RED}✗ $suite_name FAILED${NC}"
            return 1
        }
    else
        $PHPUNIT "$test_path" || {
            echo -e "${RED}✗ $suite_name FAILED${NC}"
            return 1
        }
    fi
    
    echo -e "${GREEN}✓ $suite_name PASSED${NC}"
    echo ""
    return 0
}

# Track results
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Run Unit Tests
echo "=========================================="
echo "UNIT TESTS"
echo "=========================================="
echo ""

if run_test_suite "OpenRouterClient Unit Tests" "$MODULE_PATH/tests/src/Unit" "ai_provider_openrouter"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Run Kernel Tests
echo "=========================================="
echo "KERNEL TESTS"
echo "=========================================="
echo ""

# Provider registration
if run_test_suite "Provider Registration" "$MODULE_PATH/tests/src/Kernel/OpenRouterProviderKernelTest.php" "ai_provider_openrouter"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Chat operations
if run_test_suite "Chat Operations" "$MODULE_PATH/tests/src/Kernel/OpenRouterProviderChatKernelTest.php" "ai_provider_openrouter"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Embeddings operations
if run_test_suite "Embeddings Operations" "$MODULE_PATH/tests/src/Kernel/OpenRouterProviderEmbeddingsKernelTest.php" "ai_provider_openrouter"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Text-to-image operations
if run_test_suite "Text-to-Image Operations" "$MODULE_PATH/tests/src/Kernel/OpenRouterProviderTextToImageKernelTest.php" "ai_provider_openrouter"; then
    ((PASSED_TESTS++))
else
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# Model filtering
if [ -f "$MODULE_PATH/tests/src/Kernel/OpenRouterProviderModelFilterKernelTest.php" ]; then
    if run_test_suite "Model Filtering" "$MODULE_PATH/tests/src/Kernel/OpenRouterProviderModelFilterKernelTest.php" "ai_provider_openrouter"; then
        ((PASSED_TESTS++))
    else
        ((FAILED_TESTS++))
    fi
    ((TOTAL_TESTS++))
fi

# Run Functional Tests (if not skipping)
if [ -z "$SKIP_FUNCTIONAL_TESTS" ]; then
    echo "=========================================="
    echo "FUNCTIONAL TESTS"
    echo "=========================================="
    echo ""
    
    if run_test_suite "Functional Integration Tests" "$MODULE_PATH/tests/src/Functional" "ai_provider_openrouter"; then
        ((PASSED_TESTS++))
    else
        ((FAILED_TESTS++))
    fi
    ((TOTAL_TESTS++))
else
    echo "Skipping functional tests (SKIP_FUNCTIONAL_TESTS is set)"
    echo ""
fi

# Summary
echo "=========================================="
echo "TEST SUMMARY"
echo "=========================================="
echo ""
echo "Total Test Suites: $TOTAL_TESTS"
echo -e "${GREEN}Passed: $PASSED_TESTS${NC}"
if [ $FAILED_TESTS -gt 0 ]; then
    echo -e "${RED}Failed: $FAILED_TESTS${NC}"
else
    echo "Failed: 0"
fi
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}=========================================="
    echo "ALL TESTS PASSED! ✓"
    echo "==========================================${NC}"
    exit 0
else
    echo -e "${RED}=========================================="
    echo "SOME TESTS FAILED ✗"
    echo "==========================================${NC}"
    exit 1
fi
