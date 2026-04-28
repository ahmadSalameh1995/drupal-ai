<?php

namespace Drupal\tool;

use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Defines an interface for plugins with typed outputs.
 */
interface TypedOutputsInterface {

  /**
   * Sets the value for a specific output context.
   *
   * @param string $name
   *   The name of the output context.
   * @param mixed $value
   *   The value to set for the output context.
   *
   * @return $this
   *   The current instance for method chaining.
   */
  public function setOutputValue(string $name, mixed $value): static;

  /**
   * Gets all output contexts.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]
   *   An array of output contexts keyed by their names.
   */
  public function getOutputs(): array;

  /**
   * Gets a specific output context by name.
   *
   * @param string $name
   *   The name of the output context.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface
   *   The output context object.
   */
  public function getOutput(string $name): ContextInterface;

  /**
   * Gets the value of a specific output context.
   *
   * @param string $name
   *   The name of the output context.
   *
   * @return mixed
   *   The value of the output context.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   If the context does not exist.
   */
  public function getOutputValue(string $name): mixed;

  /**
   * Gets all output values.
   *
   * @return array<string, mixed>
   *   An array of output values keyed by their names.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *    If the context does not exist.
   */
  public function getOutputValues(): array;

  /**
   * Gets the definition of a specific output context.
   *
   * @param string $name
   *   The name of the output context.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface
   *   The output context definition object.
   */
  public function getOutputDefinition(string $name): ContextDefinitionInterface;

  /**
   * Gets all output definitions.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]
   *   An array of output context definitions keyed by their names.
   */
  public function getOutputDefinitions(): array;

  /**
   * Validates the output values against their definitions.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationList
   *   A list of constraint violations, if any.
   */
  public function validateOutputs(): ConstraintViolationList;

}
