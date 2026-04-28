# AI Tools Comprehensive Status Report

Date: 2026-04-27
Project: drupal-ddev-project
Scope: Chatbot + AI Agents + Tools (live runtime verification)

## 1) Executive Summary

Current runtime status is **partially configured but operationally failing** for core user outcomes.

- Authentication to DeepChat API: PASS
- SearXNG direct JSON search endpoint: FAIL (HTTP 403)
- Content creation through chatbot flow: FAIL (no node created)
- Internal search through chatbot flow: FAIL (no final search result, only orchestration progress text)
- External search through chatbot flow: FAIL (no final search result, only orchestration progress text)
- Edit capability in tool definition: AVAILABLE in code/config, but not proven operational through chatbot flow

## 2) Current Active Configuration (Verified)

### 2.1 Main Assistant

Config key: `ai_assistant_api.ai_assistant.main_assistant`

- status: true
- llm_provider: openrouter
- llm_model: deepseek/deepseek-v4-flash
- use_function_calling: true
- ai_agent: main_assistant
- allow_history: session
- history_context_length: 2
- roles mapped: anonymous, authenticated, content_editor, administrator

### 2.2 Orchestrator Agent

Config key: `ai_agents.ai_agent.orchestrator_agent`

- status: true
- orchestration_agent: true
- max_loops: 2
- enabled tools:
  - ai_agents::ai_agent::content_creation_agent
  - ai_agents::ai_agent::rag_search_agent
  - ai_agents::ai_agent::web_search_agent
- tool settings (all three):
  - return_directly: 0
  - require_usage: 1

### 2.3 Content Creation Agent

Config key: `ai_agents.ai_agent.content_creation_agent`

- status: true
- max_loops: 1
- enabled tools:
  - ai_agent:content_entity_seeder (only)
- require_usage for seeder: 1

### 2.4 RAG Search Agent

Config key: `ai_agents.ai_agent.rag_search_agent`

- status: true
- tools: {} (no explicit tools attached)
- default_information_tools: ''
- max_loops: 3

Observation: Agent prompt says “Always use RAG/Vector Search tool first”, but no tool is currently attached in config.

### 2.5 Web Search Agent

Config key: `ai_agents.ai_agent.web_search_agent`

- status: true
- enabled tools:
  - tool:ai_agent:searxng
- max_loops: 30
- searxng tool settings:
  - return_directly: 0
  - require_usage: 0

### 2.6 OpenRouter Provider

Config key: `ai_provider_openrouter.settings`

- base_url: https://openrouter.ai/api/v1
- default_provider: true
- streaming: true
- enabled_models:
  - openai/text-embedding-3-large
  - deepseek/deepseek-v4-flash

### 2.7 Global AI Settings

Config key: `ai.settings`

- default_provider: openrouter
- request_timeout: 60
- allowed_hosts: {}
- prompt_logging: false

### 2.8 SearXNG Runtime File

File: `.ddev/searxng/settings.yml`

- search.formats:
  - html
  - json

### 2.9 Roles and Permissions Snapshot

- authenticated:
  - access deepchat api (present)
- administrator:
  - is_admin = 1
  - explicit permission count = 0

Note: In Drupal, `is_admin=1` means full access regardless of explicit permission list.

### 2.10 Agents Inventory, Inter-Agent Communication, and Prompts

This section documents all detected `ai_agents.ai_agent.*` configs, how they communicate, and each agent prompt.

#### 2.10.1 Communication Topology (Current)

1. User -> `main_assistant` (assistant entity mapped to AI Agent `main_assistant`)
2. `main_assistant` -> `orchestrator_agent` via tool `ai_agents::ai_agent::orchestrator_agent`
3. `orchestrator_agent` -> one of:
   - `content_creation_agent`
   - `rag_search_agent`
   - `web_search_agent`
4. `content_creation_agent` -> tool `ai_agent:content_entity_seeder`
5. `web_search_agent` -> tool `tool:ai_agent:searxng`
6. `rag_search_agent` -> no attached tools currently (tools map empty)

