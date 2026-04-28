# AI Provider OpenRouter - Comprehensive Review & Test Plan

**Date:** 2026-02-28  
**Module Version:** 1.1.2  
**AI Module Version:** 1.2.10  
**Production Sites:** ~30 sites  
**Status:** Active contrib module on drupal.org

---

## Executive Summary

The `ai_provider_openrouter` module is a production-ready AI provider plugin that integrates OpenRouter's API with Drupal's AI framework. It supports chat completions, embeddings, and text-to-image generation across hundreds of models from multiple providers (OpenAI, Anthropic, Google, Mistral, etc.).

### Critical Findings

1. **MISSING METHODS**: `getMaxInputTokens()` and `getMaxOutputTokens()` are NOT implemented in `OpenRouterProvider`
   - These are required by `ChatInterface` (added in AI module 1.2.x)
   - The `ChatTrait` provides defaults (1024 tokens) but these are inadequate for modern LLMs
   - **Impact:** Model configuration UI may show incorrect token limits; context window management broken

2. **Interface Compliance**: Module implements 3 operation types correctly:
   - ✅ `ChatInterface` (streaming supported)
   - ✅ `EmbeddingsInterface` (batch processing supported)
   - ✅ `TextToImageInterface` (multimodal support)

3. **Recent AI Module Changes** (1.2.6 - 1.2.10):
   - Token usage tracking improvements
   - Streaming enhancements
   - Model capability detection
   - **Provider must implement `getMaxInputTokens()` and `getMaxOutputTokens()`**

---

## Module Architecture

### Core Components

1. **OpenRouterProvider** (`src/Plugin/AiProvider/OpenRouterProvider.php`)
   - Main provider plugin implementing ChatInterface, EmbeddingsInterface, TextToImageInterface
   - Uses `ChatTrait` for base chat functionality
   - Handles streaming via `OpenRouterStreamedChatMessageIterator`
   - Supports tools/function calling, structured JSON output
   - Multimodal support (images, PDFs, videos)

2. **OpenRouterClient** (`src/Service/OpenRouterClient.php`)
   - Wraps `openai-php/client` library (OpenRouter API is OpenAI-compatible)
   - Handles authentication via Key module
   - Fetches models from `/models` and `/embeddings/models` endpoints
   - Manages streaming and non-streaming requests

3. **OpenRouterStreamedChatMessageIterator** (`src/OperationType/Chat/OpenRouterStreamedChatMessageIterator.php`)
   - Implements `StreamedChatMessageIteratorInterface`
   - Handles Server-Sent Events (SSE) for real-time token streaming
   - Includes usage metrics in streamed chunks

4. **OpenRouterConfigForm** (`src/Form/OpenRouterConfigForm.php`)
   - Configuration UI for API key, base URL, model filtering
   - Allows admins to enable/disable specific models
   - Organizes models by provider with pricing info

### Supported Features

**Chat Operations:**
- ✅ Simple text chat
- ✅ Multi-turn conversations
- ✅ System prompts (with o1/o3 workaround)
- ✅ Streaming (SSE for legacy assistants)
- ✅ Tools/function calling
- ✅ Structured JSON output
- ✅ Multimodal inputs (images, PDFs, videos via `getFiles()`)
- ✅ Reasoning effort parameter (o1/o3/gpt-5 models)
- ✅ Token usage tracking

**Embeddings Operations:**
- ✅ Single string embeddings
- ✅ Batch embeddings (array of strings)
- ✅ 21+ embedding models supported
- ✅ Vector dimensions mapped (384-3072)
- ✅ Model filtering by operation type

**Text-to-Image Operations:**
- ✅ Image generation via chat completions API
- ✅ Multimodal response handling
- ✅ Base64 image decoding
- ✅ Multiple image formats (PNG, JPEG, WebP)

**Model Management:**
- ✅ Dynamic model fetching from OpenRouter
- ✅ Model filtering by admin configuration
- ✅ Separate chat/embedding model lists
- ✅ Provider-organized model selection UI

---

## Current Test Coverage

### Unit Tests

**OpenRouterClientTest** (`tests/src/Unit/OpenRouterClientTest.php`)
- ✅ Client instantiation with config and key
- ❌ NO tests for actual API calls (mocked or real)
- ❌ NO tests for error handling
- ❌ NO tests for streaming

**Coverage:** ~5% of client functionality

### Kernel Tests

**OpenRouterProviderKernelTest** (`tests/src/Kernel/OpenRouterProviderKernelTest.php`)
- ✅ Provider plugin registration/discovery
- ❌ NO tests for chat operations
- ❌ NO tests for embeddings operations
- ❌ NO tests for text-to-image operations
- ❌ NO tests for streaming
- ❌ NO tests for model listing
- ❌ NO tests for configuration

