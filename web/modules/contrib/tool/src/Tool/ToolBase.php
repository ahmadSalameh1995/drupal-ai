<?php

declare(strict_types=1);

namespace Drupal\tool\Tool;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\TypedOutputsTrait;
use Drupal\tool\ExecutableResult;
use Drupal\tool\TypedInputsTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for tool plugins.
 */
abstract class ToolBase extends PluginBase implements ToolInterface, ContainerFactoryPluginInterface {
  use PluginWithFormsTrait;
  use TypedInputsTrait;
  use TypedOutputsTrait;

  /**
   * The result of the tool execution.
   *
   * @var \Drupal\tool\ExecutableResult|null
   */
  protected ?ExecutableResult $result;

  /**
   * Constructs a ToolBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  final public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): void {
    // No requirements by default.
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): static {
    try {
      $args = $this->getExecutableValues();
      $this->result = $this->doExecute($args);
    }
    catch (\InvalidArgumentException $e) {
      $this->result = ExecutableResult::failure(new TranslatableMarkup('Tool execution failed due to invalid input: @message', ['@message' => $e->getMessage()]));
      return $this;
    }
    catch (\Throwable $e) {
      $this->result = ExecutableResult::failure(new TranslatableMarkup('Tool execution failed: @message', ['@message' => $e->getMessage()]));
      return $this;
    }
    // If result is successful, we need to check for provided context values.
    if ($this->result->isSuccess()) {
      // If the action provides context values, we need to set the values.
      if ($provided_definitions = $this->getOutputDefinitions()) {
        $this->outputs = [];
        foreach ($this->result->getContextValues() as $context_name => $value) {
          if (isset($provided_definitions[$context_name])) {
            $this->setOutputValue($context_name, $value);
          }
        }
      }
    }
    return $this;
  }

  /**
   * Executes the tool with the provided values.
   *
   * This method is called by the `execute()` method and should contain the
   * logic for executing the tool. It should return an ExecutableResult object.
   *
   * @param array $values
   *   The values to execute the tool with.
   *
   * @return \Drupal\tool\ExecutableResult
   *   The result of the tool execution.
   */
  abstract protected function doExecute(array $values): ExecutableResult;

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    $account = $account ?? $this->currentUser;
    return $this->checkAccess($this->getExecutableValues(), $account, $return_as_object);
  }

  /**
   * Checks access for the tool.
   *
   * This method is used to determine if the current user has access to execute
   * the tool with the provided values. By default, it returns forbidden access.
   *
   * @param array $values
   *   The values to check access against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for. Defaults to current user.
   * @param bool $return_as_object
   *   Whether to return the result as an AccessResult object or a boolean.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   Returns an AccessResult object or a boolean indicating access status.
   */
  abstract protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface;

  /**
   * {@inheritdoc}
   */
  public function getResultMessage(): TranslatableMarkup {
    if (!isset($this->result)) {
      throw new \BadMethodCallException('The action message cannot be retrieved until the action ::execute() method has been called.');
    }
    return $this->result->getMessage();
  }

  /**
   * {@inheritdoc}
   */
  public function getResultStatus(): bool {
    if (!isset($this->result)) {
      throw new \BadMethodCallException('The action status cannot be retrieved until the action ::execute() method has been called.');
    }
    return $this->result->isSuccess();
  }

  /**
   * {@inheritdoc}
   */
  public function getResult(): ExecutableResult {
    if (!isset($this->result)) {
      throw new \BadMethodCallException('The action result cannot be retrieved until the action ::execute() method has been called.');
    }
    return $this->result;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormClass($operation) {
    /** @var \Drupal\tool\Tool\ToolDefinition $tool_definition */
    $tool_definition = $this->getPluginDefinition();
    return $tool_definition->getForm($operation);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // @todo Implement getCacheContexts() method.
    return [];
  }

}
