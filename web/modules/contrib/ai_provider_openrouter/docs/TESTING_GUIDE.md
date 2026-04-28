# AI Provider OpenRouter - Testing Guide

## Overview

This module now has comprehensive test coverage including:
- **Unit Tests**: Service and client logic
- **Kernel Tests**: Provider operations (chat, embeddings, text-to-image)
- **Functional Tests**: Full Drupal integration with assistants, DeepChat, agents

## Quick Start

### 1. Set Up API Key (for real API tests)

```bash
# Export your OpenRouter API key
export OPENROUTER_API_KEY=sk-or-v1-your-actual-key-here

# Or add to .ddev/config.yaml:
web_environment:
  - OPENROUTER_API_KEY=sk-or-v1-your-actual-key-here
```

### 2. Run All Tests

```bash
# Inside DDEV container
ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-all-tests.sh

# Or from host
ddev ssh
cd /var/www/html
bash web/modules/contrib/ai_provider_openrouter/scripts/run-all-tests.sh
```

### 3. Run Specific Test Suites

```bash
# Unit tests only
vendor/bin/phpunit web/modules/contrib/ai_provider_openrouter/tests/src/Unit

# Kernel tests only
vendor/bin/phpunit web/modules/contrib/ai_provider_openrouter/tests/src/Kernel

# Functional tests only
vendor/bin/phpunit web/modules/contrib/ai_provider_openrouter/tests/src/Functional

# Specific test class
vendor/bin/phpunit web/modules/contrib/ai_provider_openrouter/tests/src/Kernel/OpenRouterProviderChatKernelTest.php

# Specific test method
vendor/bin/phpunit --filter testSimpleTextChatWithString web/modules/contrib/ai_provider_openrouter/tests/src/Kernel/OpenRouterProviderChatKernelTest.php
```

## Test Coverage

### Unit Tests (tests/src/Unit/)

**OpenRouterClientTest.php**
- ✅ Client initialization with config and key
- 🔄 TODO: Mock HTTP responses for API calls
- 🔄 TODO: Error handling tests

### Kernel Tests (tests/src/Kernel/)

**OpenRouterKernelTestBase.php**
- Base class with shared setup
- API key configuration
- Real API vs. mocked test detection

**OpenRouterProviderKernelTest.php**
- ✅ Provider plugin registration

**OpenRouterProviderChatKernelTest.php**
- ✅ ChatInterface implementation
- ✅ getMaxInputTokens() - returns accurate context windows
- ✅ getMaxOutputTokens() - returns accurate output limits
- ✅ Simple text chat with string input
- ✅ Chat with ChatInput object
- ✅ Multi-turn conversations
- ✅ System prompt handling
- ✅ Token usage tracking
- ✅ Streaming chat (SSE)
- ✅ Error handling (invalid models)

**OpenRouterProviderEmbeddingsKernelTest.php**
- ✅ EmbeddingsInterface implementation
- ✅ Single string embedding
- ✅ Batch embeddings (array of strings)
- ✅ EmbeddingsInput object support
- ✅ embeddingsVectorSize() for all 21 models
- ✅ maxEmbeddingsInput() limits
- ✅ Model filtering (embeddings-only)
- ✅ Empty string handling
- ✅ Long text handling

**OpenRouterProviderTextToImageKernelTest.php**
- ✅ TextToImageInterface implementation
- ✅ Simple text-to-image with string
- ✅ TextToImageInput object support
- ✅ Image validation (binary content, mime type)
- ✅ Error handling (invalid models)
- ✅ Model identification (DALL-E, etc.)

**OpenRouterProviderModelFilterKernelTest.php** (existing)
- ✅ Model filtering logic

### Functional Tests (tests/src/Functional/)

**OpenRouterProviderFunctionalTest.php**
- ✅ Configuration form access
- ✅ Provider availability in plugin manager
- ✅ Model selection form
- ✅ Creating AI assistants with OpenRouter
- ✅ Operation type support verification
- ✅ Provider usability checks
- ✅ Model filtering configuration
- ✅ DeepChat integration

## Test Modes

### Mode 1: Mocked Tests (Default)

When `OPENROUTER_API_KEY` is not set, tests that require real API calls are skipped.

```bash
# Run without API key - only registration/structure tests run
ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-all-tests.sh
```

### Mode 2: Real API Tests (Recommended)

When `OPENROUTER_API_KEY` is set, tests make actual API calls to OpenRouter.

```bash
# Run with real API key - full integration testing
export OPENROUTER_API_KEY=sk-or-v1-your-key
ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-all-tests.sh
```

**Note:** Real API tests will consume credits. Use a test account or monitor usage.

### Mode 3: Skip Functional Tests

For faster iteration, skip browser-based functional tests:

```bash
export SKIP_FUNCTIONAL_TESTS=1
ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-all-tests.sh
```

## Manual Testing Scenarios

### Test 1: Basic Chat

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$response = \$provider->chat('Say hello', 'openai/gpt-3.5-turbo');
echo \$response->getNormalized() . PHP_EOL;
"
```

### Test 2: Embeddings

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$response = \$provider->embeddings('test sentence', 'openai/text-embedding-3-small');
\$embedding = \$response->getNormalized();
echo 'Vector dimensions: ' . count(\$embedding) . PHP_EOL;
"
```

