<?php

namespace Drupal\tool;

use Drupal\Core\Plugin\Context\ContextDefinitionInterface;

/**
 * Defines an interface for plugins with typed output definitions.
 */
interface TypedOutputsDefinitionInterface {

  /**
   * Gets the definitions of all output contexts.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]
   *   An array of context definitions keyed by their names.
   */
  public function getOutputDefinitions(): array;

  /**
   * Gets the definition of a specific output context by name.
   *
   * @param string $name
   *   The name of the output context.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface
   *   The context definition object.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   If the context definition does not exist.
   */
  public function getOutputDefinition(string $name): ContextDefinitionInterface;

  /**
   * Checks if a specific output context definition exists by name.
   *
   * @param string $name
   *   The name of the output context.
   *
   * @return bool
   *   TRUE if the context definition exists, FALSE otherwise.
   */
  public function hasOutputDefinition(string $name): bool;

  /**
   * Adds a new output context definition.
   *
   * @param string $name
   *   The name of the output context.
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface $definition
   *   The context definition object.
   *
   * @return $this
   *   The current instance for chaining.
   */
  public function addOutputDefinition(string $name, ContextDefinitionInterface $definition): static;

  /**
   * Removes a specific output context definition by name.
   *
   * @param string $name
   *   The name of the output context.
   *
   * @return $this
   *   The current instance for chaining.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   If the context definition does not exist.
   */
  public function removeOutputDefinition(string $name): static;

}
