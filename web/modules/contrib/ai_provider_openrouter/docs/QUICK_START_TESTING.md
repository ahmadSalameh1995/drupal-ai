# Quick Start: Testing AI Provider OpenRouter

## What's Been Done

✅ **Fixed Critical Bug**: Implemented missing `getMaxInputTokens()` and `getMaxOutputTokens()` methods
✅ **Comprehensive Test Suite**: 29 kernel tests + functional tests covering all operations
✅ **Test Infrastructure**: Base classes, runners, and documentation

## Running Tests Right Now

### 1. Set Your OpenRouter API Key

```bash
# Add to your DDEV config
ddev config --web-environment-add=OPENROUTER_API_KEY=sk-or-v1-YOUR-ACTUAL-KEY

# Or export in your session
export OPENROUTER_API_KEY=sk-or-v1-YOUR-ACTUAL-KEY
ddev restart
```

### 2. Run All Tests

```bash
ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-all-tests.sh
```

### 3. Run Individual Test Suites

```bash
# Chat operations (12 tests)
ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-kernel-tests.sh

# Or run specific test classes
ddev ssh
cd /var/www/html
export OPENROUTER_API_KEY=sk-or-v1-YOUR-KEY

# Chat tests
vendor/bin/phpunit -c web/core web/modules/contrib/ai_provider_openrouter/tests/src/Kernel/OpenRouterProviderChatKernelTest.php

# Embeddings tests
vendor/bin/phpunit -c web/core web/modules/contrib/ai_provider_openrouter/tests/src/Kernel/OpenRouterProviderEmbeddingsKernelTest.php

# Text-to-image tests
vendor/bin/phpunit -c web/core web/modules/contrib/ai_provider_openrouter/tests/src/Kernel/OpenRouterProviderTextToImageKernelTest.php
```

## Manual Testing Scenarios

### Test 1: Verify Token Limits Work

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$input = \$provider->getMaxInputTokens('openai/gpt-4-turbo');
\$output = \$provider->getMaxOutputTokens('openai/gpt-4-turbo');
echo 'GPT-4 Turbo:' . PHP_EOL;
echo '  Input tokens: ' . \$input . PHP_EOL;
echo '  Output tokens: ' . \$output . PHP_EOL;
echo PHP_EOL;
\$input2 = \$provider->getMaxInputTokens('anthropic/claude-3.5-sonnet');
\$output2 = \$provider->getMaxOutputTokens('anthropic/claude-3.5-sonnet');
echo 'Claude 3.5 Sonnet:' . PHP_EOL;
echo '  Input tokens: ' . \$input2 . PHP_EOL;
echo '  Output tokens: ' . \$output2 . PHP_EOL;
"
```

**Expected**: Should show accurate context windows (GPT-4 Turbo: 128k input, Claude 3.5: 200k input)

### Test 2: Simple Chat

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$response = \$provider->chat('Say hello in 5 words or less', 'openai/gpt-3.5-turbo');
echo 'Response: ' . \$response->getNormalized() . PHP_EOL;
\$usage = \$response->getTokenUsage();
if (\$usage) {
  echo 'Tokens used: ' . \$usage->getTotal() . ' (input: ' . \$usage->getInput() . ', output: ' . \$usage->getOutput() . ')' . PHP_EOL;
}
"
```

**Expected**: Should get a short greeting and token usage stats

### Test 3: Embeddings

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$response = \$provider->embeddings('test sentence', 'openai/text-embedding-3-small');
\$embedding = \$response->getNormalized();
echo 'Vector dimensions: ' . count(\$embedding) . PHP_EOL;
echo 'First 5 values: ' . implode(', ', array_slice(\$embedding, 0, 5)) . PHP_EOL;
"
```

**Expected**: Should show 1536 dimensions and float values

### Test 4: Batch Embeddings

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$texts = ['First sentence', 'Second sentence', 'Third sentence'];
\$response = \$provider->embeddings(\$texts, 'openai/text-embedding-3-small');
\$embeddings = \$response->getNormalized();
echo 'Generated ' . count(\$embeddings) . ' embeddings' . PHP_EOL;
foreach (\$embeddings as \$i => \$emb) {
  echo 'Embedding ' . (\$i + 1) . ': ' . count(\$emb) . ' dimensions' . PHP_EOL;
}
"
```

