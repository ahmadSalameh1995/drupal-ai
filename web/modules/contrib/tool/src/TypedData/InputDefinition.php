<?php

namespace Drupal\tool\TypedData;

use Drupal\Core\Config\Schema\SequenceDataDefinition;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Defines a context definition for input data types.
 */
class InputDefinition extends ContextDefinition implements InputDefinitionInterface {
  use InputDefinitionTrait;

  /**
   * Constructs a new context definition object.
   *
   * @param string $data_type
   *   The required data type.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label of this context definition for the UI.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of this context definition for the UI.
   * @param bool $required
   *   Whether the context definition is required.
   * @param bool $multiple
   *   Whether the context definition is multivalue.
   * @param mixed $default_value
   *   The default value of this definition.
   * @param array<string, mixed> $constraints
   *   An array of constraints keyed by the constraint name and a value of an
   *   array constraint options or a NULL.
   * @param bool $locked
   *   Whether the input is locked, meaning it cannot be changed by context or
   *   configuration.
   */
  public function __construct($data_type, string|TranslatableMarkup $label, string|TranslatableMarkup $description, $required = TRUE, $multiple = FALSE, $default_value = NULL, ?array $constraints = [], bool $locked = FALSE) {
    parent::__construct($data_type, $label, $required, $multiple, $description, $default_value, $constraints);
    $this->isLocked = $locked;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataDefinition() {
    $definition = parent::getDataDefinition();
    // @todo Fix upstream.
    if ($this->isMultiple() && $definition instanceof ListDataDefinitionInterface) {
      $item_definition = $definition->getItemDefinition();
      foreach ($this->getConstraints() as $name => $options) {
        $item_definition->addConstraint($name, $options);
      }
    }
    return $definition;
  }

  /**
   * Creates an InputDefinition from a config schema array.
   *
   * @param array $config_schema
   *   The config schema array.
   */
  public static function fromConfigSchema(array $config_schema): InputDefinitionInterface {
    $typed_config_manager = \Drupal::service('config.typed');
    $data_definition = $typed_config_manager->buildDataDefinition($config_schema, NULL);
    return self::fromDataDefinition($data_definition);
  }

  /**
   * Creates an InputDefinition from a DataDefinition.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The data definition.
   */
  public static function fromDataDefinition(DataDefinitionInterface $definition): InputDefinitionInterface {
    $description = $definition->getDescription() ?? new TranslatableMarkup('The input definition for @label', ['@label' => $definition->getLabel()]);
    // @phpstan-ignore-next-line
    $definition_array = $definition->toArray();
    // @phpstan-ignore-next-line
    $is_config_based = $definition->getTypedDataManager() instanceof TypedConfigManagerInterface;
    $data_type = $definition_array['type'];

    // @todo Allow hook_alter here to modify the created InputDefinition.
    if ($definition instanceof MapDataDefinition) {
      $map_definition = new MapInputDefinition(
        label: $definition->getLabel(),
        description: $description,
        required: $definition->isRequired(),
      );
      if ($is_config_based) {
        if (!empty($definition_array['mapping'])) {
          $property_definitions = [];
          foreach ($definition_array['mapping'] as $name => $property) {
            $property_definitions[$name] = self::fromConfigSchema($property);
          }
          $map_definition->setPropertyDefinitions($property_definitions);
        }
      }
      else {
        $property_definitions = [];
        foreach ($definition->getPropertyDefinitions() as $name => $property_definition) {
          $property_definitions[$name] = self::fromDataDefinition($property_definition);
        }
        $map_definition->setPropertyDefinitions($property_definitions);
      }

      return $map_definition;
    }
    elseif ($definition instanceof SequenceDataDefinition) {
      // @todo It appears that sequence data is improperly handled upstream
      // for setting item definitions from config schema, as the item definition
      // is mapping to 'any' instead of the actual type.
      // @todo A sequence is more like a map with dynamic properties, but we
      // we don't have a DynamicMapInputDefinition option.
      $list_definition = new ListInputDefinition(
        label: $definition->getLabel(),
        description: $description,
        required: $definition->isRequired(),
        default_value: $definition_array['default_value'] ?? NULL,
      );

      if ($is_config_based && isset($definition_array['sequence']['type'])) {
        $list_definition->setItemDefinition(self::fromConfigSchema($definition_array['sequence']));
      }
      else {
        $list_definition->setItemDefinition(self::fromDataDefinition($definition->getItemDefinition()));
      }
      return $list_definition;
    }
    elseif ($definition instanceof ListDataDefinitionInterface) {
      $list_definition = new ListInputDefinition(
        label: $definition->getLabel(),
        description: $description,
        required: $definition->isRequired(),
        default_value: $definition_array['default_value'] ?? NULL,
      );
      if (!$is_config_based) {
        $list_definition->setItemDefinition(self::fromDataDefinition($definition->getItemDefinition()));
      }
      return $list_definition;
    }
    elseif ($is_config_based && $definition_array['unwrap_for_canonical_representation']) {
      // If the schema is wrapped, unwrap it to get the actual data type.
      try {
        $reflection = new \ReflectionClass($definition_array['class']);
        $plugin_attribute = $reflection->getAttributes(DataType::class)[0] ?? NULL;
        if (!$plugin_attribute) {
          throw new \RuntimeException("Data type class {$definition_array['class']} is missing the DataType attribute.");
        }
        $data_type = $plugin_attribute->newInstance()->getId();
      }
      catch (\ReflectionException $e) {
        throw new \RuntimeException("Error reflecting data type class {$definition_array['class']}: " . $e->getMessage());
      }
    }
    $default_value = $is_config_based && isset($definition_array['default_value']) ? $definition_array['default_value'] : NULL;
    return new InputDefinition(
      data_type: $data_type,
      label: $definition->getLabel(),
      description: $description,
      required: $definition->isRequired(),
      default_value: $default_value,
    );
  }

}
