<?php

declare(strict_types=1);

namespace Drupal\tool\TypedData\Adapter;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Base class for data_type_adapter plugins.
 */
abstract class TypedDataAdapterBase extends PluginBase implements TypedDataAdapterInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateElement(array $element, TypedDataInterface $data, SubformStateInterface $form_state) {
    // @todo come back to clone part.
    //   $data = clone $data;
    $this->extractFormValues($data, $element, $form_state);
    $violations = $data->validate();
    foreach ($violations as $violation) {
      $error_element = $this->errorElement($element, $violation, $form_state);
      $form_state->setError($error_element, $violation->getMessage());
    }

  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, FormStateInterface $form_state) {
    return $element['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, array &$form, FormStateInterface $form_state): void {
    // Ensure empty values correctly end up as NULL value.
    $value = $form_state->getValue('value');
    if ($value === '') {
      $value = NULL;
    }
    $data->setValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public function setDataFromConfiguration(TypedDataInterface $data, array $configuration): void {
    // Default: just set the value as-is from configuration.
    if (isset($configuration['value'])) {
      $data->setValue($configuration['value']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFromData(TypedDataInterface $data): array {
    // Default: just return the value as-is.
    return ['value' => $data->getValue()];
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaDefinition(DataDefinitionInterface $data_definition): array {
    $schema = [
      'type' => $data_definition->getDataType(),
    ];

    $label = $data_definition->getLabel();
    if ($label) {
      $schema['label'] = (string) $label;
    }

    // All config values are optional since tools can receive runtime values.
    // This allows partial configuration with runtime-provided values.
    $schema['nullable'] = TRUE;

    // Map TypedData constraints to config schema constraints.
    // Skip NotNull constraint since all config values are optional.
    $typed_data_constraints = $data_definition->getConstraints();
    if (!empty($typed_data_constraints)) {
      foreach ($typed_data_constraints as $constraint_name => $constraint_options) {
        // Skip NotNull - all config values are optional.
        if ($constraint_name !== 'NotNull') {
          $schema['constraints'][$constraint_name] = $constraint_options ?: [];
        }
      }
    }

    return $schema;
  }

}
