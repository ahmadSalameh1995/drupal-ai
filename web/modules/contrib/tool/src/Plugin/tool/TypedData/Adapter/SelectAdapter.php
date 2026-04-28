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
  id: 'select',
  label: new TranslatableMarkup('Select'),
  description: new TranslatableMarkup('Select input for choice data types.'),
  weight: -10,
)]
final class SelectAdapter extends TypedDataAdapterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(DataDefinitionInterface $data_definition): bool {
    $constraints = $data_definition->getConstraints();
    if ($constraints) {
      return isset($constraints['Choice'])
        || isset($constraints['PluginExists']);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(TypedDataInterface $data, array $element, SubformStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'select',
      '#title' => $data->getDataDefinition()->getLabel(),
      '#default_value' => $data->getValue(),
      '#description' => $data->getDataDefinition()->getDescription(),
      '#required' => $data->getDataDefinition()->isRequired(),
      '#description_display' => 'after',
      '#disabled' => $data->getDataDefinition()->isReadOnly(),
      '#options' => $this->getOptions($data),
    ];
    return $form;
  }

  /**
   * Gets the options for the select element based on the data constraints.
   */
  public function getOptions(TypedDataInterface $data): array {
    $options = [];
    $constraints = $data->getDataDefinition()->getConstraints();
    if (isset($constraints['Choice'])) {
      if (isset($constraints['Choice']['choices']) && is_array($constraints['Choice']['choices'])) {
        $options = array_combine($constraints['Choice']['choices'], $constraints['Choice']['choices']);
      }
      elseif (isset($constraints['Choice']['options']) && is_array($constraints['Choice']['options'])) {
        // If 'options' is provided, we assume it contains key-value pairs.
        $values = $constraints['Choice']['options'];
        if (is_array($values[0])) {
          $options = $values;
        }
        else {
          $options = array_combine($values, $values);
        }
      }
      elseif (isset($constraints['Choice']['callback']) && is_callable($constraints['Choice']['callback'])) {
        $values = call_user_func($constraints['Choice']['callback']);
        $options = array_combine($values, $values);
      }
      else {
        // If no choices or options are provided, we return an empty array.
        $options = [];
      }

    }
    elseif (isset($constraints['PluginExists'])) {
      // If the constraint is PluginExists, we assume it has a 'manager' and
      // 'interface' key.
      $manager = $constraints['PluginExists']['manager'] ?? NULL;
      $interface = $constraints['PluginExists']['interface'] ?? NULL;
      if ($manager) {
        // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
        $plugin_manager = \Drupal::service($manager);
        $definitions = $plugin_manager->getDefinitions();
        foreach ($definitions as $id => $definition) {
          if (is_object($definition)) {
            if ($interface && !is_subclass_of($definition->getClass(), $interface)) {
              continue;
            }
            $options[$id] = $definition->getLabel() ?? $id;
          }
          else {
            if ($interface && !is_subclass_of($definition['class']::class, $interface)) {
              continue;
            }
            $options[$id] = $definition['label'] ?? $id;
          }
        }
      }
    }
    return $options;
  }

}
