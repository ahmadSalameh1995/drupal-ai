<?php

use Drupal\ai_assistant_api\Data\UserMessage;

function ask_assistant(string $threadId, string $prompt): string {
  $assistant = \Drupal::entityTypeManager()->getStorage('ai_assistant')->load('main_assistant');
  if (/home/as/masar/drupal-ddev-projectassistant) {
    throw new \RuntimeException('main_assistant not found');
  }

  $runner = \Drupal::service('ai_assistant_api.runner');
  $runner->setAssistant($assistant);
  $runner->setThreadsKey($threadId);
  $runner->setVerboseMode(FALSE);
  $runner->setThrowException(TRUE);
  $runner->setUserMessage(new UserMessage($prompt));

  $output = $runner->process();
  $normalized = $output->getNormalized();

  if (method_exists($normalized, 'getText')) {
    return (string) $normalized->getText();
  }

  return '';
}

$results = [
  'create' => ['ok' => FALSE, 'details' => ''],
  'internal_search' => ['ok' => FALSE, 'details' => ''],
  'external_search' => ['ok' => FALSE, 'details' => ''],
  'modify' => ['ok' => FALSE, 'details' => ''],
];

$token = 'AUTOTEST_CHATBOT_' . date('Ymd_His');
$title = $token . '_TITLE';
$bodyMarker = $token . '_BODY_MARKER';
$modTitle = $token . '_TITLE_MOD';
$modMarker = $token . '_MOD_MARKER';

$threadCreate = 'chatbot-create-' . time();
$threadInternal = 'chatbot-internal-' . time();
$threadExternal = 'chatbot-external-' . time();
$threadModify = 'chatbot-modify-' . time();

$createdNid = NULL;

try {
  $createPrompt = "أنشئ محتوى جديد من نوع Article بعنوان {$title}. يجب أن يحتوي النص على العبارة {$bodyMarker} وأن يكون منشورا. ثم أكد لي باختصار أنه تم الإنشاء.";
  $createReply = ask_assistant($threadCreate, $createPrompt);

  $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
  $nodes = $nodeStorage->loadByProperties(['title' => $title]);
  if (!empty($nodes)) {
    $node = reset($nodes);
    $createdNid = (int) $node->id();
    $body = (string) ($node->get('body')->value ?? '');
    $bodyHasMarker = str_contains($body, $bodyMarker);
    $results['create']['ok'] = $bodyHasMarker;
    $results['create']['details'] = 'nid=' . $createdNid . '; body_marker=' . ($bodyHasMarker ? 'yes' : 'no') . '; reply=' . mb_substr($createReply, 0, 160);
  }
  else {
    $results['create']['details'] = 'No node created; reply=' . mb_substr($createReply, 0, 200);
  }
}
catch (\Throwable $e) {
  $results['create']['details'] = 'Exception: ' . $e->getMessage();
}

try {
  $internalPrompt = "ابحث داخليا فقط في محتوى الموقع عن العبارة {$bodyMarker} وأعد لي العنوان الكامل للمحتوى.";
  $internalReply = ask_assistant($threadInternal, $internalPrompt);
  $ok = str_contains($internalReply, $title) || str_contains($internalReply, $bodyMarker);
  $results['internal_search']['ok'] = $ok;
  $results['internal_search']['details'] = mb_substr($internalReply, 0, 220);
}
catch (\Throwable $e) {
  $results['internal_search']['details'] = 'Exception: ' . $e->getMessage();
}

try {
  $externalPrompt = 'ابحث على الويب عن خبر تقني حديث جدا اليوم مع ذكر مصدر واحد على الأقل.';
  $externalReply = ask_assistant($threadExternal, $externalPrompt);
  $ok = (mb_strlen(trim($externalReply)) > 40) && (str_contains($externalReply, 'http') || str_contains($externalReply, 'المصدر') || str_contains($externalReply, 'مصدر'));
  $results['external_search']['ok'] = $ok;
  $results['external_search']['details'] = mb_substr($externalReply, 0, 260);
}
catch (\Throwable $e) {
  $results['external_search']['details'] = 'Exception: ' . $e->getMessage();
}

try {
  if ($createdNid) {
    $modifyPrompt = "عدل المحتوى الداخلي رقم {$createdNid}. اجعل العنوان {$modTitle} وأضف العبارة {$modMarker} داخل النص ثم أكد التنفيذ.";
    $modifyReply = ask_assistant($threadModify, $modifyPrompt);

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($createdNid);
    if ($node) {
      $newTitle = (string) $node->label();
      $newBody = (string) ($node->get('body')->value ?? '');
      $titleOk = ($newTitle === $modTitle);
      $bodyOk = str_contains($newBody, $modMarker);
      $results['modify']['ok'] = $titleOk && $bodyOk;
      $results['modify']['details'] = 'title_ok=' . ($titleOk ? 'yes' : 'no') . '; body_ok=' . ($bodyOk ? 'yes' : 'no') . '; reply=' . mb_substr($modifyReply, 0, 180);
    }
    else {
      $results['modify']['details'] = 'Created node missing before modify check';
    }
  }
  else {
    $results['modify']['details'] = 'Skipped: create step did not produce node';
  }
}
catch (\Throwable $e) {
  $results['modify']['details'] = 'Exception: ' . $e->getMessage();
}

$passed = 0;
$total = count($results);
foreach ($results as $name => $data) {
  echo strtoupper($name) . ': ' . ($data['ok'] ? 'PASS' : 'FAIL') . PHP_EOL;
  echo '  ' . $data['details'] . PHP_EOL;
  if ($data['ok']) {
    $passed++;
  }
}

echo 'SUMMARY: ' . $passed . '/' . $total . ' passed' . PHP_EOL;
