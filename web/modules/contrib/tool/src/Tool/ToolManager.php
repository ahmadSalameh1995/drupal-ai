<?php

declare(strict_types=1);

namespace Drupal\tool\Tool;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\tool\Attribute\Tool;

/**
 * Tool plugin manager.
 */
final class ToolManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/tool/Tool', $namespaces, $module_handler, ToolInterface::class, Tool::class);
    $this->alterInfo('tool_info');
    $this->setCacheBackend($cache_backend, 'tool_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    /** @var \Drupal\tool\Tool\ToolDefinition $definition */
    parent::processDefinition($definition, $plugin_id);

    if ($definition instanceof ToolDefinition) {
      $forms = $definition->getForms();
      if (!isset($forms['configure'])) {
        $definition->addForm('configure', 'Drupal\tool\Form\ConfigureToolPluginForm');
      }
      if (!isset($forms['execute'])) {
        $definition->addForm('execute', 'Drupal\tool\Form\ExecuteToolPluginForm');
      }
    }
  }

}