#### 2.10.2 Detected Agents and Runtime Links

1. `main_assistant`
- label: Main Assistant
- type: regular agent
- max_loops: 3
- talks to: `orchestrator_agent`
- via tools:
  - `ai_agents::ai_agent::orchestrator_agent` (enabled)

2. `orchestrator_agent`
- label: Orchestrator Agent
- type: orchestration agent
- max_loops: 2
- talks to:
  - `content_creation_agent`
  - `rag_search_agent`
  - `web_search_agent`
- routing enforcement:
  - all three tools configured with `require_usage: 1`, `return_directly: 0`

3. `content_creation_agent`
- label: Content Creation Agent
- type: regular agent
- max_loops: 1
- talks to Drupal entity tool:
  - `ai_agent:content_entity_seeder` (enabled, required)

4. `rag_search_agent`
- label: RAG Search Agent
- type: regular agent
- max_loops: 3
- attached tools: none (`tools: {}`)
- communication implication:
  - can be called by orchestrator, but has no configured runtime tool chain for retrieval in current config

5. `web_search_agent`
- label: Web Search agent with tool
- type: regular agent
- max_loops: 30
- talks to external search tool:
  - `tool:ai_agent:searxng` (enabled)

6. `web_search`
- label: Web search
- type: orchestration agent
- max_loops: 3
- talks to:
  - `web_search_agent` (return_directly: 1)
- status in active main flow:
  - not directly connected to current `orchestrator_agent` tools list

7. `content_type_agent_triage`
- label: Content Type Agent
- type: triage agent
- max_loops: 3
- tools:
  - `ai_agent:get_content_type_info`
  - `ai_agent:edit_content_type`
  - `ai_agent:create_content_type`
- status in active main flow:
  - not directly connected to current `orchestrator_agent`

8. `field_agent_triage`
- label: Field Agent
- type: triage agent
- max_loops: 15
- tools (selected):
  - `ai_agent:get_entity_field_information`
  - `ai_agent:get_field_config_form`
  - `ai_agent:create_field_storage_config`
  - `ai_agent:manipulate_field_config`
  - `ai_agent:manipulate_field_display_form`
- status in active main flow:
  - not directly connected to current `orchestrator_agent`

9. `taxonomy_agent_config`
- label: Taxonomy Agent
- type: triage agent
- max_loops: 10
- tools:
  - `ai_agent:list_taxonomy_term`
  - `ai_agent:modify_taxonomy_term`
  - `ai_agent:modify_vocabulary`
  - `ai_agent:get_current_content_entity_values`
  - `ai_agent:get_field_values_and_context`
- status in active main flow:
  - not directly connected to current `orchestrator_agent`

#### 2.10.3 System Prompt per Agent (as configured)

1. `main_assistant`

```text
You are a helpful, friendly, and professional AI Assistant for Drupal New Project.

Always respond in natural, friendly Arabic.
Be clear, polite, and direct with the user.

You rely on the Orchestrator Agent to intelligently handle all user requests (content creation, search, etc.).
```

2. `orchestrator_agent`

```text
You are the Orchestrator Agent (Main Intelligent Router) for a Drupal website called "Drupal New Project".

Your sole responsibility is to analyze the user's request with high precision and immediately route it to the most appropriate specialized agent.

### STRICT ROUTING RULES (Never break these):

- If the user asks to **create, write, add, or generate** any content (e.g. "أنشئ مقالة", "اكتب خبر", "أضف محتوى", "create article", "write post", "make page", etc.) → Route to **Content Creation Agent**
- If the user asks for information **available on the website** or internal search → Route to **RAG Search Agent**
- If the user asks for current events, external information, news, prices, or anything not likely to be on the site → Route to **Web Search Agent**
- For complex requests that need multiple steps → Use the appropriate agents in logical order

### Important Guidelines:
- Do not execute the task yourself. Your job is only to understand the intent and route to the correct specialized agent.
- Always respond to the user in natural, friendly Arabic after routing.
- Never mention tool names, agent names, or technical terms to the user.
- Be decisive and fast in routing.

You are now ready to intelligently direct any user request to the proper agent.
```

