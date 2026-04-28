<?php

namespace Drupal\tool_ai_connector\Plugin\AiFunctionCall\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tool\Tool\ToolManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base function call for each entity type with specific interfaces.
 */
class ToolPluginDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The tool manager.
   *
   * @var \Drupal\tool\Tool\ToolManager
   */
  protected ToolManager $toolManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    // @phpstan-ignore new.static
    $instance = new static();
    $instance->toolManager = $container->get('plugin.manager.tool');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    if (empty($this->derivatives)) {
      $definitions = [];
      foreach ($this->toolManager->getDefinitions() as $id => $tool_definition) {
        /** @var \Drupal\tool\Tool\ToolDefinition $tool_definition */
        $definition = $base_plugin_definition;
        $definition['id'] = 'tool:' . $id;
        $definition['name'] = $tool_definition->getLabel();
        $definition['group'] = 'tool';
        $definition['function_name'] = str_replace(':', '__', $definition['id']);
        $definition['description'] = $tool_definition->getDescription();
        $definition['context_definitions'] = $tool_definition->getInputDefinitions();
        $definitions[$id] = $definition;
      }
      $this->derivatives = $definitions;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