**Expected**: Should show 3 embeddings, each with 1536 dimensions

### Test 5: Text-to-Image

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$response = \$provider->textToImage('A simple red circle', 'openai/dall-e-3');
\$images = \$response->getNormalized();
echo 'Generated ' . count(\$images) . ' image(s)' . PHP_EOL;
\$image = \$images[0];
echo 'Image size: ' . strlen(\$image->getBinaryContent()) . ' bytes' . PHP_EOL;
echo 'MIME type: ' . \$image->getMimeType() . PHP_EOL;
"
```

**Expected**: Should generate 1 image with PNG/JPEG mime type

### Test 6: Model Listing

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$chat = \$provider->getConfiguredModels('chat');
\$embeddings = \$provider->getConfiguredModels('embeddings');
\$images = \$provider->getConfiguredModels('text_to_image');
echo 'Available models:' . PHP_EOL;
echo '  Chat: ' . count(\$chat) . PHP_EOL;
echo '  Embeddings: ' . count(\$embeddings) . PHP_EOL;
echo '  Text-to-Image: ' . count(\$images) . PHP_EOL;
echo PHP_EOL;
echo 'Sample chat models:' . PHP_EOL;
foreach (array_slice(\$chat, 0, 5) as \$id => \$name) {
  echo '  - ' . \$id . PHP_EOL;
}
"
```

**Expected**: Should list hundreds of chat models, 20+ embedding models

## Testing DeepChat Integration

### 1. Create Test Assistant

```bash
ddev drush php:eval "
\$storage = \Drupal::entityTypeManager()->getStorage('ai_assistant');
if (\$existing = \$storage->load('test_openrouter_chat')) {
  \$existing->delete();
}
\$assistant = \$storage->create([
  'id' => 'test_openrouter_chat',
  'label' => 'Test OpenRouter Chat',
  'provider' => 'openrouter',
  'model' => 'openai/gpt-3.5-turbo',
  'system_prompt' => 'You are a helpful test assistant. Keep responses brief.',
]);
\$assistant->save();
echo 'Created assistant: test_openrouter_chat' . PHP_EOL;
echo 'Visit: /ai/assistant/test_openrouter_chat' . PHP_EOL;
"
```

### 2. Test in Browser

1. Visit: `https://aitester.ddev.site/ai/assistant/test_openrouter_chat`
2. Open DevTools → Network tab
3. Send a message: "Hello, how are you?"
4. Check the XHR request:
   - Should POST to `/ai/assistant-api/chat`
   - Request body should include `"stream": true`
   - Response `Content-Type: text/event-stream`
   - Multiple `data: ...` lines should appear incrementally

### 3. Test Streaming vs Non-Streaming

```bash
# Convert to agent-based (disables streaming)
ddev drush php:eval "
\$assistant = \Drupal::entityTypeManager()->getStorage('ai_assistant')->load('test_openrouter_chat');
\$assistant->set('ai_agent', 'test_agent');
\$assistant->save();
echo 'Converted to agent-based (streaming disabled)' . PHP_EOL;
"

# Test in browser - should now return single JSON response (no streaming)

# Convert back to legacy (enables streaming)
ddev drush php:eval "
\$assistant = \Drupal::entityTypeManager()->getStorage('ai_assistant')->load('test_openrouter_chat');
\$assistant->set('ai_agent', '');
\$assistant->save();
echo 'Converted to legacy (streaming enabled)' . PHP_EOL;
"
```

## Testing AI Agents with Tools

### 1. Verify Tools Support

