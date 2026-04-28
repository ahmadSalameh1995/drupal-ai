<?php

declare(strict_types=1);

namespace Drupal\tool\Plugin\tool\TypedData\Adapter;

use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\tool\TypedData\Adapter\TypedDataAdapterBase;
use Drupal\tool\Attribute\TypedDataAdapter;

/**
 * Plugin implementation of the data_type_adapter.
 */
#[TypedDataAdapter(
  id: 'number',
  label: new TranslatableMarkup('Number'),
  description: new TranslatableMarkup('Number input for number data types.'),
)]
final class NumberAdapter extends TypedDataAdapterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(DataDefinitionInterface $data_definition): bool {
    return in_array($data_definition->getDataType(), ['integer', 'decimal', 'float'], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(TypedDataInterface $data, array $element, SubformStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'number',
      '#title' => $data->getDataDefinition()->getLabel(),
      '#default_value' => $data->getValue(),
      '#description' => $data->getDataDefinition()->getDescription(),
      '#required' => $data->getDataDefinition()->isRequired(),
      '#description_display' => 'after',
      '#disabled' => $data->getDataDefinition()->isReadOnly(),
    ];
    // Set the step for floating point and decimal numbers.
    switch ($data->getDataDefinition()->getDataType()) {
      case 'decimal':
        $form['value']['#step'] = 0.1;
        break;

      case 'float':
        $form['value']['#step'] = 'any';
        break;
    }
    return $form;
  }

}
