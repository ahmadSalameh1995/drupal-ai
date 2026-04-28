<?php

/**
 * @file
 * Ensure Drupal autoloader and dependencies are available.
 */

// Find the Drupal autoloader by traversing up from the module directory.
$current_dir = __DIR__;
$vendor_autoload = NULL;

// Try to find vendor/autoload.php by going up directories.
for ($i = 0; $i < 10; $i++) {
  $try_path = $current_dir . '/vendor/autoload.php';
  if (file_exists($try_path)) {
    $vendor_autoload = $try_path;
    break;
  }
  $current_dir = dirname($current_dir);
}

if ($vendor_autoload) {
  require_once $vendor_autoload;
}
else {
  // Fallback error message.
  throw new RuntimeException('Could not find vendor/autoload.php');
}
