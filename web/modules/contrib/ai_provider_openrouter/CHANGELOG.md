# Changelog

All notable changes to the ai_provider_openrouter module will be documented in this file.

## [1.1.3] - 2026-02-28

### Fixed
- **Critical:** Fix streaming compatibility with AI module 1.3.0 - chatbots now display responses correctly.
  - Provider now checks both `ChatInput->isStreamedOutput()` (new AI 1.3.0 method) and deprecated `$this->streamed` property for backward compatibility.
  - Resolves issue where DeepChat chatbots showed no response in AI 1.3.0-rc1 and latest 1.2.x versions.
- **Critical:** Implement `getMaxInputTokens()` and `getMaxOutputTokens()` methods.
  - Fetches accurate context window limits from OpenRouter API instead of defaulting to 1024 tokens.
  - GPT-4 Turbo now correctly reports 128,000 input tokens; Claude 3.5 Sonnet reports 200,000 tokens.
  - Improves model selection UI, token usage warnings, and cost estimation throughout the AI module.

### Added
- Working test suite with proper exit codes for CI/CD integration.
  - `scripts/run-tests.sh` - Main test runner (exit code 0 on success, 1 on failure).
  - `scripts/test-all-features.php` - Comprehensive feature verification (8 tests covering chat, streaming, embeddings).
  - `scripts/test-tool-calling.php` - Tool-calling/function-calling verification.
- Comprehensive documentation in `docs/` directory:
  - `docs/AI_MODULE_COMPATIBILITY.md` - Full compatibility review with AI module 1.2.x and 1.3.x.
  - `docs/TESTING_TOOL_CALLING.md` - Complete guide to testing function/tool calling features.
- All 8 automated tests pass: provider instantiation, token limits, model listing, chat (string/ChatInput/streaming), embeddings (single/batch).

### Changed
- Organized module directory structure to follow Drupal contrib standards.
  - Moved documentation files to `docs/` directory.
  - Moved test scripts to `scripts/` directory.
  - Removed duplicate `README.txt` (kept `README.md` as standard).
  - Updated `README.md` with links to all documentation and test scripts.

### Compatibility
- Fully compatible with AI module 1.2.0 through 1.3.0-rc2.
- Maintains backward compatibility with deprecated streaming methods while supporting new ChatInput API.
- No database updates required.
- Ready for deployment to production sites running AI 1.2.x or 1.3.x.

### Testing
- Verified streaming works with DeepChat chatbots in AI 1.3.0.
- Verified tool-calling/function-calling works with GPT-4, Claude 3.5, and other compatible models.
- All core features tested: chat, embeddings, text-to-image, streaming, token limits, model listing.

## [1.1.2] - 2026-02-01
- Update minimum `drupal/ai` requirement from ^1.2.0 to ^1.2.7 for security fixes (CVE-2025-13981 XSS vulnerability).
- Add batch processing support to `embeddings()` method - now accepts array of strings for improved performance.
- Add support for `getFiles()` method in chat messages for PDF, video, and file attachments (multimodal support).
- Add `text_to_image` operation type support - enables image generation via OpenRouter (DALL-E, Stable Diffusion, Gemini, etc.).
- Fix embedding models not appearing in model lists - now fetches from `/embeddings/models` endpoint (21+ embedding models).
- Filter models by operation type in `getConfiguredModels()` - embeddings models only show for embeddings operations.
- Add separate "Embedding Models" section to settings form with pricing/description for all 21 embedding models.
- Add 🖼️ icon to indicate models capable of image generation (5 multimodal models) in settings form.
- Add `embeddingsVectorSize()` method with proper dimensions for all 21 embedding models (384-3072 dimensions).
- Add `reasoning_effort` parameter support for o1/o3/gpt-5 reasoning models (none, minimal, low, medium, high, xhigh).
- Maintain backward compatibility for single string embeddings and `getImages()` method.
- No database updates required.

## [1.1.1] - 2025-11-12
- Require `drupal/ai` ^1.2.0 for streaming/event/usage features parity.
- Fix chat string-input normalization (messages are now an array of typed content blocks; removes nested model/messages bug).
- Streaming parity with AI module 1.2:
  - Add `stream_options.include_usage = TRUE` to include token usage in streamed chunks.
  - Refactor `OpenRouterStreamedChatMessageIterator` to implement `doIterate()` (not `getIterator()`), enabling base iterator to reconstruct `ChatOutput`, dispatch events, and run callbacks.
  - Map usage and finish reason on streamed chunks similar to OpenAI provider.
- Non-stream responses now set `TokenUsageDto` on `ChatOutput` when usage is returned.
- No database updates required.

## [1.1.0] - 2025-09-13
- Align with latest AI module interfaces.
- Fix `maxEmbeddingsInput(string $model_id = '')` signature to match `EmbeddingsInterface`.
- Remove unsupported `_provider` requirement from `ai_provider_openrouter.routing.yml`.
- Align config schema: rename `selected_models` to `enabled_models`; add `streaming` and `default_provider` keys.
- Refactor DI usage in `OpenRouterConfigForm` and `OpenRouterProvider` (no `\Drupal::service()` calls).
- Implement model filtering in `getConfiguredModels()` based on `enabled_models` and add Kernel test.
- Fix unit test `OpenRouterClientTest` to pass Guzzle client; add docblocks and void return types.
- Hardening in `OpenRouterClient` (null-safe key fetch, typed arrays) and improved error logging.
- PHPCS cleanup across module; add missing docblocks; make PHPStan level 8 largely clean.
- No database updates required.

## [Unreleased]
- Initial scaffolding: service, config form, schema, provider plugin, tests, documentation.
- Added LICENSE.txt and .gitignore.

## [0.1.0] - 2025-05-16
- First commit: OpenRouter provider plugin for Drupal AI module.
- Configuration form for API key and base URL.
- Service class for OpenRouter API communication.
- Basic unit and kernel tests.
- README with install and usage instructions.
