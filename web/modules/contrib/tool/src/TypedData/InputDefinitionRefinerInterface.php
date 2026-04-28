<?php

declare(strict_types=1);

namespace Drupal\tool\TypedData;

/**
 * Interface for tools that support input refinement.
 *
 * Tools implementing this interface can dynamically refine their input
 * definitions based on the values of other inputs.
 */
interface InputDefinitionRefinerInterface {

  /**
   * Refine input definitions based on current input values.
   *
   * This method is called when input refinement is needed, allowing tools
   * to dynamically modify their input definitions based on the current
   * values of other inputs.
   *
   * @param string $name
   *   The name of the input to be refined.
   * @param \Drupal\tool\TypedData\InputDefinitionInterface $definition
   *   The input definition to be refined.
   * @param array $values
   *   An associative array of current input values, keyed by input names.
   *
   * @return \Drupal\tool\TypedData\InputDefinitionInterface
   *   The refined input definition.
   */
  public function refineInputDefinition(string $name, InputDefinitionInterface $definition, array $values): InputDefinitionInterface;

}
