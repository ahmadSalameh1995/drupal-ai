<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openrouter\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests model filtering in OpenRouterProvider::getConfiguredModels().
 *
 * @group ai_provider_openrouter
 */
#[RunTestsInSeparateProcesses]
class OpenRouterProviderModelFilterKernelTest extends KernelTestBase {

  /**
   * Modules to enable for this kernel test.
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'ai',
    'key',
    'ai_provider_openrouter',
  ];

  /**
   * Ensures only enabled models are returned by the provider.
   */
  public function testGetConfiguredModelsRespectsEnabledModels(): void {
    // Configure enabled models.
    \Drupal::configFactory()->getEditable('ai_provider_openrouter.settings')
      ->set('enabled_models', [
        'openai/gpt-3.5-turbo',
        'anthropic/claude-2',
      ])
      ->save();

    // Mock the OpenRouter client service to return a known set of models.
    $client = $this->getMockBuilder('Drupal\\ai_provider_openrouter\\Service\\OpenRouterClient')
      ->disableOriginalConstructor()
      ->onlyMethods(['listModels'])
      ->getMock();

    $client->method('listModels')->willReturn([
      'openai/gpt-3.5-turbo' => [
        'id' => 'openai/gpt-3.5-turbo',
        'name' => 'GPT-3.5 Turbo',
      ],
      'openai/gpt-4' => [
        'id' => 'openai/gpt-4',
        'name' => 'GPT-4',
      ],
      'anthropic/claude-2' => [
        'id' => 'anthropic/claude-2',
        'name' => 'Claude 2',
      ],
    ]);

    \Drupal::getContainer()->set('ai_provider_openrouter.client', $client);

    // Create the provider via the AI plugin manager.
    $manager = \Drupal::service('ai.provider');
    $provider = $manager->createInstance('openrouter');
    /** @var \Drupal\ai\AiProviderInterface $provider */

    $models = $provider->getConfiguredModels('chat');

    $this->assertArrayHasKey('openai/gpt-3.5-turbo', $models);
    $this->assertArrayHasKey('anthropic/claude-2', $models);
    $this->assertArrayNotHasKey('openai/gpt-4', $models);
  }

}
