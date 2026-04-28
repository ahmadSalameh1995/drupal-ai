<?php

declare(strict_types=1);

namespace Drupal\tool\TypedData\Adapter;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\tool\Attribute\TypedDataAdapter;

/**
 * TypedDataAdapter plugin manager.
 */
final class TypedDataAdapterManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/tool/TypedData/Adapter', $namespaces, $module_handler, TypedDataAdapterInterface::class, TypedDataAdapter::class);
    $this->alterInfo('data_type_adapter_info');
    $this->setCacheBackend($cache_backend, 'data_type_adapter_plugins');
  }

  /**
   * Gets the appropriate adapter definition for a given data definition.
   */
  public function getDefinitionFromDataDefinition(DataDefinitionInterface $data_definition): ?array {
    $definitions = $this->getDefinitions();
    uasort($definitions, [SortArray::class, 'sortByWeightElement']);
    foreach ($definitions as $definition) {
      if ($definition['class']::isApplicable($data_definition)) {
        return $definition;
      }
    }
    return $this->getDefinition('undefined');
  }

}
