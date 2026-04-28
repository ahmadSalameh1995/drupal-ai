<?php

/**
 * Comprehensive Chatbot & Agent System Test
 * Run: ddev drush php:script comprehensive_test.php
 */

// ============================================================
// HELPERS
// ============================================================
function section(string $title): void {
  echo "\n" . str_repeat('=', 70) . "\n";
  echo "  $title\n";
  echo str_repeat('=', 70) . "\n";
}

function subsection(string $title): void {
  echo "\n" . str_repeat('-', 50) . "\n";
  echo "  $title\n";
  echo str_repeat('-', 50) . "\n";
}

function result(string $label, $value, bool $ok = TRUE): void {
  $icon = $ok ? '✅' : '❌';
  if (is_bool($value)) $value = $value ? 'true' : 'false';
  if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
  echo "  $icon  $label: $value\n";
}

function info(string $label, $value): void {
  if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
  echo "  ℹ️   $label: $value\n";
}

function warn(string $msg): void {
  echo "  ⚠️   $msg\n";
}

function testAgent(string $agentId, string $prompt, $aiProvider, $agentManager): array {
  try {
    /** @var \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $agent */
    $agent = $agentManager->createInstance($agentId);
    $agentCfg = \Drupal::config("ai_agents.ai_agent.$agentId");
    $assistantCfg = \Drupal::config("ai_assistant_api.ai_assistant.main_assistant");

    $providerId = $assistantCfg->get('llm_provider') ?: 'openrouter';
    $modelId    = $assistantCfg->get('llm_model') ?: 'openai/gpt-4o-mini';

    $msg     = new \Drupal\ai\OperationType\Chat\ChatMessage('user', $prompt);
    $input   = new \Drupal\ai\OperationType\Chat\ChatInput([$msg], []);
    $agent->setChatInput($input);
    $agent->setAiProvider($aiProvider->createInstance($providerId));
    $agent->setModelName($modelId);
    $agent->setCreateDirectly(TRUE);
    $agent->determineSolvability();
    $response = $agent->solve();

    $history = $agent->getChatHistory() ?: [];
    $lastMsg  = end($history);
    $finalText = ($lastMsg && method_exists($lastMsg, 'getText'))
      ? $lastMsg->getText()
      : (is_string($response) ? $response : json_encode($response));

    return ['ok' => TRUE, 'response' => $finalText, 'raw' => $response, 'loops' => count($history)];
  }
  catch (\Throwable $e) {
    return ['ok' => FALSE, 'error' => $e->getMessage()];
  }
}

// ============================================================
// SECTION 1: SYSTEM STATUS
// ============================================================
section('1. SYSTEM STATUS & INFRASTRUCTURE');

// DDEV Services
$searxngOk = FALSE;
try {
  $ch = curl_init('http://searxng:8080/search?q=test&format=json');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 5]);
  $r = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $searxngOk = ($code === 200);
} catch (\Exception $e) {}

result('SearXNG accessible (internal)', $searxngOk, $searxngOk);

// Provider
$providerManager = \Drupal::service('ai.provider');
$providers = $providerManager->getDefinitions();
result('OpenRouter provider registered', isset($providers['openrouter']), isset($providers['openrouter']));

// Agent manager
$agentManager = \Drupal::service('plugin.manager.ai_agents');
$agentDefs = $agentManager->getDefinitions();
result('AI Agents plugin manager available', !empty($agentDefs), !empty($agentDefs));

// AI Search
$searchApi = \Drupal::service('search_api.server_task_manager');
$servers = \Drupal\search_api\Entity\Server::loadMultiple();
foreach ($servers as $srv) {
  $srvOk = $srv->status();
  result('Search API server: ' . $srv->label(), $srvOk ? 'enabled' : 'disabled', $srvOk);
}

// ============================================================
// SECTION 2: AI ASSISTANT CONFIG (Entry Point)
// ============================================================
section('2. AI ASSISTANT — ENTRY POINT (ai_assistant_api.ai_assistant.main_assistant)');

