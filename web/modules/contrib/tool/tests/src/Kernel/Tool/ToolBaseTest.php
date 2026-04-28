<?php

declare(strict_types=1);

namespace Drupal\Tests\tool\Kernel\Tool;

use Drupal\KernelTests\KernelTestBase;
use Drupal\tool\Exception\RequirementsException;

/**
 * Tests ToolBase plugin behavior.
 *
 * @group tool
 * @coversDefaultClass \Drupal\tool\Tool\ToolBase
 */
class ToolBaseTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'tool',
    'tool_test',
    'user',
  ];

  /**
   * The tool plugin manager.
   *
   * @var \Drupal\tool\Tool\ToolManager
   */
  protected $toolManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->toolManager = $this->container->get('plugin.manager.tool');
  }

  /**
   * A tool with no checkRequirements() override does not throw.
   *
   * @covers ::checkRequirements
   */
  public function testNoRequirementsToolDoesNotThrow(): void {
    $tool = $this->toolManager->createInstance('no_requirements_tool');
    // Should not throw.
    $tool->checkRequirements();
    $this->assertTrue(TRUE);
  }

  /**
   * A tool overriding checkRequirements() throws the expected exception.
   *
   * @covers ::checkRequirements
   */
  public function testUnmetRequirementsToolThrows(): void {
    $tool = $this->toolManager->createInstance('unmet_requirements_tool');
    $this->expectException(RequirementsException::class);
    $this->expectExceptionMessage('Test API key is not configured.');
    $tool->checkRequirements();
  }

  /**
   * Tests successful tool execution.
   *
   * @covers ::execute
   * @covers ::getResult
   * @covers ::getResultStatus
   * @covers ::getResultMessage
   */
  public function testSuccessfulExecution(): void {
    $tool = $this->toolManager->createInstance('no_requirements_tool');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertNotNull($result);
    $this->assertTrue($result->isSuccess());
    $this->assertTrue($tool->getResultStatus());
    $this->assertEquals('Done.', (string) $tool->getResultMessage());
  }

  /**
   * Tests that execute() always sets a result even when doExecute() throws.
   *
   * @covers ::execute
   * @covers ::getResult
   */
  public function testExecutionWithException(): void {
    $tool = $this->toolManager->createInstance('throwing_tool');
    $tool->execute();

    // Result should be set even though an exception was thrown.
    $result = $tool->getResult();
    $this->assertNotNull($result);
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('This tool always throws an exception', (string) $result->getMessage());
  }

  /**
   * Tests tool execution that returns a failure result.
   *
   * @covers ::execute
   * @covers ::getResult
   * @covers ::getResultStatus
   */
  public function testFailedExecution(): void {
    $tool = $this->toolManager->createInstance('failing_tool');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertNotNull($result);
    $this->assertFalse($result->isSuccess());
    $this->assertFalse($tool->getResultStatus());
    $this->assertEquals('This tool failed.', (string) $result->getMessage());
  }

  /**
   * Tests execution with invalid input throws InvalidArgumentException.
   *
   * @covers ::execute
   * @covers ::getResult
   */
  public function testExecutionWithInvalidInput(): void {
    $tool = $this->toolManager->createInstance('numeric_input_tool');
    // Set an invalid string value for a numeric field.
    $tool->setInputValue('number', 'not a number');
    $tool->execute();

    // Result should be set with a failure message.
    $result = $tool->getResult();
    $this->assertNotNull($result);
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('invalid input', (string) $result->getMessage());
  }

  /**
   * Tests execution with missing required input.
   *
   * @covers ::execute
   * @covers ::getResult
   */
  public function testExecutionWithMissingRequiredInput(): void {
    $tool = $this->toolManager->createInstance('numeric_input_tool');
    // Don't set the required input value.
    $tool->execute();

    // Result should be set with a failure message.
    $result = $tool->getResult();
    $this->assertNotNull($result);
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('invalid input', (string) $result->getMessage());
  }

  /**
   * Tests that getResult() throws an exception when called before execute().
   *
   * @covers ::getResult
   */
  public function testGetResultBeforeExecute(): void {
    $tool = $this->toolManager->createInstance('no_requirements_tool');

    // Should throw an exception when called before execute().
    $this->expectException(\BadMethodCallException::class);
    $this->expectExceptionMessage('cannot be retrieved until the action ::execute() method has been called');
    $tool->getResult();
  }

  /**
   * Tests successful execution with valid numeric input and output values.
   *
   * @covers ::execute
   * @covers ::getResult
   * @covers ::getOutputValue
   */
  public function testExecutionWithValidInput(): void {
    $tool = $this->toolManager->createInstance('numeric_input_tool');
    $tool->setInputValue('number', 42);
    $tool->execute();

    $result = $tool->getResult();
    $this->assertNotNull($result);
    $this->assertTrue($result->isSuccess());
    $this->assertStringContainsString('42', (string) $result->getMessage());

    // Test that output context values are set correctly.
    $this->assertEquals(84, $tool->getOutputValue('doubled'));
  }

}
