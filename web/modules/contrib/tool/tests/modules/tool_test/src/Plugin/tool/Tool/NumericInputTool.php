<?php

declare(strict_types=1);

namespace Drupal\tool_test\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * A tool with a required numeric input for testing validation.
 */
#[Tool(
  id: 'numeric_input_tool',
  label: new TranslatableMarkup('Numeric Input Tool'),
  description: new TranslatableMarkup('A test tool with a required numeric input and output.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'number' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Number'),
      description: new TranslatableMarkup('A required numeric field.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'doubled' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Doubled'),
      description: new TranslatableMarkup('The input number doubled.'),
    ),
  ],
)]
final class NumericInputTool extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $doubled = $values['number'] * 2;
    return ExecutableResult::success(
      new TranslatableMarkup('Executed with number: @number', ['@number' => $values['number']]),
      ['doubled' => $doubled]
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

}
