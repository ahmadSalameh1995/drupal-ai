<?php

declare(strict_types=1);

namespace Drupal\tool_ai_connector\Plugin\AiFunctionGroup;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionGroup;
use Drupal\ai\Service\FunctionCalling\FunctionGroupInterface;

/**
 * The Drupal actions.
 */
#[FunctionGroup(
  id: 'tool',
  group_name: new TranslatableMarkup('Tools API'),
  description: new TranslatableMarkup('These expose tools from the Tools API as functions that you can call.'),
)]
final class ToolsApi implements FunctionGroupInterface {

}
