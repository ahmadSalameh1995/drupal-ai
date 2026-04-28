<?php
// Full chatbot scenarios test via internal service
function runChatbot(string $prompt, string $threadId): string {
  $entity = \Drupal::entityTypeManager()->getStorage('ai_assistant')->load('main_assistant');
  $client = \Drupal::service('ai_assistant_api.runner');
  $client->setAssistant($entity);
  $client->setThreadsKey($threadId);
  $userMsg = new \Drupal\ai_assistant_api\Data\UserMessage($prompt);
  $client->setUserMessage($userMsg);
  try {
    $result = $client->process();
    return $result->getNormalized()->getText() ?? '';
  } catch (\Throwable $e) {
    return 'ERROR: ' . $e->getMessage();
  }
}

$tests = [
  ['label' => 'ترحيب',           'prompt' => 'مرحبا، ما هي مهامك؟'],
  ['label' => 'إنشاء محتوى',    'prompt' => 'أنشئ مقالة بعنوان: اختبار الإصلاح ' . date('H:i:s')],
  ['label' => 'بحث داخلي',      'prompt' => 'ابحث في الموقع عن مقالات'],
  ['label' => 'بحث خارجي',      'prompt' => 'ابحث في الانترنت عن Drupal 11 features'],
];

echo str_repeat('=', 60) . "\n";
echo "  اختبار الـ Chatbot الشامل بعد الإصلاح\n";
echo str_repeat('=', 60) . "\n\n";

$passed = 0;
foreach ($tests as $i => $t) {
  echo "Test " . ($i+1) . ": {$t['label']}\n";
  echo "Prompt: {$t['prompt']}\n";
  $t0 = microtime(TRUE);
  $response = runChatbot($t['prompt'], 'fix_test_' . $i . '_' . time());
  $ms = round((microtime(TRUE) - $t0) * 1000);
  $ok = !str_starts_with($response, 'ERROR:') && strlen($response) > 20;
  $icon = $ok ? '✅' : '❌';
  echo "$icon Response ({$ms}ms): " . substr(str_replace("\n", ' ', $response), 0, 300) . "\n\n";
  if ($ok) $passed++;
}

echo str_repeat('=', 60) . "\n";
echo "  النتيجة: $passed/" . count($tests) . " نجاح\n";
echo str_repeat('=', 60) . "\n";