$assistantCfg = \Drupal::config('ai_assistant_api.ai_assistant.main_assistant');
info('ID', $assistantCfg->get('id'));
info('Label', $assistantCfg->get('label'));
info('Provider', $assistantCfg->get('llm_provider'));
info('Model', $assistantCfg->get('llm_model'));
info('use_function_calling', $assistantCfg->get('use_function_calling') ? 'true' : 'false');
info('ai_agent (linked agent)', $assistantCfg->get('ai_agent'));
info('allow_history', $assistantCfg->get('allow_history'));
info('history_context_length', $assistantCfg->get('history_context_length'));
info('Roles allowed', implode(', ', array_keys(array_filter($assistantCfg->get('roles') ?: []))));

echo "\n  [system_prompt] (empty = uses agent's own prompt):\n";
$sp = $assistantCfg->get('system_prompt');
echo "  " . (empty($sp) ? '(empty)' : '"' . substr($sp, 0, 200) . '"') . "\n";

echo "\n  [instructions field]:\n";
$inst = $assistantCfg->get('instructions');
echo "  " . (empty($inst) ? '(empty)' : '"' . $inst . '"') . "\n";

// ============================================================
// SECTION 3: AGENT CHAIN CONFIG
// ============================================================
section('3. AGENT CHAIN — FULL CONFIG');

$agentConfigs = [
  'main_assistant'       => ['level' => 'L1', 'role' => 'User-facing coordinator'],
  'orchestrator_agent'   => ['level' => 'L2', 'role' => 'Intent router'],
  'content_creation_agent' => ['level' => 'L3', 'role' => 'Creates Drupal nodes'],
  'rag_search_agent'     => ['level' => 'L3', 'role' => 'Internal RAG/vector search'],
  'web_search_agent'     => ['level' => 'L3', 'role' => 'External SearXNG search'],
];

foreach ($agentConfigs as $agentId => [$level, $role]) {
  subsection("[$level] $agentId — $role");
  $cfg = \Drupal::config("ai_agents.ai_agent.$agentId");

  info('Label', $cfg->get('label'));
  info('Status', $cfg->get('status') ? 'enabled' : 'disabled');
  info('orchestration_agent', $cfg->get('orchestration_agent') ? 'true' : 'false');
  info('triage_agent', $cfg->get('triage_agent') ? 'true' : 'false');
  info('max_loops', $cfg->get('max_loops'));

  // Tools
  $tools = $cfg->get('tools') ?: [];
  $toolSettings = $cfg->get('tool_settings') ?: [];
  $enabledTools = array_keys(array_filter($tools));
  echo "\n  Tools (" . count($enabledTools) . " enabled):\n";
  foreach ($enabledTools as $toolId) {
    $ts = $toolSettings[$toolId] ?? [];
    $required = ($ts['require_usage'] ?? 0) ? '⚠️  require_usage=1 (FORCED)' : 'require_usage=0 (optional)';
    $direct   = ($ts['return_directly'] ?? 0) ? 'return_directly=1' : 'return_directly=0';
    echo "    • $toolId\n";
    echo "      $required | $direct\n";
  }

  // System prompt preview
  $sp = $cfg->get('system_prompt') ?: '';
  echo "\n  System Prompt (" . strlen($sp) . " chars):\n";
  echo "  " . substr(str_replace("\n", ' ', $sp), 0, 300) . (strlen($sp) > 300 ? '...' : '') . "\n";
}

// ============================================================
// SECTION 4: AGENTRUNNER.PHP CODE ANALYSIS
// ============================================================
section('4. AgentRunner.php — CODE ANALYSIS');

