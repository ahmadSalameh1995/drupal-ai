<?php

namespace Drupal\tool\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a list input context definition.
 */
class ListInputDefinition extends ListContextDefinition implements InputDefinitionInterface {
  use InputDefinitionTrait;

  /**
   * Constructs a new context definition object.
   *
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
   * @param \Drupal\tool\TypedData\InputDefinitionInterface|null $item_definition
   *   The definition for list items.
   * @param bool $locked
   *   Whether the input is locked, meaning it cannot be changed by context or
   *   configuration.
   */
  public function __construct(string|TranslatableMarkup $label, string|TranslatableMarkup $description, $required = TRUE, $multiple = FALSE, $default_value = NULL, array $constraints = [], ?InputDefinitionInterface $item_definition = NULL, bool $locked = FALSE) {
    parent::__construct('list', $label, $required, $multiple, $description, $default_value, $constraints, $item_definition);
    $this->isLocked = $locked;
  }

}
