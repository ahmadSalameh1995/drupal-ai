<?php

declare(strict_types=1);

namespace Drupal\tool\TypedData\Adapter;

use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Interface for data_type_adapter plugins.
 */
interface TypedDataAdapterInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Determines if the plugin is applicable for the given data definition.
   */
  public static function isApplicable(DataDefinitionInterface $data_definition): bool;

  /**
   * Returns a form element for the given typed data.
   */
  public function formElement(TypedDataInterface $data, array $element, SubformStateInterface $form_state): array;

  /**
   * Extracts form values into the given typed data.
   */
  public function extractFormValues(TypedDataInterface $data, array &$form, SubformStateInterface $form_state): void;

  /**
   * Returns the schema definition array for the given data definition.
   *
   * This is used to generate config schema YAML, not to retrieve actual
   * config schema from the system.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $data_definition
   *   The data definition.
   *
   * @return array
   *   The schema definition array suitable for YAML serialization.
   */
  public function getSchemaDefinition(DataDefinitionInterface $data_definition): array;

  /**
   * Populates typed data from configuration array.
   *
   * Converts configuration format to runtime format (upcast).
   * For example, converts ['entity_type_id' => ..., 'entity_id' => ...]
   * to Entity objects.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The typed data to populate.
   * @param array $configuration
   *   The configuration array.
   */
  public function setDataFromConfiguration(TypedDataInterface $data, array $configuration): void;

  /**
   * Extracts configuration array from typed data.
   *
   * Converts runtime format to configuration format (downcast).
   * For example, converts Entity objects to
   * ['entity_type_id' => ..., 'entity_id' => ...].
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The typed data containing runtime values.
   *
   * @return array
   *   The configuration array.
   */
  public function getConfigurationFromData(TypedDataInterface $data): array;

}
