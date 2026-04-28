<?php

namespace Drupal\tool\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\tool\TypedData\Adapter\TypedDataAdapterTrait;

/**
 * Form for executing tool plugins.
 */
class ExecuteToolPluginForm extends ToolPluginFormBase implements ContainerInjectionInterface {
  use TypedDataTrait;
  use TypedDataAdapterTrait;

  /**
   * Constructs a ToolExplorerController object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected MessengerInterface $messenger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('messenger'),
    );
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

    if ($this->plugin->access()) {
      $this->plugin->execute();
      $this->messenger->addMessage((string) $this->plugin->getResultMessage());
    }
    else {
      $this->messenger->addError($this->t('You do not have access to execute this tool.'));
    }
  }

}
