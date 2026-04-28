<?php

declare(strict_types=1);

namespace Drupal\tool\Tool;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tool\ExecutableResultInterface;
use Drupal\tool\TypedInputsInterface;
use Drupal\tool\TypedOutputsInterface;

/**
 * Interface for tool plugins.
 */
interface ToolInterface extends ExecutableResultInterface, TypedInputsInterface, TypedOutputsInterface, CacheableDependencyInterface, PluginWithFormsInterface {

  /**
   * Checks that requirements for this tool are met.
   *
   * Override this method to verify static prerequisites such as API keys or
   * Drupal configuration values being present. This is not intended to
   * validate runtime state (e.g. stale credentials, network availability).
   *
   * @throws \Drupal\tool\Exception\RequirementsException
   *   Thrown when a required configuration dependency is absent, with a
   *   message describing what is missing.
   */
  public function checkRequirements(): void;

  /**
   * Checks access for the tool.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account to check access for. If NULL, current user should be used.
   * @param bool $return_as_object
   *   Whether to return the access result as an object.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The Result.
   */
  public function access(?AccountInterface $account = NULL, bool $return_as_object = FALSE): bool|AccessResultInterface;

}