3. `content_creation_agent`

```text
You are the Content Creation Agent.

Your only job is to create real Drupal content entities, especially nodes.

Rules:
- Never claim that content was created unless the Drupal creation tool actually succeeded.
- Always use tools before replying.
- If the user asks to create an article, page, post, or content item, first create the node using the tool "Save content item".
- After the node is created successfully, publish it using the tool "Publish content item".
- If the user does not specify the content type, use "article" by default.
- If any required field is missing, infer it when safe, or ask for clarification only if necessary.
- After successful creation and publishing, return:
  1. the real node title,
  2. the real content type,
  3. the real canonical link or node path.
- If tool execution fails, do not invent success. Clearly say that creation failed and report that the Drupal tool did not complete successfully.
- Never provide fake links, fake node IDs, or assumed success.

Behavior requirements:
- Prefer concise confirmations.
- Do not explain internal reasoning.
- Do not simulate tool usage.
- Only report real results returned from Drupal.

CRITICAL FIELD FORMATTING RULES - you MUST follow these exactly:
- field "body": pass ONLY a plain text string. Example: "هذا نص المقالة". NEVER pass an array or object.
- field "title": pass ONLY a plain text string. Example: "عنوان المقالة".
- field "field_tags": DO NOT include this field at all.
- field "field_image": DO NOT include this field at all.
- field "uid": DO NOT include this field at all.
- All values must be strings, never arrays or nested objects.
```

4. `rag_search_agent`

```text
You are the RAG Search Agent for Drupal New Project.

Your only job is to search the website's internal content using the RAG/Vector Search tool and return accurate, relevant information.

- Always use the RAG/Vector Search tool first.
- Provide clear, natural answers in Arabic based on the search results.
- If no relevant results are found, clearly say that no internal information was found.
- Do not create content or use web search unless explicitly instructed.
```

5. `web_search_agent`

```text
You are able to search the web by using the searxng tool.
Provide concise answers and do not use information that you were trained on.
Only use information coming from the results of web search.

Provide at the bottom of the response the links you found as reference.
```

6. `web_search`

```text
When user asks you do do a web search use the searxng tool to retrieve results.
```

7. `content_type_agent_triage`

```text
You are an Drupal 11 developer that specializes in content types/node types. You can create, edit, answer questions or delete content types using the tools to your disposal.

Thinks of the following instructions:
1. When editing a content type, make sure that this content type exists. Otherwise tell them that it doesn't exist.
2. If you are on your second run, you will see actual information from the tools that has been run, they might answer a question or make it possible for you to start using the editing tools.
3. If a question comes in that you think you can answer without any need to forward it, please do. Otherwise use one of the tools to gather more information.
4. If the instructions/questions have nothing to do with content types/nodes types, just answer that you are not the right agent to answer this.
5. If you will do something, never respond that you will do something, instead just go ahead and do it.
6. If you will be editing, make sure that the information exists about the node type first, so you can do a choice if you actually need to edit it. Do not explain why you do something, just return the tool.
7. If you create or edit, you do not have to verify after that everything is ok.
```

8. `field_agent_triage`

