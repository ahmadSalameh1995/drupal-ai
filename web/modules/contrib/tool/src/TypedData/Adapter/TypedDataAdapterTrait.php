<?php

namespace Drupal\tool\TypedData\Adapter;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataTrait;

/**
 * Trait for typed data adapters.
 */
trait TypedDataAdapterTrait {
  use TypedDataTrait;

  /**
   * Gets the adapter instance for a given data definition.
   *
   * Note: We don't cache adapter instances to avoid serialization issues.
   * Adapters may have service dependencies that cannot be serialized.
   */
  public function getAdapterInstance(DataDefinitionInterface $data_definition, string $name) {
    $adapter_manager = \Drupal::service('plugin.manager.tool.typed_data_adapter');
    $adapter_definition = $adapter_manager->getDefinitionFromDataDefinition($data_definition);
    return $adapter_manager->createInstance($adapter_definition['id']);
  }

}