### Test 3: Text-to-Image

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$response = \$provider->textToImage('A red circle', 'openai/dall-e-3');
\$images = \$response->getNormalized();
echo 'Generated ' . count(\$images) . ' image(s)' . PHP_EOL;
"
```

### Test 4: Token Limits

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$input = \$provider->getMaxInputTokens('openai/gpt-4-turbo');
\$output = \$provider->getMaxOutputTokens('openai/gpt-4-turbo');
echo 'GPT-4 Turbo - Input: ' . \$input . ', Output: ' . \$output . PHP_EOL;
"
```

### Test 5: Model Listing

```bash
ddev drush php:eval "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$chat_models = \$provider->getConfiguredModels('chat');
\$embedding_models = \$provider->getConfiguredModels('embeddings');
echo 'Chat models: ' . count(\$chat_models) . PHP_EOL;
echo 'Embedding models: ' . count(\$embedding_models) . PHP_EOL;
"
```

## Testing DeepChat Integration

### 1. Create a Test Assistant

```bash
ddev drush php:eval "
\$assistant = \Drupal::entityTypeManager()
  ->getStorage('ai_assistant')
  ->create([
    'id' => 'test_openrouter',
    'label' => 'Test OpenRouter Assistant',
    'provider' => 'openrouter',
    'model' => 'openai/gpt-3.5-turbo',
    'system_prompt' => 'You are a helpful test assistant.',
  ]);
\$assistant->save();
echo 'Created assistant: test_openrouter' . PHP_EOL;
"
```

### 2. Test Streaming (Legacy Assistant)

1. Visit `/ai/assistant/test_openrouter` in browser
2. Open browser DevTools → Network tab
3. Send a message in DeepChat
4. Verify:
   - Request includes `"stream": true`
   - Response `Content-Type: text/event-stream`
   - Multiple `data: ...` lines appear incrementally

### 3. Test Non-Streaming (Agent-Based Assistant)

```bash
# Create agent-based assistant
ddev drush php:eval "
\$assistant = \Drupal::entityTypeManager()
  ->getStorage('ai_assistant')
  ->load('test_openrouter');
\$assistant->set('ai_agent', 'some_agent_id');
\$assistant->save();
echo 'Converted to agent-based assistant' . PHP_EOL;
"
```

Then test in browser - should return single JSON response (no streaming).

## Testing AI Agents with Tools

### 1. Create Agent with Tools

```bash
ddev drush php:eval "
// Create a simple tool-using agent
\$agent = \Drupal::entityTypeManager()
  ->getStorage('ai_agent')
  ->create([
    'id' => 'test_tool_agent',
    'label' => 'Test Tool Agent',
    'provider' => 'openrouter',
    'model' => 'openai/gpt-4-turbo',
    'tools' => [
      [
        'type' => 'function',
        'function' => [
          'name' => 'get_weather',
          'description' => 'Get current weather',
          'parameters' => [
            'type' => 'object',
            'properties' => [
              'location' => ['type' => 'string'],
            ],
          ],
        ],
      ],
    ],
  ]);
\$agent->save();
echo 'Created agent with tools' . PHP_EOL;
"
```

### 2. Test Tool Calling

Test that the provider correctly handles tool calls and responses.

## Continuous Integration

### GitHub Actions / GitLab CI

```yaml
# .gitlab-ci.yml example
test:
  stage: test
  image: drupal:10-php8.3-apache
  services:
    - mysql:8
  variables:
    MYSQL_ROOT_PASSWORD: drupal
    MYSQL_DATABASE: drupal
    OPENROUTER_SKIP_REAL_API: "1"
  script:
    - composer install
    - bash web/modules/contrib/ai_provider_openrouter/scripts/run-all-tests.sh
```

## Troubleshooting

### Tests Fail with "API Key Not Found"

Ensure the key entity exists:

```bash
ddev drush config:get ai_provider_openrouter.settings api_key
ddev drush key:list
```

### Tests Timeout

Increase PHPUnit timeout in `phpunit.xml`:

```xml
<phpunit timeoutForLargeTests="300">
```

### Rate Limit Errors

OpenRouter has rate limits. If tests fail with 429 errors:
1. Wait a few minutes
2. Use a different API key
3. Run tests individually instead of all at once

### Streaming Tests Fail

Streaming requires:
1. Real API key
2. Legacy assistant (no `ai_agent` set)
3. Network connectivity

## Coverage Goals

**Current Coverage:** ~70% (with new tests)

**Target Coverage:** 80%+

**Remaining Gaps:**
- Mocked HTTP responses for unit tests
- Tool calling with complex schemas
- Structured JSON output validation
- Multimodal input testing (images, PDFs, videos)
- Reasoning effort parameter testing
- Concurrent request handling
- Performance benchmarks

## Contributing Tests

When adding new features, please include:
1. Unit tests for service logic
2. Kernel tests for provider operations
3. Functional tests for UI integration
4. Update this guide with test scenarios

## Resources

- **PHPUnit Docs:** https://phpunit.de/documentation.html
- **Drupal Testing:** https://www.drupal.org/docs/automated-testing
- **OpenRouter API:** https://openrouter.ai/docs
- **AI Module Tests:** `web/modules/contrib/ai/tests/`
