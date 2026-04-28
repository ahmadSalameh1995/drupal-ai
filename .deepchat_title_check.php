<?php
$title = getenv("TITLE");
$storage = \Drupal::entityTypeManager()->getStorage("node");
$nids = \Drupal::entityQuery("node")->accessCheck(FALSE)->condition("title", $title)->execute();
echo "TITLE_COUNT=" . count($nids) . PHP_EOL;
if ($nids) {
  $nodes = $storage->loadMultiple($nids);
  foreach ($nodes as $n) {
    echo "NID=" . $n->id() . " CREATED=" . $n->getCreatedTime() . " CHANGED=" . $n->getChangedTime() . " STATUS=" . ($n->isPublished() ? 1 : 0) . PHP_EOL;
  }
}
