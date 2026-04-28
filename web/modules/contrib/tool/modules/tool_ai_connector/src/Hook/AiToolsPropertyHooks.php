<?php

declare(strict_types=1);

namespace Drupal\tool_ai_connector\Hook;

use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\tool\TypedData\MapContextDefinition;

/**
 * Implements hook_ai_tools_property_alter().
 */
final class AiToolsPropertyHooks {

  /**
   * Implements hook_ai_tools_property_alter() via the Alter attribute.
   */
  #[Hook('ai_tools_property_alter')]
  public function aiToolsPropertyAlter(ToolsPropertyInputInterface $property, ContextDefinitionInterface $definition): void {
    if ($definition instanceof MapContextDefinition) {
      // @phpstan-ignore-next-line
      $properties = \Drupal::service('ai.context_definition_normalizer')->normalize($definition->getPropertyDefinitions()); // phpcs:ignore
      $property->setType('object');
      $property->setProperties($properties);
    }
  }

}
