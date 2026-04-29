# Drupal AI Assistant Project

## 1) Current State (Deep Analysis Snapshot)

This repository contains a Drupal + AI multi-agent setup running inside DDEV.

Verified current state:
- Assistant entrypoint exists and is active: `main_assistant`.
- Assistant is configured to use:
  - provider: `openrouter`
  - model: `openai/gpt-4o`
  - function calling: enabled
  - linked AI agent: `orchestrator_agent`
- Multi-agent routing chain is active:
  - `orchestrator_agent`
  - `content_creation_agent`
  - `rag_search_agent`
  - `web_search_agent`
- Content create/edit works in practice.
- Permanent delete is not implemented as a safe production path (current behavior is not reliable for hard-delete from chat).
- Internal inventory-style prompts (count/all article titles) are currently unstable via chatbot response layer (may return empty text despite available data).

Ground truth data check (direct Drupal query):
- Published article count exists and is non-zero (recent verified count: 55).
- Titles are present in node storage.

## 2) Runtime Architecture

User -> AI Assistant API (`main_assistant`) -> `orchestrator_agent` -> specialized agents/tools.

Main flow:
1. User prompt enters AI Assistant runner.
2. Orchestrator decides route:
   - Greeting/general -> direct Arabic response.
   - Create/edit content -> `content_creation_agent`.
   - Internal search -> `rag_search_agent`.
   - External search -> `web_search_agent`.
3. Tool output is converted to final Arabic response.

## 3) Configuration Snapshot

### 3.1 AI Assistant
Config key: `ai_assistant_api.ai_assistant.main_assistant`

Current important values:
- `llm_provider: openrouter`
- `llm_model: openai/gpt-4o`
- `use_function_calling: true`
- `ai_agent: orchestrator_agent`
- `allow_history: session`
- `history_context_length: 5`

Instructions are English logic with Arabic output requirement.

### 3.2 Orchestrator Agent
Config key: `ai_agents.ai_agent.orchestrator_agent`

Role:
- Central router for all intents.

Current key settings:
- `orchestration_agent: true`
- `max_loops: 5`

Enabled tool references include:
- `ai_agents::ai_agent::content_creation_agent`
- `ai_agents::ai_agent::rag_search_agent`
- `ai_agents::ai_agent::web_search_agent`

Additional inventory tool setting present:
- `ai_agent:list_content_entities` with `require_usage: 1`.

Prompt policy includes:
- Arabic user-facing response.
- Mandatory content inventory rule for article count/title requests.

### 3.3 Content Creation Agent
Config key: `ai_agents.ai_agent.content_creation_agent`

Role:
- Create and edit Drupal content entities.

Current key settings:
- Tool: `ai_agent:content_entity_seeder`
- `require_usage: 1`
- `max_loops: 2`
- `masquerade_roles: [administrator]`

Prompt policy includes strict schema guidance:
- Create article with `label` title.
- Default fields limited to `body` + `status`.
- Edit requires `entity_id`.
- No permanent delete instruction.

### 3.4 RAG Search Agent
Config key: `ai_agents.ai_agent.rag_search_agent`

Role:
- Internal semantic/content search.

Current key settings:
- Tool: `ai_search:rag_search`
- `max_loops: 3`

### 3.5 Web Search Agent
Config key: `ai_agents.ai_agent.web_search_agent`

Role:
- External search via SearXNG tool.

Current key settings:
- Tool: `tool:ai_agent:searxng`
- `max_loops: 3`

## 4) AI Security and Output Filtering

Global config key: `ai.settings`
- `default_provider: openrouter`
- `allowed_hosts: [drupal-ddev-project.ddev.site]`
- `allowed_hosts_rewrite_links: false`

Runtime override in settings file:
- `web/sites/default/settings.php` contains:
  - `$settings["ai_output"]["full_trust_mode"] = TRUE;`

Effect:
- URL/hostname output filtering is effectively relaxed at runtime.

## 5) Search Infrastructure

Search API indexes are enabled:
- `default_index`
- `content_index`

Both were recently confirmed up to date.

## 6) Test Assets Included

Project contains direct and end-to-end validation scripts:
- `chatbot_full_test.php`
- `comprehensive_test.php`
- `chatbot_e2e_test.php`
- `chatbot_service_test.php`

### Recommended quick verification

Run from project root:

```bash
ddev drush cr
ddev drush php:script chatbot_full_test.php
```

For broad diagnosis:

```bash
ddev drush php:script comprehensive_test.php
```

## 7) What Works vs What Is Unstable

Working:
- Greeting and general assistant responses.
- Content creation via chat.
- Content edit via chat (with node id/entity id).
- Internal and external search routes in many scenarios.

Unstable / known issue:
- Inventory prompts such as:
  - "How many published articles do we have?"
  - "Give me all published article titles"
- In current state, chatbot can still return empty text for these prompts even though data exists.
- This appears to be a response assembly/tool-only loop behavior rather than missing content.

## 8) No-Code Operations Guide

### Update prompts/config (no code)
Use Drupal config entities in DB:
- `ai_assistant_api.ai_assistant.main_assistant`
- `ai_agents.ai_agent.orchestrator_agent`
- `ai_agents.ai_agent.content_creation_agent`
- `ai_agents.ai_agent.rag_search_agent`
- `ai_agents.ai_agent.web_search_agent`

After every change:

```bash
ddev drush cr
```

### Re-index internal search (no code)

```bash
ddev drush search-api:index default_index --batch-size=100 -y
ddev drush search-api:index content_index --batch-size=100 -y
```

### Verify assistant config quickly

```bash
ddev drush cget ai_assistant_api.ai_assistant.main_assistant
```

## 9) Repository and Git Status

Local repository was initialized and pushed to:
- `https://github.com/ahmadSalameh1995/drupal-ai.git`

Recent checkpoint commits include:
- `82cb14c3`
- `b2996014`

## 10) Recommended Next Step

If inventory prompts must be production-reliable, apply a focused code-level fix in assistant response handling to guarantee non-empty final text when the model performs tool-only loops.

This is the only remaining gap not reliably solved by no-code tuning alone.