**OpenRouterProviderModelFilterKernelTest** (exists but not reviewed yet)
- Likely tests model filtering logic

**Coverage:** ~10% of provider functionality

### Functional Tests

**None exist** - No browser/integration tests

**Overall Test Coverage: ~10-15%**

---

## Missing Method Implementations

### CRITICAL: Token Limit Methods

```php
// Required by ChatInterface but NOT implemented in OpenRouterProvider
public function getMaxInputTokens(string $model_id): int;
public function getMaxOutputTokens(string $model_id): int;
```

**Current Behavior:**
- Falls back to `ChatTrait` defaults (1024 tokens for both)
- Incorrect for all modern models (GPT-4: 128k input, Claude 3.5: 200k input, etc.)

**Required Implementation:**
- Fetch model metadata from OpenRouter API
- Cache model context windows
- Return accurate limits per model
- Handle unknown models gracefully

**Suggested Approach:**
```php
public function getMaxInputTokens(string $model_id): int {
  $models = $this->client->listModels();
  if (isset($models[$model_id]['context_length'])) {
    return (int) $models[$model_id]['context_length'];
  }
  // Fallback for unknown models
  return 8192;
}

public function getMaxOutputTokens(string $model_id): int {
  $models = $this->client->listModels();
  // Most models reserve ~10-20% of context for output
  // OpenRouter may provide max_completion_tokens in model metadata
  if (isset($models[$model_id]['top_provider']['max_completion_tokens'])) {
    return (int) $models[$model_id]['top_provider']['max_completion_tokens'];
  }
  // Conservative fallback: 25% of input tokens
  return (int) ($this->getMaxInputTokens($model_id) * 0.25);
}
```

---

## Comprehensive Test Plan

### Phase 1: Unit Tests (Expand Coverage)

**OpenRouterClient Tests:**
1. ✅ Client initialization (exists)
2. ❌ `chatCompletion()` with mocked HTTP responses
3. ❌ `chatCompletionStream()` with mocked SSE stream
4. ❌ `embeddings()` single string
5. ❌ `embeddings()` batch array
6. ❌ `listModels()` merging chat + embedding models
7. ❌ Error handling (rate limits, quota, unsafe content)
8. ❌ API key validation
9. ❌ Base URL configuration

**OpenRouterStreamedChatMessageIterator Tests:**
1. ❌ SSE chunk parsing
2. ❌ Token accumulation
3. ❌ Usage metrics extraction
4. ❌ Finish reason handling
5. ❌ Error chunk handling

### Phase 2: Kernel Tests (Core Functionality)

**Provider Registration & Configuration:**
1. ✅ Plugin discovery (exists)
2. ❌ Provider capabilities detection
3. ❌ Supported operation types
4. ❌ `isUsable()` with/without API key
5. ❌ Configuration form submission
6. ❌ Model filtering logic

**Chat Operations:**
1. ❌ Simple text chat (mocked API)
2. ❌ Multi-turn conversation
3. ❌ System prompt handling
4. ❌ o1/o3 system prompt workaround
5. ❌ Streaming chat (SSE)
6. ❌ Non-streaming chat
7. ❌ Tools/function calling
8. ❌ Structured JSON output
9. ❌ Multimodal inputs (images, PDFs, videos)
10. ❌ Token usage tracking
11. ❌ Reasoning effort parameter
12. ❌ **getMaxInputTokens() implementation**
13. ❌ **getMaxOutputTokens() implementation**

**Embeddings Operations:**
1. ❌ Single string embedding
2. ❌ Batch embeddings (array)
3. ❌ `maxEmbeddingsInput()` limits
4. ❌ `embeddingsVectorSize()` for all 21 models
5. ❌ Model filtering (embeddings-only models)

**Text-to-Image Operations:**
1. ❌ Simple text-to-image
2. ❌ Image format handling (PNG, JPEG, WebP)
3. ❌ Base64 decoding
4. ❌ Multiple images in response
5. ❌ Error handling (no images generated)

**Model Management:**
1. ❌ `getConfiguredModels()` with no filters
2. ❌ `getConfiguredModels()` with enabled models
3. ❌ `getConfiguredModels()` filtered by operation type
4. ❌ Model metadata caching
5. ❌ Embedding model detection (`_is_embedding_model` flag)

**Error Handling:**
1. ❌ Rate limit exceptions
2. ❌ Quota exceptions
3. ❌ Unsafe prompt exceptions
4. ❌ General API errors
5. ❌ Network failures
6. ❌ Invalid model IDs

