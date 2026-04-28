<?php

declare(strict_types=1);

namespace Drupal\tool\Plugin\tool\TypedData\Adapter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\tool\TypedData\Adapter\TypedDataAdapterBase;
use Drupal\tool\Attribute\TypedDataAdapter;
use Drupal\tool\TypedData\Adapter\TypedDataAdapterTrait;

/**
 * Plugin implementation of the data_type_adapter.
 */
#[TypedDataAdapter(
  id: 'map',
  label: new TranslatableMarkup('Map'),
  description: new TranslatableMarkup('Map inputs for map data types.'),
)]
final class MapAdapter extends TypedDataAdapterBase {

  use TypedDataAdapterTrait;

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(DataDefinitionInterface $data_definition): bool {
    return $data_definition->getDataType() === 'map';
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(TypedDataInterface $data, array $element, SubformStateInterface $form_state): array {
    /** @var \Drupal\Core\TypedData\MapDataDefinition $map_definition */
    $map_definition = $data->getDataDefinition();
    $property_definitions = $map_definition->getPropertyDefinitions();

    // If no property definitions exist, provide a textarea for raw input.
    if (empty($property_definitions)) {
      $current_value = $data->getValue();
      $element['value'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Value'),
        '#description' => $this->t('Enter a JSON object representation of the map data.'),
        '#default_value' => $current_value ? json_encode($current_value, JSON_PRETTY_PRINT) : '',
        '#rows' => 10,
      ];
      return $element;
    }

    // Otherwise, build form elements for each property.
    $element['value'] = [
      '#type' => 'container',
    ];
    foreach ($property_definitions as $property_name => $property_definition) {
      $adapter = $this->getAdapterInstance($property_definition, $property_name);
      $property_data = $property_definition->getClass()::createInstance($property_definition);
      if (!isset($element['value'][$property_name])) {
        $element['value'][$property_name] = [];
      }
      $property_form_state = SubformState::createForSubform($element['value'][$property_name], $element, $form_state);
      $element['value'][$property_name] = $adapter->formElement($property_data, $element['value'][$property_name], $property_form_state);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\TypedData\MapDataDefinition $map_definition */
    $map_definition = $data->getDataDefinition();
    $property_definitions = $map_definition->getPropertyDefinitions();

    // If no property definitions exist, parse the textarea input.
    if (empty($property_definitions)) {
      $raw_value = $form_state->getValue('value');
      $decoded_value = json_decode($raw_value, TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        $data->setValue($decoded_value);
      }
      else {
        // If JSON parsing fails, set as empty array.
        $data->setValue([]);
      }
      return;
    }

    // Otherwise, extract values from property form elements.
    $result = [];
    foreach ($property_definitions as $property_name => $property_definition) {
      $adapter = $this->getAdapterInstance($property_definition, $property_name);
      $property_data = $property_definition->getClass()::createInstance($property_definition);
      $property_form_state = SubformState::createForSubform($form['value'][$property_name], $form, $form_state);
      $form[$property_name] = $adapter->formElement($property_data, $form['value'][$property_name], $property_form_state);
      $adapter->extractFormValues($property_data, $form['value'][$property_name], $property_form_state);
      $result[$property_name] = $property_data->getValue();
    }
    $data->setValue($result);
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaDefinition(DataDefinitionInterface $data_definition): array {
    $schema = parent::getSchemaDefinition($data_definition);
    $schema['type'] = 'mapping';

    /** @var \Drupal\Core\TypedData\MapDataDefinition $map_definition */
    $map_definition = $data_definition;
    $property_definitions = $map_definition->getPropertyDefinitions();

    if (!empty($property_definitions)) {
      $schema['mapping'] = [];
      foreach ($property_definitions as $property_name => $property_definition) {
        $adapter = $this->getAdapterInstance($property_definition, $property_name);
        $schema['mapping'][$property_name] = $adapter->getSchemaDefinition($property_definition);
      }

      // Add FullyValidatable constraint to mappings.
      $schema['constraints']['FullyValidatable'] = [];
    }

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFromData(TypedDataInterface $data): array {
    $value = $data->getValue();

    // If the value is NULL, return NULL.
    if ($value === NULL) {
      return ['value' => NULL];
    }

    // If it's not an array, return NULL.
    if (!is_array($value)) {
      return ['value' => NULL];
    }

    /** @var \Drupal\Core\TypedData\MapDataDefinition $map_definition */
    $map_definition = $data->getDataDefinition();
    $property_definitions = $map_definition->getPropertyDefinitions();

    // If no property definitions, store the value as-is (for raw JSON maps).
    if (empty($property_definitions)) {
      return ['value' => $value];
    }

    // Convert each property using its adapter.
    $result = [];
    foreach ($property_definitions as $property_name => $property_definition) {
      if (!array_key_exists($property_name, $value)) {
        continue;
      }

      $adapter = $this->getAdapterInstance($property_definition, $property_name);

      // Create a temporary typed data object for the property.
      $property_data = $property_definition->getClass()::createInstance($property_definition);
      $property_data->setValue($value[$property_name]);

      // Use the adapter to get configuration.
      $property_config = $adapter->getConfigurationFromData($property_data);
      $result[$property_name] = $property_config['value'] ?? NULL;
    }

    return ['value' => $result];
  }

  /**
   * {@inheritdoc}
   */
  public function setDataFromConfiguration(TypedDataInterface $data, array $configuration): void {
    if (!isset($configuration['value'])) {
      return;
    }

    $config_value = $configuration['value'];

    // If NULL, set NULL.
    if ($config_value === NULL) {
      $data->setValue(NULL);
      return;
    }

    // If it's not an array, set NULL.
    if (!is_array($config_value)) {
      $data->setValue(NULL);
      return;
    }

    /** @var \Drupal\Core\TypedData\MapDataDefinition $map_definition */
    $map_definition = $data->getDataDefinition();
    $property_definitions = $map_definition->getPropertyDefinitions();

    // If no property definitions, set the value as-is (for raw JSON maps).
    if (empty($property_definitions)) {
      $data->setValue($config_value);
      return;
    }

    // Convert each property from config format back to runtime format.
    $result = [];
    foreach ($property_definitions as $property_name => $property_definition) {
      if (!array_key_exists($property_name, $config_value)) {
        continue;
      }

      $adapter = $this->getAdapterInstance($property_definition, $property_name . '_from_config');

      // Create a temporary typed data object for the property.
      $property_data = $property_definition->getClass()::createInstance($property_definition);

      // Use the adapter to load from configuration.
      $adapter->setDataFromConfiguration($property_data, ['value' => $config_value[$property_name]]);

      $result[$property_name] = $property_data->getValue();
    }

    $data->setValue($result);
  }

}