$arFile = \Drupal::root() . '/modules/contrib/ai/modules/ai_assistant_api/src/Service/AgentRunner.php';
if (file_exists($arFile)) {
  $code = file_get_contents($arFile);
  result('File exists', TRUE);

  // Check for null safety
  $hasNullCheck = str_contains($code, 'is_string($response)') || str_contains($code, 'trim($response)');
  result('Null/empty response guard on $response', $hasNullCheck, $hasNullCheck);
  if (!$hasNullCheck) {
    warn('RISK: $response passed directly to new ChatMessage() — if null, will throw TypeError');
  }

  // Check for ChatMessage instanceof guard
  $hasInstanceof = str_contains($code, 'instanceof ChatMessage');
  result('instanceof ChatMessage guard before end($history)', $hasInstanceof, $hasInstanceof);
  if (!$hasInstanceof) {
    warn('RISK: end($history) may return FALSE or non-ChatMessage — no instanceof check');
  }

  // solve() call
  result('$agent->solve() called', str_contains($code, '$agent->solve()'));
  result('determineSolvability() called', str_contains($code, 'determineSolvability()'));
  result('getChatHistory() used', str_contains($code, 'getChatHistory()'));

  // Extract the solve block for display
  preg_match('/\$response = \$agent->solve\(\);.*?return new ChatOutput/s', $code, $m);
  if (!empty($m[0])) {
    echo "\n  [solve() block]:\n";
    $block = str_replace("\n", "\n  ", substr($m[0], 0, 600));
    echo "  $block\n";
  }
} else {
  result('AgentRunner.php found', FALSE, FALSE);
}

// ============================================================
// SECTION 5: LIVE AGENT TESTS
// ============================================================
section('5. LIVE AGENT TESTS (direct, bypassing chatbot UI)');

$aiProvider = \Drupal::service('ai.provider');
$tests = [
  [
    'agent'   => 'main_assistant',
    'name'    => 'Main Assistant — greet',
    'prompt'  => 'مرحبا، ما هي المهام التي يمكنك مساعدتي بها؟',
    'expect'  => 'Arabic greeting/description',
    'check'   => fn($r) => strlen($r) > 20,
  ],
  [
    'agent'   => 'orchestrator_agent',
    'name'    => 'Orchestrator — route to content creation',
    'prompt'  => 'أنشئ مقالة بعنوان: اختبار شامل للنظام',
    'expect'  => 'Routes to content_creation_agent',
    'check'   => fn($r) => strlen($r) > 10,
  ],
  [
    'agent'   => 'orchestrator_agent',
    'name'    => 'Orchestrator — route to internal search',
    'prompt'  => 'ابحث في الموقع عن محتوى حول Drupal',
    'expect'  => 'Routes to rag_search_agent',
    'check'   => fn($r) => strlen($r) > 10,
  ],
  [
    'agent'   => 'orchestrator_agent',
    'name'    => 'Orchestrator — route to web search',
    'prompt'  => 'ابحث في الانترنت عن آخر أخبار الذكاء الاصطناعي',
    'expect'  => 'Routes to web_search_agent → SearXNG',
    'check'   => fn($r) => strlen($r) > 10,
  ],
  [
    'agent'   => 'content_creation_agent',
    'name'    => 'Content Creation — create article',
    'prompt'  => 'أنشئ مقالة بعنوان: اختبار إنشاء محتوى تلقائي ' . date('Y-m-d H:i:s'),
    'expect'  => 'Node created with real ID/path',
    'check'   => fn($r) => strlen($r) > 10,
  ],
  [
    'agent'   => 'rag_search_agent',
    'name'    => 'RAG Search — internal search',
    'prompt'  => 'ابحث عن مقالات في الموقع',
    'expect'  => 'Returns search results or "no results"',
    'check'   => fn($r) => strlen($r) > 10,
  ],
  [
    'agent'   => 'web_search_agent',
    'name'    => 'Web Search — SearXNG',
    'prompt'  => 'ابحث عن: Drupal CMS features 2024',
    'expect'  => 'Web results with links',
    'check'   => fn($r) => strlen($r) > 20,
  ],
];

$passed = 0;
$failed = 0;
$results = [];

