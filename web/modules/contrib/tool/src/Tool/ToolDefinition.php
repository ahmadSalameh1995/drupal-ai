<?php

namespace Drupal\tool\Tool;

use Drupal\Component\Plugin\Definition\DerivablePluginDefinitionInterface;
use Drupal\Component\Plugin\Definition\PluginDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\tool\TypedInputsDefinitionInterface;
use Drupal\tool\TypedOutputsDefinitionInterface;

/**
 * Defines a tool plugin definition.
 */
class ToolDefinition extends PluginDefinition implements DerivablePluginDefinitionInterface, TypedInputsDefinitionInterface, TypedOutputsDefinitionInterface {

  /**
   * The human-readable name.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected TranslatableMarkup $label;

  /**
   * A description of the tool.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected TranslatableMarkup $description;
  /**
   * The input definitions for this plugin definition.
   *
   * @var \Drupal\tool\TypedData\InputDefinitionInterface[]
   */
  protected array $inputDefinitions = [];

  /**
   * The output definitions for this plugin definition.
   *
   * @var \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]
   */
  protected array $outputDefinitions = [];

  /**
   * An array of form class names or FALSE, keyed by a string.
   *
   * @var array<string, string|false>
   */
  protected array $forms = [];

  /**
   * Whether the tool is destructive and requires confirmation.
   *
   * @var bool
   */
  protected bool $destructive = FALSE;

  /**
   * Input definition refiners configuration for dynamic input refinement.
   *
   * @var array
   */
  protected array $inputDefinitionRefiners = [];

  /**
   * The operation type that defines the nature and expectations of the tool.
   *
   * @var \Drupal\tool\Tool\ToolOperation
   */
  protected ToolOperation $operation;

  /**
   * The name of the deriver of this tool definition, if any.
   *
   * @var string|null
   */
  protected string|null $deriver;

  /**
   * Constructs a new tool definition object.
   *
   * @param array $definition
   *   An array of values from the attribute.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   When input definition refiners reference non-existent input definitions.
   */
  public function __construct(array $definition) {
    if (isset($definition['input_definitions'])) {
      foreach ($definition['input_definitions'] as $name => $input_definition) {
        $this->addInputDefinition($name, $input_definition);
      }
      unset($definition['input_definitions']);
    }

    if (isset($definition['output_definitions'])) {
      foreach ($definition['output_definitions'] as $name => $output_definition) {
        $this->addOutputDefinition($name, $output_definition);
      }
      unset($definition['output_definitions']);
    }

    if (isset($definition['input_definition_refiners'])) {
      $this->setInputDefinitionRefiners($definition['input_definition_refiners']);
      unset($definition['input_definition_refiners']);
    }

    foreach ($definition as $property => $value) {
      $this->set($property, $value);
    }
  }

  /**
   * Gets any arbitrary property.
   *
   * @param string $property
   *   The property to retrieve.
   *
   * @return mixed
   *   The value for that property, or NULL if the property does not exist.
   */
  public function get(string $property): mixed {
    return $this?->{$property} ?? NULL;
  }

