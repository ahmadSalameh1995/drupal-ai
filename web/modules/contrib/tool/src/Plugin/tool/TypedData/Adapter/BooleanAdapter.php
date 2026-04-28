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
  id: 'boolean',
  label: new TranslatableMarkup('String'),
  description: new TranslatableMarkup('Textfield input for string data types.'),
)]
final class BooleanAdapter extends TypedDataAdapterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(DataDefinitionInterface $data_definition): bool {
    return $data_definition->getDataType() === 'boolean';
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(TypedDataInterface $data, array $element, SubformStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'checkbox',
      '#title' => $data->getDataDefinition()->getLabel(),
      '#default_value' => $data->getValue(),
      '#description' => $data->getDataDefinition()->getDescription(),
      '#required' => $data->getDataDefinition()->isRequired(),
      '#description_display' => 'after',
      '#disabled' => $data->getDataDefinition()->isReadOnly(),
    ];
    return $form;
  }

}