```bash
ddev drush php:eval "
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ChatTools;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;

\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');

// Define a simple tool
\$tools = new ChatTools();
\$tools->addFunction(new ToolsFunctionInput(
  'get_weather',
  'Get current weather for a location',
  ['type' => 'object', 'properties' => ['location' => ['type' => 'string']]],
  ['location']
));

\$input = new ChatInput([
  new ChatMessage('user', 'What is the weather in San Francisco?', [])
]);
\$input->setChatTools(\$tools);

\$response = \$provider->chat(\$input, 'openai/gpt-4-turbo');
echo 'Response: ' . \$response->getNormalized() . PHP_EOL;

// Check if tool was called
\$message = \$response->getMessage();
if (\$message && \$message->getTools()) {
  echo 'Tool calls detected!' . PHP_EOL;
  foreach (\$message->getTools() as \$tool) {
    echo '  Function: ' . \$tool->getFunction()->getName() . PHP_EOL;
  }
}
"
```

**Expected**: Model should recognize it needs to call `get_weather` function

## Checking for Bugs

### Bug Check 1: Token Limits

```bash
# This should NOT return 1024 anymore (old bug)
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$tokens = \$provider->getMaxInputTokens('openai/gpt-4-turbo');
if (\$tokens == 1024) {
  echo '❌ BUG: Still using default 1024 tokens!' . PHP_EOL;
} else if (\$tokens > 100000) {
  echo '✅ FIXED: Showing accurate context window (' . \$tokens . ' tokens)' . PHP_EOL;
} else {
  echo '⚠️  UNEXPECTED: Got ' . \$tokens . ' tokens' . PHP_EOL;
}
"
```

### Bug Check 2: Embeddings Model Filtering

```bash
# Chat models should NOT appear in embeddings list
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$embedding_models = \$provider->getConfiguredModels('embeddings');
\$has_gpt4 = isset(\$embedding_models['openai/gpt-4']);
if (\$has_gpt4) {
  echo '❌ BUG: Chat model appearing in embeddings list!' . PHP_EOL;
} else {
  echo '✅ CORRECT: Chat models excluded from embeddings' . PHP_EOL;
}
"
```

### Bug Check 3: Streaming Detection

```bash
# Legacy assistants should support streaming
ddev drush php:eval "
\$assistant = \Drupal::entityTypeManager()->getStorage('ai_assistant')->load('test_openrouter_chat');
\$is_agent = !empty(\$assistant->get('ai_agent')->value);
echo 'Assistant type: ' . (\$is_agent ? 'Agent-based (no streaming)' : 'Legacy (streaming enabled)') . PHP_EOL;
"
```

## What to Look For

### ✅ Tests Should Pass
- Token limit methods return accurate values (not 1024)
- Chat operations work with string, array, and ChatInput
- Embeddings work for single strings and batches
- Text-to-image generates valid image files
- Model filtering separates chat/embeddings/image models

### ❌ Known Issues to Watch For
- Rate limits (429 errors) - wait and retry
- Invalid API key (401 errors) - check key configuration
- Model not found (404 errors) - verify model ID is correct
- Streaming hangs - ensure assistant is legacy (no ai_agent)

## Next Steps

1. **Run the full test suite** with your API key
2. **Test DeepChat** in browser with streaming
3. **Test AI agents** with tool calling
4. **Report any failures** - we'll fix them immediately

## Quick Fixes

### If tests fail with "API key not found":
```bash
ddev drush config:get ai_provider_openrouter.settings api_key
ddev drush key:list
# Verify the key entity exists and has a value
```

### If tests timeout:
```bash
# Increase timeout in phpunit.xml or skip slow tests
export OPENROUTER_SKIP_REAL_API=1
```

### If streaming doesn't work:
```bash
# Ensure assistant is legacy
ddev drush php:eval "\$a = \Drupal::entityTypeManager()->getStorage('ai_assistant')->load('test_openrouter_chat'); \$a->set('ai_agent', ''); \$a->save();"
ddev drush cr
```
