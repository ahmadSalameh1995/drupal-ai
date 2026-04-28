<?php

namespace Drupal\tool;

use Drupal\Core\Executable\ExecutableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an interface for executable results.
 */
interface ExecutableResultInterface extends ExecutableInterface {

  /**
   * Gets the result of the execution.
   *
   * @return \Drupal\tool\ExecutableResult
   *   The result of the execution.
   */
  public function getResult(): ExecutableResult;

  /**
   * Gets the status of the result.
   *
   * @return bool
   *   TRUE if the execution was successful, FALSE otherwise.
   */
  public function getResultStatus(): bool;

  /**
   * Gets the message associated with the result.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message associated with the result.
   */
  public function getResultMessage(): TranslatableMarkup;

}
