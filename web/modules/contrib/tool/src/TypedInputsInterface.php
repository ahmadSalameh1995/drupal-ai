<?php

namespace Drupal\tool;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\tool\Exception\InputException;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Defines an interface for plugins with typed inputs.
 */
interface TypedInputsInterface extends ConfigurableInterface {

  /**
   * Sets the input definitions.
   *
   * @param \Drupal\tool\TypedData\InputDefinitionInterface[] $input_definitions
   *   An array of input definitions keyed by their names.
   *
   * @return $this
   *   The current instance for method chaining.
   */
  public function setInputDefinitions(array $input_definitions): static;

  /**
   * Checks if a specific input definition exists by name.
   *
   * @param string $name
   *   The name of the input definition.
   *
   * @return bool
   *   TRUE if the input definition exists, FALSE otherwise.
   */
  public function hasInputDefinition(string $name): bool;

  /**
   * Gets the input definitions.
   *
   * @return \Drupal\tool\TypedData\InputDefinitionInterface[]
   *   An array of input definitions keyed by their names.
   */
  public function getInputDefinitions(bool $include_locked = FALSE): array;

  /**
   * Gets a specific input definition by name.
   *
   * @param string $name
   *   The name of the input definition.
   *
   * @return \Drupal\tool\TypedData\InputDefinitionInterface|InputException
   *   The input definition object or an InputException if not found.
   */
  public function getInputDefinition(string $name): InputDefinitionInterface|InputException;

  /**
   * Gets the executable values for the inputs.
   *
   * @param bool $skip_validation
   *   Whether to skip validation of the values.
   *
   * @return array<string, mixed>
   *   An array of executable values.
   */
  public function getExecutableValues(bool $skip_validation = FALSE): array;

  /**
   * Gets the executable value for a specific input by name.
   *
   * @param string $name
   *   The name of the input.
   * @param bool $skip_validation
   *   Whether to skip validation of the value.
   *
   * @return mixed
   *   The executable value.
   */
  public function getExecutableValue(string $name, bool $skip_validation = FALSE): mixed;

  /**
   * Prepares a value for execution based on its input definition.
   *
   * @param string $name
   *   The name of the input.
   * @param \Drupal\tool\TypedData\InputDefinitionInterface $definition
   *   The input definition.
   * @param mixed $value
   *   The value to prepare.
   * @param string $source
   *   The source of the value.
   *
   * @return mixed
   *   The prepared executable value.
   */
  public function prepareExecutableValue(string $name, InputDefinitionInterface $definition, mixed $value, string $source): mixed;

  /**
   * Gets the configuration for the typed inputs.
   *
   * @return array<string, mixed>
   *   An array of configuration settings.
   */
  public function getConfiguration(): array;

  /**
   * Sets the configuration for the typed inputs.
   *
   * @param array<string, mixed> $configuration
   *   An array of configuration settings.
   *
   * @return $this
   *   The current instance for method chaining.
   */
  public function setConfiguration(array $configuration): static;

  /**
   * Provides the default configuration for the typed inputs.
   *
   * @return array<string, mixed>
   *   An array of default configuration settings.
   */
  public function defaultConfiguration(): array;

  /**
   * Gets all input contexts.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   An array of input contexts keyed by their names.
   */
  public function getInputs(): array;

  /**
   * Gets a specific input context by name.
   *
   * @param string $name
   *   The name of the input context.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface
   *   The input context object.
   */
  public function getInput(string $name): ContextInterface;

  /**
   * Sets the context for a specific input by name.
   *
   * @param string $name
   *   The name of the context.
   * @param \Drupal\Core\Plugin\Context\ContextInterface $input
   *   The context object to set.
   *
   * @return $this
   *   The current instance for method chaining.
   */
  public function setInput(string $name, ContextInterface $input): static;

  /**
   * Checks if a specific input has a value set.
   *
   * @param string $name
   *   The name of the input.
   *
   * @return bool
   *   TRUE if the input has a value, FALSE otherwise.
   */
  public function hasInputValue(string $name): bool;

  /**
   * Sets the value for a specific input by name.
   *
   * @param string $name
   *   The name of the context.
   * @param mixed $value
   *   The value to set for the context.
   *
   * @return $this
   *   The current instance for method chaining.
   */
  public function setInputValue(string $name, mixed $value): static;

  /**
   * Gets the value of a specific input by name.
   *
   * @param string $name
   *   The name of the input.
   *
   * @return mixed
   *   The value of the input.
   */
  public function getInputValue(string $name): mixed;

  /**
   * Gets all input values.
   *
   * @return array<string, mixed>
   *   An array of input values keyed by their names.
   */
  public function getInputValues(): array;

  /**
   * Validate the provided input values.
   *
   * @param array<string, mixed> $values
   *   The keyed input values to validate.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   A list of constraint violations, if any.
   */
  public function validateInputValues(array $values): ConstraintViolationListInterface;

  /**
   * Validates a specific input value by name.
   *
   * @param \Drupal\tool\TypedData\InputDefinitionInterface $definition
   *   The input definition.
   * @param mixed $value
   *   The value to validate.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   A list of constraint violations, if any.
   */
  public function validateInputValue(InputDefinitionInterface $definition, mixed $value): ConstraintViolationListInterface;

}