foreach ($tests as $test) {
  echo "\n  Testing: {$test['name']}\n";
  echo "  Prompt: {$test['prompt']}\n";
  echo "  Expected: {$test['expect']}\n";

  $t0 = microtime(TRUE);
  $res = testAgent($test['agent'], $test['prompt'], $aiProvider, $agentManager);
  $elapsed = round((microtime(TRUE) - $t0) * 1000);

  if (!$res['ok']) {
    echo "  ❌ FAILED (exception): {$res['error']}\n";
    $failed++;
    $results[] = ['test' => $test['name'], 'status' => 'EXCEPTION', 'detail' => $res['error']];
  } else {
    $response = $res['response'];
    $ok = ($test['check'])($response);
    $status = $ok ? 'PASS' : 'FAIL';
    $icon = $ok ? '✅' : '❌';
    echo "  $icon $status ({$elapsed}ms)\n";
    echo "  Response preview: " . substr(str_replace("\n", ' ', $response), 0, 250) . "\n";
    if ($ok) $passed++; else $failed++;
    $results[] = ['test' => $test['name'], 'status' => $status, 'response_len' => strlen($response), 'ms' => $elapsed];
  }
}

// ============================================================
// SECTION 6: CHATBOT API TEST
// ============================================================
section('6. CHATBOT REST API TEST (HTTP via chatbot endpoint)');

$baseUrl = 'https://drupaldev.ddev.site';
$chatUrl  = $baseUrl . '/api/ai-assistant-api/chat/main_assistant';

// Get CSRF token
$tokenUrl = $baseUrl . '/session/token';
$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => TRUE,
  CURLOPT_TIMEOUT => 10,
  CURLOPT_SSL_VERIFYPEER => FALSE,
  CURLOPT_SSL_VERIFYHOST => FALSE,
]);
$token = curl_exec($ch);
$tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tokenCode === 200 && !empty(trim($token))) {
  result('CSRF token obtained', TRUE);
} else {
  result('CSRF token obtained', FALSE, FALSE);
  warn("Token response code: $tokenCode");
  $token = '';
}

$chatbotTests = [
  ['msg' => 'مرحبا، ما هي مهامك؟', 'label' => 'Greet chatbot'],
  ['msg' => 'أنشئ مقالة بعنوان: اختبار API للشات بوت ' . date('H:i:s'), 'label' => 'Create content via chatbot'],
  ['msg' => 'ابحث في الموقع عن مقالات', 'label' => 'Internal search via chatbot'],
  ['msg' => 'ابحث في الانترنت عن Drupal 11', 'label' => 'Web search via chatbot'],
];

$jobId = uniqid('test_', TRUE);
$chatPassed = 0;
$chatFailed = 0;

foreach ($chatbotTests as $ct) {
  echo "\n  Testing: {$ct['label']}\n";
  echo "  Message: {$ct['msg']}\n";

  $payload = json_encode([
    'message'  => $ct['msg'],
    'job_id'   => $jobId,
    'context'  => [],
  ]);

  $headers = [
    'Content-Type: application/json',
    'Accept: application/json',
  ];
  if (!empty($token)) {
    $headers[] = 'X-CSRF-Token: ' . trim($token);
  }

  $ch = curl_init($chatUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_POST           => TRUE,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => FALSE,
    CURLOPT_SSL_VERIFYHOST => FALSE,
  ]);

  $t0 = microtime(TRUE);
  $raw = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $elapsed = round((microtime(TRUE) - $t0) * 1000);
  curl_close($ch);

  if ($code !== 200) {
    echo "  ❌ HTTP $code ({$elapsed}ms)\n";
    echo "  Raw: " . substr($raw, 0, 300) . "\n";
    $chatFailed++;
    continue;
  }

  $data = json_decode($raw, TRUE);
  $text = $data['response'] ?? $data['message'] ?? $data['text'] ?? $data['output'] ?? NULL;

  if (empty($text)) {
    echo "  ❌ No response text in JSON ({$elapsed}ms)\n";
    echo "  Keys: " . implode(', ', array_keys($data ?: [])) . "\n";
    echo "  Raw: " . substr($raw, 0, 300) . "\n";
    $chatFailed++;
  } else {
    echo "  ✅ PASS HTTP 200 ({$elapsed}ms)\n";
    echo "  Response: " . substr(str_replace("\n", ' ', $text), 0, 250) . "\n";
    $chatPassed++;
  }

  // Brief pause between requests
  usleep(500000);
}

