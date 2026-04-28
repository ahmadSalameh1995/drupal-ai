<?php

namespace Drupal\tool;

use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Plugin\Context\Context;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Provides typed outputs functionality for plugins.
 */
trait TypedOutputsTrait {

  /**
   * The data objects representing the context of this plugin.
   *
   * @var \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]
   */
  protected array $outputs = [];

  /**
   * {@inheritdoc}
   */
  public function setOutputValue(string $name, mixed $value): static {
    $context = Context::createFromContext($this->getOutput($name), $value);
    $this->outputs[$name] = $context;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputs(): array {
    // Make sure all context objects are initialized.
    foreach ($this->getOutputDefinitions() as $name => $definition) {
      $this->getOutput($name);
    }
    return $this->outputs;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutput(string $name): ContextInterface {
    // Check for a valid context value.
    if (!isset($this->outputs[$name])) {
      $this->outputs[$name] = new Context($this->getOutputDefinition($name));
    }
    return $this->outputs[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputValue(string $name): mixed {
    if (!isset($this->outputs[$name]) || !$this->outputs[$name] instanceof ContextInterface) {
      throw new ContextException(sprintf("The provided context '%s' is not valid.", $name));
    }
    return $this->outputs[$name]->getContextValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputValues(): array {
    $values = [];
    foreach ($this->getOutputDefinitions() as $name => $definition) {
      $values[$name] = $this->getOutputValue($name);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(string $name): ContextDefinitionInterface {
    $definition = $this->getPluginDefinition();
    assert($definition instanceof TypedOutputsDefinitionInterface);
    return $definition->getOutputDefinition($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinitions(): array {
    $definition = $this->getPluginDefinition();
    assert($definition instanceof TypedOutputsDefinitionInterface);
    return $definition->getOutputDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function validateOutputs(): ConstraintViolationList {
    $violations = new ConstraintViolationList();
    // @todo Implement the Symfony Validator component to let the validator
    //   traverse and set property paths accordingly.
    //   See https://www.drupal.org/project/drupal/issues/3153847.
    foreach ($this->getOutputs() as $context) {
      /** @var \Drupal\Core\Plugin\Context\ContextInterface $context */
      $violations->addAll($context->validate());
    }
    return $violations;
  }

}