  /**
   * Sets a value to an arbitrary property.
   *
   * @param string $property
   *   The property to use for the value.
   * @param mixed $value
   *   The value to set.
   *
   * @return $this
   *   The current instance.
   */
  public function set(string $property, mixed $value): static {
    if (property_exists($this, $property)) {
      $this->{$property} = $value;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasInputDefinition(string $name): bool {
    return array_key_exists($name, $this->inputDefinitions);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinitions(bool $include_locked = FALSE): array {
    if ($include_locked) {
      return $this->inputDefinitions;
    }
    return array_filter($this->inputDefinitions, function ($definition) {
      return !$definition->isLocked();
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(string $name): InputDefinitionInterface {
    if ($this->hasInputDefinition($name)) {
      return $this->inputDefinitions[$name];
    }
    throw new ContextException($this->id() . " does not define a '$name' input");
  }

  /**
   * {@inheritdoc}
   */
  public function addInputDefinition(string $name, InputDefinitionInterface $definition): static {
    $this->inputDefinitions[$name] = $definition;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeInputDefinition(string $name): static {
    unset($this->inputDefinitions[$name]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasOutputDefinition(string $name): bool {
    return array_key_exists($name, $this->outputDefinitions);
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinitions(): array {
    return $this->outputDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(string $name): ContextDefinitionInterface {
    if ($this->hasOutputDefinition($name)) {
      return $this->outputDefinitions[$name];
    }
    throw new ContextException($this->id() . " does not define a '$name' output");
  }

  /**
   * {@inheritdoc}
   */
  public function addOutputDefinition(string $name, ContextDefinitionInterface $definition): static {
    $this->outputDefinitions[$name] = $definition;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeOutputDefinition(string $name): static {
    unset($this->outputDefinitions[$name]);
    return $this;
  }

  /**
   * Gets the human-readable name of the tool definition.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The human-readable name of the tool definition.
   */
  public function getLabel(): TranslatableMarkup {
    return $this->label;
  }

  /**
   * Sets the human-readable name of the tool definition.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the tool definition.
   *
   * @return $this
   */
  public function setLabel(TranslatableMarkup $label): static {
    $this->label = $label;
    return $this;
  }

  /**
   * Gets the description of the tool definition.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The description of the tool definition.
   */
  public function getDescription(): TranslatableMarkup {
    return $this->description;
  }

  /**
   * Sets the description of the tool definition.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of the tool definition.
   *
   * @return $this
   */
  public function setDescription(TranslatableMarkup $description): static {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeriver(): ?string {
    if (empty($this->deriver)) {
      return NULL;
    }
    return $this->deriver;
  }

  /**
   * {@inheritdoc}
   */
  public function setDeriver($deriver): static {
    $this->deriver = $deriver;
    return $this;
  }

  /**
   * Gets the forms for this tool definition.
   *
   * @return array<string, string|false>
   *   An array of form class names or FALSE, keyed by a string.
   */
  public function getForms(): array {
    return $this->forms;
  }

  /**
   * Gets a specific form class name by its.
   */
  public function getForm(string $name): ?string {
    return $this->forms[$name] ?? NULL;
  }

  /**
   * Sets the forms for this tool definition.
   *
   * @param array<string, string|false> $forms
   *   An array of form class names or FALSE, keyed by a string.
   *
   * @return $this
   */
  public function setForms(array $forms): static {
    $this->forms = $forms;
    return $this;
  }

  /**
   * Adds a form to this tool definition.
   *
   * @param string $name
   *   The form name.
   * @param string $form_class
   *   The form class name.
   *
   * @return $this
   *   The current instance.
   */
  public function addForm(string $name, string $form_class): static {
    $this->forms[$name] = $form_class;
    return $this;
  }

  /**
   * Removes a form from this tool definition.
   *
   * @param string $name
   *   The form name to remove.
   *
   * @return $this
   */
  public function removeForm(string $name): static {
    unset($this->forms[$name]);
    return $this;
  }

  /**
   * Gets whether this tool is destructive.
   *
   * @return bool
   *   TRUE if the tool is destructive and requires confirmation.
   */
  public function isDestructive(): bool {
    return $this->destructive;
  }

  /**
   * Sets whether this tool is destructive.
   *
   * @param bool $destructive
   *   TRUE if the tool is destructive and requires confirmation.
   *
   * @return $this
   */
  public function setDestructive(bool $destructive): static {
    $this->destructive = $destructive;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinitionRefiners(): array {
    return $this->inputDefinitionRefiners;
  }

  /**
   * {@inheritdoc}
   */
  public function setInputDefinitionRefiners(array $input_definition_refiners): static {
    // Validate that all referenced inputs exist in input definitions.
    foreach ($input_definition_refiners as $input_name => $dependencies) {
      if (!$this->hasInputDefinition($input_name)) {
        throw new ContextException("Input definition refiner references non-existent input definition '{$input_name}'");
      }
      foreach ($dependencies as $dependency) {
        if (!$this->hasInputDefinition($dependency)) {
          throw new ContextException("Input definition refiner for '{$input_name}' references non-existent dependency '{$dependency}'");
        }
      }
    }

    $this->inputDefinitionRefiners = $input_definition_refiners;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinitionRefiner(string $input_name): ?array {
    return $this->inputDefinitionRefiners[$input_name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function addInputDefinitionRefiner(string $input_name, array $dependencies): static {
    // Validate that the input and its dependencies exist.
    if (!$this->hasInputDefinition($input_name)) {
      throw new ContextException("Input definition refiner references non-existent input definition '{$input_name}'");
    }
    foreach ($dependencies as $dependency) {
      if (!$this->hasInputDefinition($dependency)) {
        throw new ContextException("Input definition refiner for '{$input_name}' references non-existent dependency '{$dependency}'");
      }
    }

    $this->inputDefinitionRefiners[$input_name] = $dependencies;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeInputDefinitionRefiner(string $input_name): static {
    unset($this->inputDefinitionRefiners[$input_name]);
    return $this;
  }

  /**
   * Gets the operation type for this tool.
   *
   * @return \Drupal\tool\Tool\ToolOperation
   *   The operation type that defines the nature and expectations of the tool.
   */
  public function getOperation(): ToolOperation {
    return $this->operation ?? ToolOperation::Transform;
  }

  /**
   * Sets the operation type for this tool.
   *
   * @param \Drupal\tool\Tool\ToolOperation $operation
   *   The operation type that defines the nature and expectations of the tool.
   *
   * @return $this
   */
  public function setOperation(ToolOperation $operation): static {
    $this->operation = $operation;
    return $this;
  }

}
