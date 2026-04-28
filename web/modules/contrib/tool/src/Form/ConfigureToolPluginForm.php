<?php

namespace Drupal\tool\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\tool\TypedData\Adapter\TypedDataAdapterTrait;

/**
 * Form for configuring tool plugins.
 */
class ConfigureToolPluginForm extends ToolPluginFormBase implements ContainerInjectionInterface {
  use TypedDataTrait;
  use TypedDataAdapterTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $values = $this->plugin->getInputValues();
    foreach ($this->plugin->getInputDefinitions() as $name => $definition) {
      $typed_data = $this->getTypedDataManager()->create($definition->getDataDefinition(), $values[$name], $name);
      $form[$name] = [];
      $adapter = $this->getAdapterInstance($definition->getDataDefinition(), $name);
      $subform_state = SubformState::createForSubform($form[$name], $form, $form_state);
      $form[$name] = $adapter->formElement($typed_data, $form[$name], $subform_state);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $this->plugin->getInputValues();
    foreach ($this->plugin->getInputDefinitions() as $name => $definition) {
      $adapter = $this->getAdapterInstance($definition->getDataDefinition(), $name);
      $typed_data = $this->getTypedDataManager()->create($definition->getDataDefinition(), $values[$name], $name);
      $subform_state = SubformState::createForSubform($form[$name], $form, $form_state);
      $adapter->extractFormValues($typed_data, $form[$name], $subform_state);
      $this->plugin->setInputValue($name, $typed_data->getValue());
    }
  }

}
