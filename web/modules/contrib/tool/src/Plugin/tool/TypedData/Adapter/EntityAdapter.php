<?php

declare(strict_types=1);

namespace Drupal\tool\Plugin\tool\TypedData\Adapter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\tool\TypedData\Adapter\TypedDataAdapterBase;
use Drupal\tool\Attribute\TypedDataAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the data_type_adapter.
 */
#[TypedDataAdapter(
  id: 'entity',
  label: new TranslatableMarkup('Entity'),
  description: new TranslatableMarkup('Entity input for entity data types.'),
)]
final class EntityAdapter extends TypedDataAdapterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(DataDefinitionInterface $data_definition): bool {
    return in_array($data_definition->getDataType(), ['entity'], TRUE) || $data_definition instanceof EntityDataDefinitionInterface;
  }

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(TypedDataInterface $data, array $element, SubformStateInterface $form_state): array {
    $element['#typed_data'] = $data;
    $element['#process'][] = [$this, 'processEntityAdapter'];
    return $element;
  }

  /**
   * Form process callback for the entity adapter.
   */
  public function processEntityAdapter(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);
    $data = $element['#typed_data'];
    $entity_definitions = array_filter($this->entityTypeManager->getDefinitions(), function ($definition) {
      return $definition->getClass() && is_subclass_of($definition->getClass(), ContentEntityInterface::class);
    });

    $options = [];
    foreach ($entity_definitions as $entity_definition) {
      $options[$entity_definition->id()] = $entity_definition->getLabel();
    }
    // @todo handle target type when fixed value.
    $target_type = $data->getValue()?->getEntityTypeId() ?? $subform_state->getValue('entity_type_id', 'node');

    if (!$id = $subform_state->get('wrapper_id')) {
      $id = Html::getId('wrapper__tool__entity_adapter');
      $subform_state->set('wrapper_id', $id);
    }

    $element['entity_type_id'] = [
      '#title' => $this->t('Entity type'),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $target_type,
      '#ajax' => [
        'callback' => [$this, 'setEntityTypeId'],
        'wrapper' => $id,
        'trigger_as' => [
          'name' => 'wrapper__tool__entity_adapter',
        ],
      ],
    ];

    $validation_parents = $element['#array_parents'];
    $validation_parents[] = 'entity_type_id';
    $element['entity_type_id_save'] = [
      '#title' => $this->t('Entity type'),
      '#type' => 'submit',
      '#value' => $this->t('Set'),
      '#options' => $options,
      '#submit' => [[$this, 'setEntityTypeId']],
      '#name' => 'wrapper__tool__entity_adapter',
      '#ajax' => [
        'callback' => [static::class, 'setEntityTypeIdAjax'],
        'wrapper' => $id,
        'method' => 'replaceWith',
        'trigger_key' => 'entity_type_id',
      ],
      '#limit_validation_errors' => [$validation_parents],
      '#attributes' => [
        'class' => ['js-hide'],
      ],
    ];
    $element['entity_id'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => $target_type,
      '#title' => $this->t('Select @type entity', ['@type' => $entity_definitions[$target_type]->getLabel()]),
      '#default_value' => $data->getValue(),
      '#description' => $data->getDataDefinition()->getDescription(),
      '#required' => $data->getDataDefinition()->isRequired(),
      '#description_display' => 'after',
      '#disabled' => $data->getDataDefinition()->isReadOnly(),
      '#prefix' => '<div id="' . $id . '">',
      '#suffix' => '</div>',
    ];

    if ($subform_state->getValue('entity_type_id') && !$subform_state->getValue('entity_id')) {
      $element['entity_id']['#value'] = NULL;
    }
    return $element;
  }

  /**
   * Ajax callback to rebuild the form when the entity type is changed.
   */
  public static function setEntityTypeIdAjax(array $form, FormStateInterface $form_state) {
    // $form_state->setRebuild(TRUE);
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#array_parents'], 0, -1);
    $parents[] = 'entity_id';
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Handles switching the available regions based on the selected theme.
   */
  public function setEntityTypeId($form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, array &$form, FormStateInterface $form_state): void {
    // Ensure empty values correctly end up as NULL value.
    $values = $form_state->getValues();
    if (isset($values['entity_type_id'], $values['entity_id'])) {
      $entity = $this->entityTypeManager->getStorage($values['entity_type_id'])->load($values['entity_id']);
      if ($entity) {
        $data->setValue($entity);
      }
      else {
        $data->setValue(NULL);
      }
    }
    else {
      $data->setValue(NULL);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFromData(TypedDataInterface $data): array {
    $value = $data->getValue();

    // Convert entity objects to config-safe array format.
    if ($value instanceof ContentEntityInterface) {
      return [
        'value' => [
          'entity_type_id' => $value->getEntityTypeId(),
          'entity_id' => (int) $value->id(),
        ],
      ];
    }

    // NULL or other values.
    return ['value' => $value];
  }

  /**
   * {@inheritdoc}
   */
  public function setDataFromConfiguration(TypedDataInterface $data, array $configuration): void {
    if (!isset($configuration['value'])) {
      return;
    }

    $config_value = $configuration['value'];

    // If it's already an entity, use it directly.
    if ($config_value instanceof ContentEntityInterface) {
      $data->setValue($config_value);
      return;
    }

    // If it's an array with entity_type_id and entity_id, load the entity.
    if (is_array($config_value) && isset($config_value['entity_type_id'], $config_value['entity_id'])) {
      $entity = $this->entityTypeManager->getStorage($config_value['entity_type_id'])->load($config_value['entity_id']);
      $data->setValue($entity ?? NULL);
      return;
    }

    // Fallback: set NULL.
    $data->setValue(NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, FormStateInterface $form_state) {
    return $element['entity_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaDefinition(DataDefinitionInterface $data_definition): array {
    $schema = [
      'type' => 'mapping',
    ];

    $label = $data_definition->getLabel();
    if ($label) {
      $schema['label'] = (string) $label;
    }

    // Define the structure for entity references with separate type and id.
    $schema['mapping'] = [
      'entity_type_id' => [
        'type' => 'string',
        'label' => 'Entity type',
        'constraints' => [
          'NotNull' => [],
        ],
      ],
      'entity_id' => [
        'type' => 'integer',
        'label' => 'Entity ID',
        'constraints' => [
          'NotNull' => [],
        ],
      ],
    ];

    // Add validation constraints based on the data definition.
    if ($data_definition->isRequired()) {
      $schema['constraints']['NotNull'] = [];
    }
    else {
      $schema['nullable'] = TRUE;
    }

    return $schema;
  }

}
