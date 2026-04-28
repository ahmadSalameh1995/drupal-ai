<?php

namespace Drupal\tool\TypedData;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Defines a context definition for map data types.
 */
class MapContextDefinition extends ContextDefinition {

  /**
   * The property definitions for map values.
   *
   * @var \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]
   */
  protected array $propertyDefinitions = [];

  /**
   * Constructs a new context definition object.
   *
   * @param string $data_type
   *   The required data type.
   * @param string|null|\Stringable $label
   *   The label of this context definition for the UI.
   * @param bool $required
   *   Whether the context definition is required.
   * @param bool $multiple
   *   Whether the context definition is multivalue.
   * @param string|null $description
   *   The description of this context definition for the UI.
   * @param mixed $default_value
   *   The default value of this definition.
   * @param array<string, mixed> $constraints
   *   An array of constraints keyed by the constraint name and a value of an
   *   array constraint options or a NULL.
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface[] $property_definitions
   *   The definition for map values.
   */
  // @phpstan-ignore constructor.unusedParameter
  public function __construct($data_type = 'map', $label = NULL, $required = TRUE, $multiple = FALSE, $description = NULL, $default_value = NULL, array $constraints = [], array $property_definitions = []) {
    parent::__construct('map', $label, $required, $multiple, $description, $default_value, $constraints);
    foreach ($property_definitions as $name => $property_definition) {
      if (!is_string($name)) {
        throw new \InvalidArgumentException('Value definition names must be strings.');
      }
      if (!$property_definition instanceof ContextDefinitionInterface) {
        throw new \InvalidArgumentException('Value definitions must be instances of ContextDefinitionInterface.');
      }
    }
    $this->propertyDefinitions = $property_definitions;
  }

  /**
   * Sets the property definitions.
   */
  public function setPropertyDefinitions(array $property_definitions): static {
    foreach ($property_definitions as $name => $property_definition) {
      if (!is_string($name)) {
        throw new \InvalidArgumentException('Value definition names must be strings.');
      }
      if (!$property_definition instanceof ContextDefinitionInterface) {
        throw new \InvalidArgumentException('Value definitions must be instances of ContextDefinitionInterface.');
      }
    }
    $this->propertyDefinitions = $property_definitions;
    return $this;
  }

  /**
   * Gets the property definitions.
   */
  public function getPropertyDefinitions(): array {
    return $this->propertyDefinitions;
  }

  /**
   * Gets a specific property definition by name.
   */
  public function getPropertyDefinition($name): ?ContextDefinitionInterface {
    return $this->propertyDefinitions[$name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue() {
    $default = parent::getDefaultValue();
    // @todo multiple?
    if ($default === NULL) {
      foreach ($this->getPropertyDefinitions() as $name => $property_definition) {
        $default[$name] = $property_definition->getDefaultValue();
      }
    }
    return $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataDefinition() {
    $definition = parent::getDataDefinition();
    if ($this->isMultiple()) {
      assert($definition instanceof ListDataDefinition);
      $item_definition = $definition->getItemDefinition();
      assert($item_definition instanceof MapDataDefinition);
      foreach ($this->getPropertyDefinitions() as $name => $property_definition) {
        $item_definition->setPropertyDefinition($name, $property_definition->getDataDefinition());
      }
      $definition->setItemDefinition($item_definition);
    }
    else {
      assert($definition instanceof MapDataDefinition);
      foreach ($this->getPropertyDefinitions() as $name => $property_definition) {
        $definition->setPropertyDefinition($name, $property_definition->getDataDefinition());
      }
    }
    return $definition;
  }

}
