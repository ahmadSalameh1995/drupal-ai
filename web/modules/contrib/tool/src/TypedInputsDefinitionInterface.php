<?php

namespace Drupal\tool;

use Drupal\tool\TypedData\InputDefinitionInterface;

/**
 * Interface for typed input definitions.
 */
interface TypedInputsDefinitionInterface {

  /**
   * Checks if an input definition exists.
   *
   * @param string $name
   *   The name of the input definition.
   *
   * @return bool
   *   TRUE if the input definition exists, FALSE otherwise.
   */
  public function hasInputDefinition(string $name): bool;

  /**
   * Gets input definitions.
   *
   * @param bool $include_locked
   *   Whether to include locked inputs.
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
   * @return \Drupal\tool\TypedData\InputDefinitionInterface
   *   The input definition object.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   If the input definition does not exist.
   */
  public function getInputDefinition(string $name): InputDefinitionInterface;

  /**
   * Adds a new input definition.
   *
   * @param string $name
   *   The name of the input definition.
   * @param \Drupal\tool\TypedData\InputDefinitionInterface $definition
   *   The input definition object.
   *
   * @return $this
   *   The current instance for chaining.
   */
  public function addInputDefinition(string $name, InputDefinitionInterface $definition): static;

  /**
   * Removes an input definition by name.
   *
   * @param string $name
   *   The name of the input definition to remove.
   *
   * @return $this
   *   The current instance for chaining.
   */
  public function removeInputDefinition(string $name): static;

  /**
   * Gets input definition refiners.
   *
   * @return array<string, array<string>>
   *   An array of input definition refiners.
   */
  public function getInputDefinitionRefiners(): array;

  /**
   * Sets the input definition refiners configuration.
   *
   * @param array<string, array<string>> $input_definition_refiners
   *   The input definition refiners configuration array.
   *
   * @return $this
   *   The current instance for method chaining.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   When input definition refiners reference non-existent input definitions.
   */
  public function setInputDefinitionRefiners(array $input_definition_refiners): static;

  /**
   * Adds an input definition refiner for a specific input.
   *
   * @param string $input_name
   *   The input name to add an input definition refiner for.
   * @param string[] $dependencies
   *   The array of input dependencies.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   When input definition refiners reference non-existent input definitions.
   */
  public function addInputDefinitionRefiner(string $input_name, array $dependencies): static;

  /**
   * Gets input definition refiners for a specific input.
   *
   * @param string $input_name
   *   The input name to get input definition refiners for.
   *
   * @return string[]|null
   *   The array of input dependencies for the given input, or NULL if none.
   */
  public function getInputDefinitionRefiner(string $input_name): ?array;

  /**
   * Removes an input definition refiner for a specific input.
   *
   * @param string $input_name
   *   The input name to remove the input definition refiner for.
   *
   * @return $this
   *   The current instance.
   */
  public function removeInputDefinitionRefiner(string $input_name): static;

}
