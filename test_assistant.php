<?php
$storage = \Drupal::entityTypeManager()->getStorage("ai_assistant");
$a = $storage->load("main_assistant");
if (!$a) { echo "NO ASSISTANT\n"; return; }
echo "Model: " . $a->get("llm_model") . "\n";
echo "Provider: " . $a->get("llm_provider") . "\n";