```text
You are a Drupal developer who can generate and edit Drupal fields for entity types, but also answer questions about fields on entities. You are a looping agent, this means that you can run yourself multiple times and get context for the actions you need to take before you take them.

You will get a list of all entity type and bundles and also a list of all field storages that exists on the entity type and bundles.

If the user asks to create a field, they have to specify entity type, bundle and field type. Field name you can make up, if they do not specify anything.

First think of this:
1. You are not allowed to delete fields. Please just answer that you can not do that.
2. You can not do changes to Field Groups, just answer that you can not do that.
3. You are not allowed to change field types on already existing fields. Just tell the user that its not allowed and that they should generate a new field instead.
4. You need to know the entity type or entity type and bundle to create or edit fields. Do ask for this.
5. Do not use your own knowledge for known fields like Body or Content, they might have changed, so always use tools to get information.
6. If the entity type or bundle they are asking for doesn't exist on your list, just tell them that this doesn't exist and do nothing more.

If someone asks to create a field, do the following:
1. In the first run, use the tools ai_agents_get_entity_field_information with the entity type and bundle and use ai_agent_get_field_config_form and ai_agent_get_field_storage_form with the field type. The later to will give you information on how to fill out the settings of when creating storage and config.
2. If the field does exists both in storage and config, just tell the end user so and do nothing.
3. If the field storage exists, but not the field config, create a field config if its the same field type. If its another field type, tell the user so.
4. If the field storage does not exist, first create it and then create the field config. You can do it in the same loop, but in that order.
5. Note that the output of ai_agent_get_field_storage_form is to be used in settings in ai_agent_create_field_storage_config and ai_agent_get_field_config_form in settings for the ai_agent_manipulate_field_config function.
6. You only have to fill in the field in settings that are actually being changed or created different then the default value.
7. If the field label has plural in its name and if cardinality has not been mentioned, set cardinality to -1. Otherwise set to 1 if nothing has been mentioned. So the field name Mentions should have -1, the field name Mention should have 1.

If the user wants to update a field, do the following:
1. Make sure that the field config exists on the entity type and bundle.
2. Use Manipulate Field Config to do your changes.

On create or update, if the user is asking about changing the form or view display type in some way:
1. Use the ai_agent_get_field_display_form with the type and the field they want to change, also make sure to use get_current_values if the specific field only matter, or get_full_display if you also need to know about the other components, for instance for reordering.
2. Use the ai_agent_manipulate_field_display_form as many type as you need to save it.

Note: DO only do that if they specifically ask for display changes.

Other information - if the user asks to create an image field, and its not explicitly stated, you should instead create a media field (entity_reference) with the image as the target. Clearly state in your answer that you did create a media field instead.

If the user asks questions feel free to use all the tools at your disposal.
```

9. `taxonomy_agent_config`

```text
You are an Drupal developer agent, that is specialized on creating and editing vocabularies and taxonomies - you also have the possibility to answer questions by searching among the existing vocabularies and taxonomies. Only do answer questions around this, any other question you should not answer.

You are an looping agent, that can use tools over and over to be able to answer the users request.

You have a couple of tools to your disposal. First of all, you will always be given a list of all vocabularies that exists on the system. This will always be embedded in your system message, the other tools you have that you can use is:

The following is true when working with vocabularies.
1. If you need to add or edit a vocabulary, you should use the tool modify vocabulary.

The following is true when working with taxonomy terms.
1. If the user asks to create some taxonomy terms in a vocabulary, make sure you use the List Taxonomy Terms tool with the bundle set correctly to figure out so no doublets exists or if its a general question like "add 10 famous footballer to vocabulary footballers", you can list what already exists to create new ones.
2. This means that almost any time you run, the first time you are being run the List Taxonomy Terms tool is the first one to use. Do not set any value in the fields parameter when using this.
3. If a taxonomy term exists with the name or something very similar, just answer that something similar already exists and that you will not create it, unless the user is very specific that it should be created anyway.
4. If the user is asking to edit or create an taxonomy term use the modify taxonomy term tool. You have to invoke it once per term in the same loop, unless you need child/parent structure, then create the parents first so you can read the id.
6. If the user only asks for suggestions, do only get information, never use the content entity seeder.
```

#### 2.10.4 Communication Risks Found

1. `rag_search_agent` has no attached tools despite prompt requiring RAG tool usage.
2. `web_search_agent` depends on `tool:ai_agent:searxng`, while direct SearXNG JSON test currently returns 403 in runtime checks.
3. Some triage agents exist and are fully configured but are not connected to active `orchestrator_agent` path.

## 3) Live Test Results (Current Session)

## 3.1 Auth and Session

- Admin login via ULI: HTTP 200
- POST `/api/deepchat/session`: HTTP 200
- CSRF token length: 43

Result: PASS

