#!/bin/bash

# Simple test runner that uses drush eval to run tests
# Usage: ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-tests.sh

set -e

echo "=========================================="
echo "OpenRouter Provider - Feature Tests"
echo "=========================================="
echo ""

PASSED=0
FAILED=0

# Test 1: Token limits
echo "Testing token limits..."
RESULT=$(drush ev "
\$p = \Drupal::service('ai.provider')->createInstance('openrouter');
\$input = \$p->getMaxInputTokens('openai/gpt-4-turbo');
\$output = \$p->getMaxOutputTokens('openai/gpt-4-turbo');
if (\$input > 100000 && \$output > 1000) {
  echo 'PASS';
} else {
  echo 'FAIL: input=' . \$input . ', output=' . \$output;
}
" 2>&1)

if [[ "$RESULT" == "PASS" ]]; then
  echo "✓ Token limits accurate - PASSED"
  ((PASSED++))
else
  echo "✗ Token limits accurate - FAILED: $RESULT"
  ((FAILED++))
fi

# Test 2: Simple chat
echo ""
echo "Testing chat with string input..."
RESULT=$(drush ev "
\$p = \Drupal::service('ai.provider')->createInstance('openrouter');
try {
  \$r = \$p->chat('Say TEST', 'openai/gpt-3.5-turbo');
  \$text = \$r->getNormalized()->getText();
  echo (strlen(\$text) > 0) ? 'PASS' : 'FAIL: empty response';
} catch (\Exception \$e) {
  echo 'FAIL: ' . \$e->getMessage();
}
" 2>&1)

if [[ "$RESULT" == "PASS" ]]; then
  echo "✓ Chat with string input - PASSED"
  ((PASSED++))
else
  echo "✗ Chat with string input - FAILED: $RESULT"
  ((FAILED++))
fi

# Test 3: ChatInput without streaming
echo ""
echo "Testing chat with ChatInput (no streaming)..."
RESULT=$(drush ev "
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
\$p = \Drupal::service('ai.provider')->createInstance('openrouter');
try {
  \$input = new ChatInput([new ChatMessage('user', 'Say HELLO', [])]);
  \$r = \$p->chat(\$input, 'openai/gpt-3.5-turbo');
  \$text = \$r->getNormalized()->getText();
  echo (strlen(\$text) > 0) ? 'PASS' : 'FAIL: empty response';
} catch (\Exception \$e) {
  echo 'FAIL: ' . \$e->getMessage();
}
" 2>&1)

if [[ "$RESULT" == "PASS" ]]; then
  echo "✓ Chat with ChatInput (no streaming) - PASSED"
  ((PASSED++))
else
  echo "✗ Chat with ChatInput (no streaming) - FAILED: $RESULT"
  ((FAILED++))
fi

# Test 4: ChatInput WITH streaming (THE CRITICAL BUG)
echo ""
echo "Testing chat with ChatInput + streaming (AI 1.3.0 fix)..."
RESULT=$(drush ev "
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
\$p = \Drupal::service('ai.provider')->createInstance('openrouter');
try {
  \$input = new ChatInput([new ChatMessage('user', 'Say WORLD', [])]);
  \$input->setStreamedOutput(TRUE);
  \$r = \$p->chat(\$input, 'openai/gpt-3.5-turbo');
  \$normalized = \$r->getNormalized();
  if (\$normalized instanceof \Traversable) {
    \$text = '';
    foreach (\$normalized as \$chunk) {
      \$text .= \$chunk->getText();
    }
    echo (strlen(\$text) > 0) ? 'PASS' : 'FAIL: empty stream';
  } else {
    echo 'FAIL: not a stream';
  }
} catch (\Exception \$e) {
  echo 'FAIL: ' . \$e->getMessage();
}
" 2>&1)

if [[ "$RESULT" == "PASS" ]]; then
  echo "✓ Chat with streaming enabled - PASSED"
  ((PASSED++))
else
  echo "✗ Chat with streaming enabled - FAILED: $RESULT"
  ((FAILED++))
fi

# Test 5: Embeddings single
echo ""
echo "Testing embeddings (single string)..."
RESULT=$(drush ev "
\$p = \Drupal::service('ai.provider')->createInstance('openrouter');
try {
  \$r = \$p->embeddings('test', 'openai/text-embedding-3-small');
  \$emb = \$r->getNormalized();
  echo (is_array(\$emb) && count(\$emb) === 1536) ? 'PASS' : 'FAIL: got ' . count(\$emb) . ' dimensions';
} catch (\Exception \$e) {
  echo 'FAIL: ' . \$e->getMessage();
}
" 2>&1)

if [[ "$RESULT" == "PASS" ]]; then
  echo "✓ Embeddings (single string) - PASSED"
  ((PASSED++))
else
  echo "✗ Embeddings (single string) - FAILED: $RESULT"
  ((FAILED++))
fi

# Test 6: Embeddings batch
echo ""
echo "Testing embeddings (batch)..."
RESULT=$(drush ev "
\$p = \Drupal::service('ai.provider')->createInstance('openrouter');
try {
  \$r = \$p->embeddings(['First', 'Second', 'Third'], 'openai/text-embedding-3-small');
  \$embs = \$r->getNormalized();
  if (is_array(\$embs) && count(\$embs) === 3) {
    \$all_ok = true;
    foreach (\$embs as \$e) {
      if (!is_array(\$e) || count(\$e) !== 1536) {
        \$all_ok = false;
        break;
      }
    }
    echo \$all_ok ? 'PASS' : 'FAIL: wrong dimensions';
  } else {
    echo 'FAIL: expected 3 embeddings, got ' . count(\$embs);
  }
} catch (\Exception \$e) {
  echo 'FAIL: ' . \$e->getMessage();
}
" 2>&1)

if [[ "$RESULT" == "PASS" ]]; then
  echo "✓ Embeddings (batch) - PASSED"
  ((PASSED++))
else
  echo "✗ Embeddings (batch) - FAILED: $RESULT"
  ((FAILED++))
fi

# Test 7: Model listing
echo ""
echo "Testing model listing..."
RESULT=$(drush ev "
\$p = \Drupal::service('ai.provider')->createInstance('openrouter');
try {
  \$chat = \$p->getConfiguredModels('chat');
  \$emb = \$p->getConfiguredModels('embeddings');
  echo (count(\$chat) > 0 && count(\$emb) > 0) ? 'PASS' : 'FAIL: no models';
} catch (\Exception \$e) {
  echo 'FAIL: ' . \$e->getMessage();
}
" 2>&1)

if [[ "$RESULT" == "PASS" ]]; then
  echo "✓ Model listing - PASSED"
  ((PASSED++))
else
  echo "✗ Model listing - FAILED: $RESULT"
  ((FAILED++))
fi

# Summary
TOTAL=$((PASSED + FAILED))
echo ""
echo "=========================================="
echo "TEST SUMMARY"
echo "=========================================="
echo "Total: $TOTAL"
echo "Passed: $PASSED"
echo "Failed: $FAILED"
echo ""

if [ $FAILED -eq 0 ]; then
  echo "✓ ALL TESTS PASSED"
  exit 0
else
  echo "✗ SOME TESTS FAILED"
  exit 1
fi