// ============================================================
// SECTION 7: KNOWN ISSUES & DIAGNOSIS
// ============================================================
section('7. KNOWN ISSUES DIAGNOSIS');

// Issue 1: AgentRunner null safety
$arCode = file_exists($arFile) ? file_get_contents($arFile) : '';
$hasNullGuard = str_contains($arCode, 'is_string($response)');
$issue1 = !$hasNullGuard;
echo "\n  Issue #1: AgentRunner.php null response guard\n";
if ($issue1) {
  echo "  ❌ ACTIVE — \$response passed to ChatMessage without null check\n";
  echo "     → If any agent returns null/empty, a TypeError will crash the whole chain\n";
  echo "     → FIX: Add is_string() guard before new ChatMessage('assistant', \$response)\n";
} else {
  echo "  ✅ FIXED — null guard present\n";
}

// Issue 2: require_usage on orchestrator
$mainAgentCfg = \Drupal::config('ai_agents.ai_agent.main_assistant');
$orchToolSettings = $mainAgentCfg->get('tool_settings')['ai_agents::ai_agent::orchestrator_agent'] ?? [];
$orchRequired = ($orchToolSettings['require_usage'] ?? 0);
echo "\n  Issue #2: main_assistant → orchestrator_agent require_usage\n";
if ($orchRequired) {
  echo "  ⚠️  require_usage=1 — orchestrator MUST be called or agent stops\n";
  echo "     → May cause issues if orchestrator is unavailable\n";
} else {
  echo "  ✅ require_usage=0 — orchestrator is optional (AI decides)\n";
  echo "     → RISK: AI may choose NOT to call orchestrator for some prompts\n";
}

// Issue 3: max_loops
$orchCfg = \Drupal::config('ai_agents.ai_agent.orchestrator_agent');
$orchLoops = $orchCfg->get('max_loops');
echo "\n  Issue #3: orchestrator_agent max_loops=$orchLoops\n";
if ($orchLoops < 3) {
  echo "  ⚠️  max_loops=$orchLoops may be too low for complex routing (recommend 3-5)\n";
} else {
  echo "  ✅ max_loops=$orchLoops — sufficient\n";
}

// Issue 4: content_creation_agent max_loops=1
$ccCfg = \Drupal::config('ai_agents.ai_agent.content_creation_agent');
$ccLoops = $ccCfg->get('max_loops');
echo "\n  Issue #4: content_creation_agent max_loops=$ccLoops\n";
if ($ccLoops < 2) {
  echo "  ⚠️  max_loops=1 — only 1 tool call allowed; if seeder fails, no retry\n";
  echo "     → FIX: Set to 2 to allow one retry on failure\n";
} else {
  echo "  ✅ max_loops=$ccLoops\n";
}

// Issue 5: history_context_length
$histLen = $assistantCfg->get('history_context_length');
echo "\n  Issue #5: AI Assistant history_context_length=$histLen\n";
if ((int)$histLen < 3) {
  echo "  ⚠️  Only $histLen messages of history sent — may lose context in multi-turn\n";
} else {
  echo "  ✅ history_context_length=$histLen\n";
}

// ============================================================
// SECTION 8: SUMMARY
// ============================================================
section('8. SUMMARY');

$totalDirect  = count($tests);
$totalChatbot = count($chatbotTests);

echo "\n  Direct Agent Tests:  $passed/$totalDirect passed, $failed/$totalDirect failed\n";
echo "  Chatbot API Tests:   $chatPassed/$totalChatbot passed, $chatFailed/$totalChatbot failed\n";
echo "\n  Result Details:\n";
foreach ($results as $r) {
  $icon = ($r['status'] === 'PASS') ? '✅' : '❌';
  $ms = isset($r['ms']) ? " ({$r['ms']}ms)" : '';
  echo "    $icon [{$r['status']}] {$r['test']}$ms\n";
  if ($r['status'] === 'EXCEPTION') {
    echo "         Error: {$r['detail']}\n";
  }
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "  TEST COMPLETE\n";
echo str_repeat('=', 70) . "\n\n";
