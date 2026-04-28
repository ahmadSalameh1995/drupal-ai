<?php
$config = \Drupal::configFactory()->getEditable("block.block.olivero_aideepchatchatbot");
$settings = $config->get("settings");
$settings["verbose_mode"] = false;
$config->set("settings", $settings)->save();
echo "verbose_mode: " . ($config->get("settings.verbose_mode") ? "true" : "false") . "\n";
