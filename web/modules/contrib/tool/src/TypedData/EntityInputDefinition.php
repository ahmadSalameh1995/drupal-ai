<?php

namespace Drupal\tool\TypedData;

use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an entity input definition.
 */
class EntityInputDefinition extends EntityContextDefinition implements InputDefinitionInterface {
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
  public function __construct($data_type, string|TranslatableMarkup $label, string|TranslatableMarkup $description, $required = TRUE, $multiple = FALSE, $default_value = NULL, array $constraints = [], bool $locked = FALSE) {
    parent::__construct($data_type, $label, $required, $multiple, $description, $default_value, $constraints);
    $this->isLocked = $locked;
  }

}
