<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openrouter\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Base class for OpenRouter kernel tests.
 *
 * Provides shared setup for testing the OpenRouter provider plugin.
 */
#[RunTestsInSeparateProcesses]
abstract class OpenRouterKernelTestBase extends KernelTestBase {

  /**
   * Modules to enable for OpenRouter kernel tests.
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'ai',
    'ai_provider_openrouter',
  ];

  /**
   * The OpenRouter provider plugin instance (wrapped in ProviderProxy).
   *
   * @var \Drupal\ai\Plugin\ProviderProxy
   */
  protected $provider;

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * Fallback API key used when no real key is available.
   *
   * @var string
   */
  protected $testApiKey = 'sk-or-v1-test-key-12345';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'ai', 'ai_provider_openrouter']);
    $this->installEntitySchema('key');

    // Use real API key from environment if available, otherwise use fake key.
    $api_key = $this->getRealApiKey() ?: $this->testApiKey;

    // Create a test API key entity.
    $key = Key::create([
      'id' => 'openrouter_test_key',
      'label' => 'OpenRouter Test Key',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_input' => 'text_field',
    ]);
    $key->setKeyValue($api_key);
    $key->save();

    // Configure the OpenRouter provider to use the test key.
    $config = $this->config('ai_provider_openrouter.settings');
    $config->set('api_key', 'openrouter_test_key');
    $config->set('base_url', 'https://openrouter.ai/api/v1');
    $config->save();

    // Get the provider plugin manager.
    $this->providerManager = \Drupal::service('ai.provider');

    // Create an instance of the OpenRouter provider.
    $this->provider = $this->providerManager->createInstance('openrouter');
  }

  /**
   * Helper to check if we should skip tests requiring real API calls.
   *
   * Set OPENROUTER_API_KEY=sk-or-v1-... to enable tests that hit the real API.
   * Tests are skipped by default when no real key is available.
   *
   * @return bool
   *   TRUE if real API tests should be skipped.
   */
  protected function shouldSkipRealApiTests(): bool {
    return empty(getenv('OPENROUTER_API_KEY'));
  }

  /**
   * Helper to get a real API key from environment if available.
   *
   * Set OPENROUTER_API_KEY=sk-or-v1-... to run tests against real API.
   *
   * @return string|null
   *   The API key or NULL if not set.
   */
  protected function getRealApiKey(): ?string {
    $key = getenv('OPENROUTER_API_KEY');
    return $key !== FALSE ? $key : NULL;
  }

  /**
   * Configure provider to use real API key for integration tests.
   */
  protected function configureRealApiKey(): void {
    $real_key = $this->getRealApiKey();
    if ($real_key) {
      $key = Key::load('openrouter_test_key');
      $key->setKeyValue($real_key);
      $key->save();
    }
  }

}
