<?php

namespace Drupal\tool\TypedData;

use Drupal\Core\Plugin\Context\ContextDefinitionInterface;

/**
 * Defines an interface for input definitions.
 */
interface InputDefinitionInterface extends ContextDefinitionInterface {

  /**
   * Returns if the input is locked.
   *
   * @return bool
   *   Returns TRUE if the input is locked, FALSE otherwise.
   */
  public function isLocked(): bool;

  /**
   * Sets whether the value is locked.
   *
   * @param bool $locked
   *   Whether a data value is locked.
   *
   * @return $this
   */
  public function setLocked($locked = TRUE): static;

}
