<?php

namespace Drupal\tool\TypedData;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;

/**
 * Defines a context definition for list data types.
 */
class ListContextDefinition extends ContextDefinition {

  /**
   * The item definition for list values.
   *
   * @var \Drupal\Core\Plugin\Context\ContextDefinitionInterface|null
   */
  protected ?ContextDefinitionInterface $itemDefinition;

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
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface|null $item_definition
   *   The definition for list items.
   */
  // @phpstan-ignore constructor.unusedParameter
  public function __construct($data_type = 'list', $label = NULL, $required = TRUE, $multiple = FALSE, $description = NULL, $default_value = NULL, array $constraints = [], ?ContextDefinitionInterface $item_definition = NULL) {
    parent::__construct('list', $label, $required, $multiple, $description, $default_value, $constraints);
    $this->itemDefinition = $item_definition;
  }

  /**
   * Sets the item definition.
   */
  public function setItemDefinition(ContextDefinitionInterface $item_definition): static {
    $this->itemDefinition = $item_definition;
    return $this;
  }

  /**
   * Gets the item definition.
   */
  public function getItemDefinition(): ContextDefinitionInterface {
    if (!$this->itemDefinition) {
      return new ContextDefinition('any', 'Any');
    }
    return $this->itemDefinition;
  }

}