### Phase 3: Integration Tests (Functional)

**End-to-End Workflows:**
1. ❌ Install module → configure API key → test chat
2. ❌ Enable specific models → verify dropdown filtering
3. ❌ Create AI assistant → test streaming chat
4. ❌ Create AI assistant with agent → verify non-streaming
5. ❌ Embeddings for search/recommendations
6. ❌ Image generation workflow
7. ❌ Multi-user permissions (administer vs. use)

**DeepChat Integration:**
1. ❌ Legacy assistant streaming
2. ❌ Agent-based assistant non-streaming
3. ❌ Stream flag detection
4. ❌ SSE response format
5. ❌ Error handling in UI

**AI Agents Integration:**
1. ❌ Tools/function calling with agents
2. ❌ Structured output with agents
3. ❌ Agent orchestration compatibility

### Phase 4: Performance & Edge Cases

1. ❌ Large context windows (100k+ tokens)
2. ❌ Batch embeddings (100+ strings)
3. ❌ Concurrent requests
4. ❌ API timeout handling
5. ❌ Model list caching
6. ❌ Streaming with slow connections
7. ❌ Unicode/emoji handling
8. ❌ Very long prompts (near token limit)

### Phase 5: Regression Tests

1. ❌ Backward compatibility with AI module 1.2.0-1.2.6
2. ❌ Upgrade path from 1.1.1 → 1.1.2
3. ❌ Config schema validation
4. ❌ No breaking changes in provider API

---

## Test Implementation Priority

### P0 - Critical (Blocking Production Issues)
1. **Implement `getMaxInputTokens()` and `getMaxOutputTokens()`**
2. Test chat operations (basic text chat)
3. Test embeddings operations (single + batch)
4. Test error handling (rate limits, quota, API errors)
5. Test model filtering logic

### P1 - High (Core Features)
1. Test streaming chat (SSE)
2. Test tools/function calling
3. Test multimodal inputs
4. Test text-to-image generation
5. Test model listing and caching
6. Test token usage tracking

### P2 - Medium (Advanced Features)
1. Test structured JSON output
2. Test reasoning effort parameter
3. Test o1/o3 system prompt workaround
4. Test DeepChat integration
5. Test AI agents compatibility

### P3 - Low (Edge Cases & Performance)
1. Test large context windows
2. Test concurrent requests
3. Test Unicode handling
4. Test backward compatibility
5. Test upgrade paths

---

## Recommended Next Steps

1. **Immediate (Today):**
   - Implement `getMaxInputTokens()` and `getMaxOutputTokens()` methods
   - Write kernel tests for these methods
   - Verify against OpenRouter model metadata

2. **Short-term (This Week):**
   - Expand unit tests for `OpenRouterClient` (mocked API calls)
   - Add kernel tests for chat, embeddings, text-to-image operations
   - Test error handling paths

3. **Medium-term (Next 2 Weeks):**
   - Add functional tests for end-to-end workflows
   - Test DeepChat streaming integration
   - Test AI agents compatibility
   - Performance testing with large contexts

4. **Long-term (Next Month):**
   - Achieve 80%+ test coverage
   - Document all test scenarios
   - Set up CI/CD for automated testing
   - Create test fixtures for common use cases

---

## Questions for User

1. **API Access:** Do we have a test OpenRouter API key for running real API tests (non-mocked)?
2. **Test Environment:** Should tests use mocked responses or hit the real OpenRouter API?
3. **Coverage Goals:** What's the target test coverage percentage? (Recommend 80%+)
4. **CI/CD:** Should we set up automated testing in GitLab CI or similar?
5. **Breaking Changes:** Are there any known issues reported by the 30 production sites?
6. **AI Module Compatibility:** Should we test against AI module 1.2.6-1.2.10 specifically?

---

## Known Issues & Technical Debt

1. **Missing token limit methods** (CRITICAL)
2. Hardcoded model metadata in `embeddingsVectorSize()` - should fetch from API
3. No caching for model lists (fetches on every `getConfiguredModels()` call)
4. No retry logic for transient API failures
5. Limited error context in exceptions (no request/response logging)
6. No metrics/telemetry for API usage
7. DeepChat streaming detection is fragile (relies on request body parsing)
8. No validation of model IDs before API calls
9. No rate limit backoff strategy
10. Test coverage is minimal (~10-15%)

---

## Resources

- **OpenRouter API Docs:** https://openrouter.ai/docs
- **AI Module Docs:** https://www.drupal.org/docs/contributed-modules/ai
- **Module Issue Queue:** https://www.drupal.org/project/issues/ai_provider_openrouter
- **Test Running:** `ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-kernel-tests.sh`
