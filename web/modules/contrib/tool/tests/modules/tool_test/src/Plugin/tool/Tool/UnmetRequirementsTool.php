<?php

declare(strict_types=1);

namespace Drupal\tool_test\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Exception\RequirementsException;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

/**
 * A tool with unmet requirements, used for testing.
 */
#[Tool(
  id: 'unmet_requirements_tool',
  label: new TranslatableMarkup('Unmet Requirements Tool'),
  description: new TranslatableMarkup('A test tool whose requirements are never met.'),
  operation: ToolOperation::Read,
)]
final class UnmetRequirementsTool extends ToolBase {

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): void {
    throw new RequirementsException('Test API key is not configured.');
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return ExecutableResult::success(new TranslatableMarkup('Done.'));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

}
