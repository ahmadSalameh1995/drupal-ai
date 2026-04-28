<?php
// Test chatbot via internal service
$entity = \Drupal::entityTypeManager()->getStorage('ai_assistant')->load('main_assistant');
if (!$entity) {
  echo "ERROR: assistant not found\n";
  exit;
}
echo "Assistant: " . $entity->label() . "\n";

$client = \Drupal::service('ai_assistant_api.runner');
$client->setAssistant($entity);
$client->setThreadsKey('nocode_test_' . time());

$userMsg = new \Drupal\ai_assistant_api\Data\UserMessage('مرحبا، ما هي مهامك؟');
$client->setUserMessage($userMsg);

try {
  $result = $client->process();
  echo "Response type: " . get_class($result) . "\n";
  $normalized = $result->getNormalized();
  echo "Normalized type: " . get_class($normalized) . "\n";
  echo "Response: " . $normalized->getText() . "\n";
} catch (\Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  echo "At: " . $e->getFile() . ':' . $e->getLine() . "\n";
}