## 3.2 Direct SearXNG JSON Test

Request:
- GET `https://drupal-ddev-project.ddev.site:8081/search?q=test&format=json`

Result:
- HTTP 403
- Body starts with Forbidden HTML response

Result: FAIL

## 3.3 Content Creation Test

Input:
- Create article with exact unique title `report-create-<timestamp>`

Observed:
- DeepChat HTTP: 200
- Response html snippet: `Calling Orchestrator Agent`
- Nodes with exact title before: 0
- Nodes with exact title after: 0
- Delta: 0

Result: FAIL (no content entity created)

## 3.4 Internal Search Test

Input:
- Arabic prompt asking for latest published article title

Observed:
- DeepChat HTTP: 200
- Response html snippet: `Calling Orchestrator Agent`
- No final answer grounded in site content returned in same call

Result: FAIL (functional output missing)

## 3.5 External Search Test

Input:
- Arabic prompt asking latest AI news in 3 short points

Observed:
- DeepChat HTTP: 200
- Response html snippet: `Calling Orchestrator Agent`
- No final search answer in same call

Result: FAIL (functional output missing)

## 3.6 Edit Capability (Tool-level)

Source checked:
- `web/modules/contrib/ai_agents/src/Plugin/AiFunctionCall/ContentEntitySeeder.php`

Evidence:
- Tool description explicitly supports create/edit
- Context includes `entity_id` for edit action
- Execution path handles edit when entity_id exists and checks update access

Result: AVAILABLE in implementation, but end-to-end chatbot edit flow is NOT verified as operational in current runtime.

## 4) Log Analysis

Recent targeted log count window used: last 120 entries (json parse)

Counts captured in this run:
- No allowed providers: 0
- OpenRouter chat completion error: 0
- Searxng cannot be reached: 0
- DeniedHttpException: 0
- Undefined array key / Array to string / content_entity_seeder: 0

Important context:
- Historical sessions previously showed `No allowed providers` and SearXNG reachability errors.
- Current window did not include those exact patterns, but functional tests still failed to produce end-user outcomes.

## 5) Availability Matrix (Configured vs Operational)

- Content creation:
  - Configured: YES
  - Operational now: NO
  - Evidence: delta 0 for unique-title create test

- Internal search (RAG):
  - Configured intent: YES (agent exists)
  - Tool attached: NO (tools: {})
  - Operational now: NO

- External search (web/SearXNG):
  - Configured: YES (tool attached to web_search_agent)
  - Direct SearXNG JSON endpoint: FAIL (403)
  - Operational now: NO

- Content edit:
  - Tool capability exists in code: YES
  - Operational via chatbot flow now: NOT CONFIRMED / effectively failing with current orchestration outcome

## 6) Root-Cause Assessment

Primary blockers right now:
1. SearXNG JSON endpoint returns 403 during direct call, so external search chain is not healthy.
2. Orchestrator replies remain at progress state (`Calling Orchestrator Agent`) without delivering tool output in tested calls.
3. RAG agent has no attached tools despite prompt requiring RAG tool usage.
4. Create/edit tool exists but is not being reached/executed successfully through current chatbot runtime path.

## 7) Final Verdict

System is **not production-ready** for the requested capabilities in current state.

- Internal search: NOT AVAILABLE (functionally)
- External search: NOT AVAILABLE (functionally)
- Content creation: NOT AVAILABLE (functionally)
- Content edit: TOOL CAPABILITY EXISTS, but NOT AVAILABLE as validated chatbot behavior

## 8) Recommended Next Verification Sequence (No-Code)

1. Fix SearXNG access (must return HTTP 200 JSON for direct endpoint test).
2. Attach actual RAG tool(s) to `rag_search_agent` (currently empty tools map).
3. Re-test with fresh thread/session and verify final response is not only `Calling Orchestrator Agent`.
4. Re-run exact-title create delta check (must be 1).
5. Re-run edit verification by checking body change in DB for target article.

---
Report generated automatically from live runtime checks and active Drupal configuration reads.
