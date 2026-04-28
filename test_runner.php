<?php
$start = microtime(true);
$storage = \Drupal::entityTypeManager()->getStorage("ai_assistant");
$assistant = $storage->load("main_assistant");
if (!$assistant) { echo "NO ASSISTANT\n"; return; }
echo "Model: " . $assistant->get("llm_model") . "\n";
$runner = \Drupal::service("ai_assistant_api.runner");
$runner->setAssistant($assistant);
$runner->setVerboseMode(FALSE);
use Drupal\ai_assistant_api\Data\UserMessage;
$msg = new UserMessage("Say hello");
$runner->setUserMessage($msg);
try {
  $resp = $runner->process();
  $norm = $resp->getNormalized();
  echo "Response: " . (string)$norm . "\n";
} catch (\Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}
echo "Time: " . round(microtime(true)-$start, 2) . "s\n";
