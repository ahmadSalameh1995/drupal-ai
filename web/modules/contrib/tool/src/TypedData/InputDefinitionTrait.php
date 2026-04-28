<?php

namespace Drupal\tool\TypedData;

/**
 * Trait for input definitions.
 */
trait InputDefinitionTrait {

  /**
   * Determines whether the value is locked.
   *
   * @var bool
   *   Whether a data value is locked.
   */
  protected $isLocked = FALSE;

  /**
   * {@inheritdoc}
   */
  public function isLocked(): bool {
    return $this->isLocked;
  }

  /**
   * {@inheritdoc}
   */
  public function setLocked($locked = TRUE): static {
    $this->isLocked = $locked;
    return $this;
  }

}
