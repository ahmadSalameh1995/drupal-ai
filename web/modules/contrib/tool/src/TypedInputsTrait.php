<?php

namespace Drupal\tool;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\tool\Exception\InputException;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\InputDefinitionRefinerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Provides typed inputs functionality for plugins.
 */
trait TypedInputsTrait {

  use DependencySerializationTrait;
  use TypedDataTrait;

  const STORAGE_CONFIGURATION = 'config';
  const STORAGE_INPUT = 'input';
  const INPUT_SOURCE_INPUT = 'input';
  const INPUT_SOURCE_CONFIG = 'config';
  const INPUT_SOURCE_LOCKED = 'locked';
  const INPUT_SOURCE_DEFAULT = 'default';

  /**
   * The input definitions for this plugin.
   *
   * @var \Drupal\tool\TypedData\InputDefinitionInterface[]
   */
  protected ?array $inputDefinitions = NULL;

  /**
   * The storage for input and configuration values.
   *
   * @var array<string, array<string, \Drupal\Core\Plugin\Context\ContextInterface>>
   */
  protected array $storage = [];

  /**
   * The form adapter instances for the input definitions.
   *
   * @var \Drupal\tool\TypedData\Adapter\TypedDataAdapterInterface[]|null
   */
  protected ?array $formAdapterInstances = NULL;

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    $configuration = [];
    foreach (array_keys($this->getInputDefinitions()) as $name) {
      $configuration[$name] = $this->getConfigurationValue($name);
    }
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): static {
    $default_values = $this->defaultConfiguration();
    foreach (array_keys($this->getInputDefinitions()) as $name) {
      if (array_key_exists($name, $configuration)) {
        $this->setConfigurationValue($name, $configuration[$name], TRUE);
      }
      else {
        if (array_key_exists($name, $default_values)) {
          $this->setConfigurationValue($name, $default_values[$name], TRUE);
        }
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return array_map(function ($definition) {
      return $definition->getDefaultValue();
    }, $this->getInputDefinitions());
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutableValues(bool $skip_validation = FALSE): array {
    $values = [];
    foreach ($this->getInputDefinitions(TRUE) as $name => $definition) {
      $values[$name] = $this->getExecutableValue($name, TRUE);

    }
    if (!$skip_validation) {
      $violations = $this->validateInputValues($values);
      if ($violations->count() > 0) {
        throw new \InvalidArgumentException((string) $violations);
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutableValue(string $name, $skip_validation = FALSE): mixed {
    $definition = $this->getInputDefinition($name);
    if ($definition->isLocked()) {
      $value = $this->prepareExecutableValue($name, $definition, $definition->getDefaultValue(), self::INPUT_SOURCE_LOCKED);
    }
    // Next, check if an input value has been set.
    elseif (isset($this->storage[self::STORAGE_INPUT][$name])) {
      $value = $this->prepareExecutableValue($name, $definition, $this->storage[self::STORAGE_INPUT][$name]->getContextValue(), self::INPUT_SOURCE_INPUT);
    }
    // Next, check if a configuration value has been set.
    elseif ($this->hasConfigurationValue($name)) {
      $value = $this->prepareExecutableValue($name, $definition, $this->getConfigurationValue($name)->getValue(), self::INPUT_SOURCE_CONFIG);
    }
    // Finally, use the default value.
    else {
      $value = $this->prepareExecutableValue($name, $definition, $definition->getDefaultValue(), self::INPUT_SOURCE_DEFAULT);
    }
    if (!$skip_validation) {
      $violations = $this->validateInputValue($definition, $value);
      if ($violations->count() > 0) {
        throw new \InvalidArgumentException((string) $violations);
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareExecutableValue(string $name, InputDefinitionInterface $definition, mixed $value, $source): mixed {
    // Source should be one of: input, config, locked, default.
    $allowed_sources = [
      self::INPUT_SOURCE_INPUT,
      self::INPUT_SOURCE_CONFIG,
      self::INPUT_SOURCE_LOCKED,
      self::INPUT_SOURCE_DEFAULT,
    ];
    if (!in_array($source, $allowed_sources, TRUE)) {
      throw new \InvalidArgumentException('Source must be one of: input, config, locked, default.');
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setInputDefinitions(array $input_definitions): static {
    $old_input_definitions = $this->inputDefinitions ?? [];
    $this->inputDefinitions = $input_definitions;
    foreach ($input_definitions as $name => $definition) {
      if (isset($old_input_definitions[$name]) && $old_input_definitions[$name] !== $definition && $this->hasInputValue($name)) {
        $value = $this->getInputValue($name);
        $this->setInput($name, new Context($definition, $value));
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasInputDefinition(string $name): bool {
    $inputs = $this->getInputDefinitions(TRUE);
    return isset($inputs[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinitions(bool $include_locked = FALSE): array {
    if (!isset($this->inputDefinitions)) {
      $definition = $this->getPluginDefinition();
      assert($definition instanceof TypedInputsDefinitionInterface);
      $this->inputDefinitions = $definition->getInputDefinitions(TRUE);
    }
    if (!$include_locked) {
      return array_filter($this->inputDefinitions, function ($definition) {
        return !$definition->isLocked();
      });
    }
    return $this->inputDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(string $name): InputDefinitionInterface|InputException {
    $inputs = $this->getInputDefinitions(TRUE);
    if (isset($inputs[$name])) {
      return $inputs[$name];
    }
    throw new InputException(sprintf("The %s context is not a valid context.", $name));
  }

  /**
   * {@inheritdoc}
   */
  public function getInputs(): array {
    // Make sure all input objects are initialized.
    foreach ($this->getInputDefinitions() as $name => $definition) {
      $this->getInput($name);
    }
    return $this->storage[self::STORAGE_INPUT] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getInput(string $name): ContextInterface {
    // Check for a valid input value.
    if (!isset($this->storage[self::STORAGE_INPUT][$name])) {
      $this->storage[self::STORAGE_INPUT][$name] = new Context($this->getInputDefinition($name));
    }
    return $this->storage[self::STORAGE_INPUT][$name];
  }

  /**
   * {@inheritdoc}
   */
  public function setInput(string $name, ContextInterface $input): static {
    $this->storage[self::STORAGE_INPUT][$name] = $input;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasInputValue(string $name): bool {
    return isset($this->storage[self::STORAGE_INPUT][$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputValues(): array {
    $values = [];
    foreach ($this->getInputDefinitions() as $name => $definition) {
      $values[$name] = isset($this->storage[self::STORAGE_INPUT][$name]) ? $this->storage[self::STORAGE_INPUT][$name]->getContextValue() : NULL;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputValue(string $name): mixed {
    return $this->getInput($name)->getContextValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setInputValue(string $name, $value): static {
    if ($this->getInputDefinition($name)->isLocked()) {
      throw new InputException(sprintf("The %s input is locked and cannot be changed.", $name));
    }
    $this->setInput($name, Context::createFromContext($this->getInput($name), $value));
    // If this plugin can refine input definitions, check if this input is a
    // dependency for any refiners and apply them if so.
    if ($this instanceof InputDefinitionRefinerInterface) {
      $input_definitions = $this->getInputDefinitions(TRUE);
      $plugin_definition = $this->getPluginDefinition();
      assert($plugin_definition instanceof TypedInputsDefinitionInterface);
      $refiners = $plugin_definition->getInputDefinitionRefiners();
      foreach ($refiners as $target => $dependencies) {
        if (in_array($name, $dependencies, TRUE)) {
          // Prepare the values for the dependencies and validate.
          $simulated_values = array_intersect_key($this->getExecutableValues(TRUE), array_flip($dependencies));
          $violations = $this->validateInputValues($simulated_values);
          // If no violations, refine the target input definition.
          if ($violations->count() === 0) {
            $input_definitions[$target] = $this->refineInputDefinition($target, $this->getInputDefinition($target), $simulated_values);
          }
          // If violations exist, revert to the original definition.
          else {
            $input_definitions[$target] = $plugin_definition->getInputDefinition($target);
          }
        }
      }
      $this->setInputDefinitions($input_definitions);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputValues($values): ConstraintViolationListInterface {
    // @todo test is giving property name on mapped values.
    $violations = new ConstraintViolationList();
    foreach ($values as $name => $value) {
      $definition = $this->getInputDefinition($name);
      $input_violations = $this->validateInputValue($definition, $value);
      if ($input_violations->count() > 0) {
        foreach ($input_violations as $key => $violation) {
          $input_violations[$key] = new ConstraintViolation(
            sprintf('Input %s: %s', $name, $violation->getMessage()),
            $violation->getMessageTemplate(),
            $violation->getParameters(),
            $violation->getRoot(),
            $violation->getPropertyPath(),
            $violation->getInvalidValue(),
            $violation->getPlural(),
            $violation->getCode(),
            $violation->getCause()
          );
        }
        $violations->addAll($input_violations);
      }
    }
    return $violations;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputValue(InputDefinitionInterface $definition, $value): ConstraintViolationListInterface {
    $violations = new ConstraintViolationList();
    // Skip validations for non-required NULL values.
    if (!$definition->isRequired() && $value === NULL) {
      return $violations;
    }
    if ($definition->isMultiple() && is_array($value)) {
      foreach ($value as $item) {
        $item_definition = clone $definition;
        $item_definition->setMultiple(FALSE);
        return $this->validateInputValue($item_definition, $item);
      }
    }
    else {
      try {
        $context = new Context($definition, $value);
        if ($context->getContextData()->getDataDefinition()->getDataType() === 'entity') {
          if (!$context->getContextData()->getValue() instanceof ContentEntityInterface) {
            $violations->add(new ConstraintViolation(
              sprintf('The %s input must be an entity.', $definition->getLabel()),
              '',
              [],
              NULL,
              '',
              NULL
            ));
          }
        }
        else {
          $violations->addAll($context->validate());
        }
      }
      catch (\Exception $e) {
        $violations->add(new ConstraintViolation(
          sprintf('The %s input is invalid: %s', $definition->getLabel(), $e->getMessage()),
          '',
          [],
          NULL,
          '',
          NULL
        ));
      }
    }
    return $violations;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheInputs(): array {
    $cache_inputs = [];
    // Applied inputs can affect the cache inputs when this plugin is
    // involved in caching, collect and return them.
    foreach ($this->getInputs() as $input) {
      /** @var \Drupal\Core\Cache\CacheableDependencyInterface $input */
      if ($input instanceof CacheableDependencyInterface) {
        $cache_inputs = Cache::mergeContexts($cache_inputs, $input->getCacheContexts());
      }
    }
    return $cache_inputs;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $tags = [];
    // Applied inputs can affect the cache tags when this plugin is
    // involved in caching, collect and return them.
    foreach ($this->getInputs() as $input) {
      /** @var \Drupal\Core\Cache\CacheableDependencyInterface $input */
      if ($input instanceof CacheableDependencyInterface) {
        $tags = Cache::mergeTags($tags, $input->getCacheTags());
      }
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    $max_age = Cache::PERMANENT;
    // Applied inputs can affect the cache max age when this plugin is
    // involved in caching, collect and return them.
    foreach ($this->getInputs() as $input) {
      /** @var \Drupal\Core\Cache\CacheableDependencyInterface $input */
      if ($input instanceof CacheableDependencyInterface) {
        $max_age = Cache::mergeMaxAges($max_age, $input->getCacheMaxAge());
      }
    }
    return $max_age;
  }

  /**
   * Gets a specific configuration value by name.
   *
   * @param string $name
   *   The name of the configuration value.
   * @param bool $return_typed_data
   *   Whether to return the value as typed data.
   *
   * @return mixed
   *   The configuration value, or NULL if not set.
   *
   * @throws \Drupal\tool\Exception\InputException
   */
  public function getConfigurationValue(string $name, bool $return_typed_data = FALSE): mixed {
    // Get the configuration value for the given name.
    if ($this->hasConfigurationValue($name)) {
      return $return_typed_data ? $this->storage[self::STORAGE_CONFIGURATION][$name]->getContextValue() : $this->storage[self::STORAGE_CONFIGURATION][$name];
    }
    throw new InputException("Configuration value not set for key: $name");
  }

  /**
   * Sets a configuration value.
   *
   * @param string $name
   *   The name of the configuration value.
   * @param mixed $value
   *   The value to set for the configuration.
   * @param bool $skip_validation
   *   Whether to skip validation for this value.
   *
   * @return $this
   *   The current instance for method chaining.
   *
   * @throws \Drupal\tool\Exception\InputException
   *   If validation fails for the provided value.
   */
  public function setConfigurationValue(string $name, mixed $value, bool $skip_validation = FALSE): static {
    // If isset in getInputDefinitions(), validate constraints.
    if (!$skip_validation) {
      $violations = $this->validateInputValue($this->getInputDefinition($name), $value);
      if ($violations->count() > 0) {
        // If there are violations, throw an exception or handle them as needed.
        foreach ($violations as $violation) {
          throw new InputException((string) $violation->getMessage());
        }
      }
    }
    if ($this->hasConfigurationValue($name)) {
      $this->storage[self::STORAGE_CONFIGURATION][$name] = Context::createFromContext($this->storage[self::STORAGE_CONFIGURATION][$name], $value);
    }
    elseif ($this->hasInputDefinition($name)) {
      $this->storage[self::STORAGE_CONFIGURATION][$name] = new Context($this->getInputDefinition($name), $value);
    }
    // @todo Check what to do if definition doesn't exist.
    return $this;
  }

  /**
   * Checks if a configuration value exists.
   *
   * @param string $name
   *   The name of the configuration value to check.
   *
   * @return bool
   *   TRUE if the configuration value exists, FALSE otherwise.
   */
  public function hasConfigurationValue(string $name): bool {
    return isset($this->storage[self::STORAGE_CONFIGURATION][$name]);
  }

}
