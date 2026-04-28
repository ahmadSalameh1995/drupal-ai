<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openrouter\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\key\Entity\Key;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for OpenRouter provider integration.
 *
 * Tests the full integration with Drupal's AI ecosystem including
 * assistants, DeepChat, and agent orchestration.
 *
 * @group ai_provider_openrouter
 */
#[RunTestsInSeparateProcesses]
class OpenRouterProviderFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'user',
    'key',
    'ai',
    'ai_provider_openrouter',
    'ai_assistant_api',
    'ai_agents',
  ];

  /**
   * Admin user for testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create admin user with all AI permissions.
    $this->adminUser = $this->drupalCreateUser([
      'administer ai',
      'administer keys',
      'administer ai assistants',
      'use ai assistants',
      'administer ai agents',
    ]);

    // Create API key.
    $api_key = getenv('OPENROUTER_API_KEY') ?: 'sk-or-v1-test-key';
    $key = Key::create([
      'id' => 'openrouter_api_key',
      'label' => 'OpenRouter API Key',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_input' => 'text_field',
    ]);
    $key->setKeyValue($api_key);
    $key->save();

    // Configure OpenRouter provider.
    $config = $this->config('ai_provider_openrouter.settings');
    $config->set('api_key', 'openrouter_api_key');
    $config->set('base_url', 'https://openrouter.ai/api/v1');
    $config->save();
  }

  /**
   * Test OpenRouter configuration form.
   */
  public function testConfigurationForm(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/ai/openrouter');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('OpenRouter Provider');
    $this->assertSession()->fieldExists('api_key');
    $this->assertSession()->fieldExists('base_url');
  }

  /**
   * Test that OpenRouter provider is available in provider list.
   */
  public function testProviderIsAvailable(): void {
    $this->drupalLogin($this->adminUser);
    
    $provider_manager = \Drupal::service('ai.provider');
    $providers = $provider_manager->getDefinitions();
    
    $this->assertArrayHasKey('openrouter', $providers, 'OpenRouter provider should be available.');
    $this->assertEquals('OpenRouter', $providers['openrouter']['label']->render());
  }

  /**
   * Test model selection form shows OpenRouter models.
   */
  public function testModelSelectionForm(): void {
    if (empty(getenv('OPENROUTER_API_KEY'))) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/ai/openrouter');
    
    // The form should show available models.
    $this->assertSession()->pageTextContains('Models');
  }

  /**
   * Test creating an AI assistant with OpenRouter provider.
   */
  public function testCreateAssistantWithOpenRouter(): void {
    if (empty(getenv('OPENROUTER_API_KEY'))) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->drupalLogin($this->adminUser);

    // Navigate to assistant creation form.
    $this->drupalGet('admin/structure/ai_assistant/add');
    $this->assertSession()->statusCodeEquals(200);

    // Fill in assistant details.
    $edit = [
      'label' => 'Test OpenRouter Assistant',
      'id' => 'test_openrouter_assistant',
      'provider' => 'openrouter',
      'model' => 'openai/gpt-3.5-turbo',
      'system_prompt' => 'You are a helpful assistant.',
    ];

    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Created the Test OpenRouter Assistant');
  }

  /**
   * Test that provider supports required operation types.
   */
  public function testProviderSupportsOperationTypes(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('openrouter');
    
    $operation_types = $provider->getSupportedOperationTypes();
    
    $this->assertContains('chat', $operation_types, 'Provider should support chat.');
    $this->assertContains('embeddings', $operation_types, 'Provider should support embeddings.');
    $this->assertContains('text_to_image', $operation_types, 'Provider should support text_to_image.');
  }

  /**
   * Test provider is usable when API key is configured.
   */
  public function testProviderIsUsable(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('openrouter');
    
    $is_usable = $provider->isUsable('chat');
    
    $this->assertTrue($is_usable, 'Provider should be usable when API key is configured.');
  }

  /**
   * Test model filtering configuration.
   */
  public function testModelFilteringConfiguration(): void {
    if (empty(getenv('OPENROUTER_API_KEY'))) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/ai/openrouter');

    // Enable specific models.
    $edit = [
      'enabled_models[openai/gpt-3.5-turbo]' => TRUE,
      'enabled_models[openai/gpt-4]' => TRUE,
    ];

    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify only enabled models are returned.
    $provider = \Drupal::service('ai.provider')->createInstance('openrouter');
    $models = $provider->getConfiguredModels('chat');

    $this->assertArrayHasKey('openai/gpt-3.5-turbo', $models);
    $this->assertArrayHasKey('openai/gpt-4', $models);
  }

  /**
   * Test DeepChat integration (if module is available).
   */
  public function testDeepChatIntegration(): void {
    if (!\Drupal::moduleHandler()->moduleExists('ai_assistant_api')) {
      $this->markTestSkipped('ai_assistant_api module not available.');
    }

    if (empty(getenv('OPENROUTER_API_KEY'))) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    // Create a test assistant.
    $assistant = \Drupal::entityTypeManager()
      ->getStorage('ai_assistant')
      ->create([
        'id' => 'test_deepchat_assistant',
        'label' => 'Test DeepChat Assistant',
        'provider' => 'openrouter',
        'model' => 'openai/gpt-3.5-turbo',
        'system_prompt' => 'You are a test assistant.',
      ]);
    $assistant->save();

    $this->drupalLogin($this->adminUser);

    // Test that assistant is accessible.
    $this->drupalGet('ai/assistant/test_deepchat_assistant');
    $this->assertSession()->statusCodeEquals(200);
  }

}
