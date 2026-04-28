# OpenRouter Provider for Drupal AI

## INTRODUCTION

The OpenRouter Provider module integrates the [OpenRouter](https://openrouter.ai) API as a pluggable provider for the [Drupal AI framework](https://www.drupal.org/project/ai). It enables Drupal sites to leverage OpenRouter's large language models for chat completions and embeddings, using a secure and extensible architecture.

**Primary use cases:**
- Add OpenRouter as an AI provider for content generation, chatbots, and smart search
- Use OpenRouter's models for text embeddings (semantic search, recommendations, etc.)
- Provide a drop-in alternative to OpenAI and Anthropic providers, supporting flexible model selection
- Filter and curate available AI models to simplify selection throughout your site

## REQUIREMENTS

- Drupal 10 or 11
- [AI module](https://www.drupal.org/project/ai) (machine name `ai`)
- [Key module](https://www.drupal.org/project/key) for secure API key storage
- PHP 8.1+
- Composer dependency: `openai-php/client`

## INSTALLATION

1. Install via Composer (from your project root):
   ```bash
   composer require openai-php/client
   ```
2. Enable the module:
   - Via the Drupal admin UI, or
   - With Drush: `drush en ai_provider_openrouter`
3. Ensure the required modules (`ai`, `key`) are also enabled.

## CONFIGURATION

1. **API Key:**
   - Go to Configuration → Web services → OpenRouter Provider.
   - Select a Key module entry containing your OpenRouter API key. (You may need to create a new key at `/admin/config/system/keys`.)
2. **API Base URL:**
   - The default is `https://openrouter.ai/api/v1`. Change only if using a custom endpoint.
3. **Model Selection:**
   - Enable specific models you want to use on your site from the hundreds available through OpenRouter.
   - Models are organized by provider (OpenAI, Anthropic, Claude, etc.) for easy selection.
   - If you enable specific models, only those will appear in dropdown selectors throughout your site.
   - If no models are enabled, all available models will be shown (default behavior).
4. **Provider Settings:**
   - The module registers as an AI provider and can be selected/configured via the AI module’s UI.

## MODEL FILTERING

The OpenRouter Provider module allows you to filter and curate the available AI models, making it easier to manage and select models throughout your site. This feature enables you to:

* Enable or disable specific models
* Organize models by provider for easy selection
* Restrict model selection to only enabled models (when at least one model is enabled)
* Show all available models when none are specifically enabled

This feature is particularly useful for large sites with multiple users, as it allows administrators to control which models are available for use. By default, if no models are explicitly enabled, all models will be available to ensure maximum flexibility.

## USAGE

- After configuration, OpenRouter will be available as a provider for AI-powered features (chat, embeddings, etc.) in the AI module.
- You can select models, adjust settings, and use OpenRouter for custom or contributed AI integrations.

## STREAMING

True incremental token streaming (Server-Sent Events) is supported for legacy (non-agent) assistants when used with the AI ecosystem and DeepChat UI.

- Legacy assistants (no `ai_agent` set) can stream tokens in real-time.
- Agent-based assistants (assistants with an `ai_agent` set) currently run through the agent orchestration path, which returns a single non-stream response. This is by design in `ai_assistant_api`/`ai_agents` and is not streamed.

How to use streaming with DeepChat:
- The DeepChat block will send `"stream": true` and expect `Content-Type: text/event-stream` with multiple `data: {"html": "...", "overwrite": true}` lines.
- This module ensures the DeepChat connect attribute contains `stream: true` for legacy assistants, and disables streaming for agent-based assistants to avoid UI hangs.

Verifying streaming:
- Open your browser’s dev tools → Network → XHR/fetch → the DeepChat POST.
  - Request body should include `"stream": true` and an `assistant_id`.
  - Response headers should include `Content-Type: text/event-stream`.
  - Response body should show multiple `data: ...` lines accumulating output.

Converting an assistant to legacy (enable streaming):
- New assistants may auto-create and attach an agent (`ai_agent` config property). To stream, the assistant must be legacy (no agent reference).
- Clear the `ai_agent` property for a given assistant (replace `my_assistant_id`):

```bash
ddev drush php:eval '$s=\Drupal::entityTypeManager()->getStorage("ai_assistant"); $a=$s->load("my_assistant_id"); if($a){ $a->set("ai_agent", ""); $a->save(); echo "Cleared ai_agent\n"; } else { echo "assistant not found\n"; }'
ddev drush cr
```

Notes:
- Streaming is only available for legacy assistants at this time. If you need agents (tools/actions/orchestration), expect a single non-stream JSON response.
- You may keep two assistants: one legacy for streaming UI chats, and one agent-based for tools without streaming.

## TROUBLESHOOTING

- **API Key Issues:**
  - Ensure your API key is valid and stored securely in the Key module.
  - Check permissions on the key entry.
- **Provider Not Available:**
  - Make sure the AI module and this provider are enabled.
  - Clear caches (`drush cr`) after installation/config changes.
- **Errors or Unexpected Results:**
  - Check logs at `/admin/reports/dblog` for details.
  - Review the OpenRouter API documentation for rate limits and model support.
  - **DeepChat shows a spinner and never finishes:**
    - Ensure your assistant is legacy (no `ai_agent` set) when streaming is requested.
    - Confirm the response `Content-Type` is `text/event-stream` (not `application/json`).
    - Confirm the request body contains `"stream": true`.

## TESTS

Kernel tests require a database connection. A convenience script is provided:

```bash
# Run the provider's kernel tests inside DDEV
ddev exec bash web/modules/contrib/ai_provider_openrouter/scripts/run-kernel-tests.sh

# Optional environment variables:
#   TESTSUITE (default: kernel)
#   GROUP (default: ai_provider_openrouter)

# Examples:
ddev exec bash -lc 'TESTSUITE=kernel GROUP=ai_provider_openrouter \
 web/modules/contrib/ai_provider_openrouter/scripts/run-kernel-tests.sh'
```

## ROADMAP / TODO
- Implement additional AI operations (moderation, images, audio, etc.) as supported by OpenRouter.
- Provide Drush commands for admin/testing.
- Expand test coverage (unit, kernel tests).
 - Streaming: Supported for legacy assistants in DeepChat (SSE). Agent-runner streaming TBD.

## CREDITS & MAINTAINERS

Current maintainers:
- Jeff Bruton (bdsweb) - https://www.drupal.org/u/bdsweb

Inspired by/contributions from:
- The Drupal AI module maintainers
- The OpenAI and Anthropic provider modules

## DOCUMENTATION

Additional documentation is available in the `docs/` directory:

- **[AI Module Compatibility](docs/AI_MODULE_COMPATIBILITY.md)** - Compatibility review with AI module versions 1.2.x and 1.3.x
- **[Testing Tool-Calling](docs/TESTING_TOOL_CALLING.md)** - Guide to testing function/tool calling features
- **[Testing Guide](docs/TESTING_GUIDE.md)** - Comprehensive testing documentation
- **[Quick Start Testing](docs/QUICK_START_TESTING.md)** - Quick setup and testing instructions
- **[Comprehensive Review](docs/COMPREHENSIVE_REVIEW.md)** - Full module architecture and feature review

Test scripts are available in the `scripts/` directory:

- `scripts/run-tests.sh` - Main test runner with proper exit codes
- `scripts/test-all-features.php` - Feature verification script
- `scripts/test-tool-calling.php` - Tool-calling test script

## RESOURCES
- [OpenRouter API Docs](https://openrouter.ai/docs/api-reference/overview)
- [Drupal AI Initiative](https://www.drupal.org/project/artificial_intelligence_initiative)
- [Key module documentation](https://www.drupal.org/project/key)
