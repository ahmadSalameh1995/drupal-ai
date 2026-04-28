<?php

declare(strict_types=1);

namespace Drupal\tool\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinitionRefinerInterface;

/**
 * The tool attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Tool extends Plugin {

  /**
   * Constructs a new Tool instance.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is
   *   "foo" the ID must be either "foo" or "foo:bar".
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   A brief description of the plugin.
   * @param \Drupal\tool\Tool\ToolOperation $operation
   *   The operation type that defines the nature and expectations
   *    of the tool.
   * @param bool $destructive
   *   (optional) Whether the tool is destructive. Encourage confirmation.
   * @param \Drupal\tool\TypedData\InputDefinitionInterface[] $input_definitions
   *   (optional) An array of context definitions describing the context.
   * @param array|null $input_definition_refiners
   *   (optional) Input definition refiners configuration for dynamic input
   *    refinement.
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface[] $output_definitions
   *   (optional) Leave empty, will be auto filled by $input_definitions.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param array<string, string|false> $forms
   *   (optional) An array of form class names or FALSE, keyed by a string.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
    public readonly ToolOperation $operation,
    public readonly bool $destructive = FALSE,
    public readonly ?array $input_definitions = [],
    public readonly ?array $input_definition_refiners = [],
    public readonly ?array $output_definitions = [],
    public readonly ?string $deriver = NULL,
    public readonly ?array $forms = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(): ToolDefinition {
    $definition_array = parent::get();

    // If input definition refiners are specified, assert that the plugin class
    // implements InputDefinitionRefinerInterface.
    if (!empty($this->input_definition_refiners) && isset($definition_array['class'])) {
      $class = $definition_array['class'];
      if (!is_subclass_of($class, InputDefinitionRefinerInterface::class)) {
        throw new \InvalidArgumentException(
          "Plugin class '{$class}' must implement InputDefinitionRefinerInterface when input_definition_refiners are defined."
        );
      }
    }

    return new ToolDefinition($definition_array);
  }

}
