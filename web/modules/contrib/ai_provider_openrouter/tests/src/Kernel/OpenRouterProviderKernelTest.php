<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openrouter\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for OpenRouterProvider plugin registration.
 *
 * @group ai_provider_openrouter
 */
#[RunTestsInSeparateProcesses]
class OpenRouterProviderKernelTest extends KernelTestBase {

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
   * Ensures the OpenRouter provider plugin is discoverable.
   */
  public function testProviderIsDiscoverable(): void {
    $manager = \Drupal::service('ai.provider');
    $plugin_ids = array_keys($manager->getDefinitions());
    $this->assertContains('openrouter', $plugin_ids, 'OpenRouter provider plugin is registered.');
  }

}
