<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openrouter\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Simple functional tests for OpenRouter provider.
 *
 * @group ai_provider_openrouter
 */
#[RunTestsInSeparateProcesses]
class OpenRouterProviderSimpleTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_provider_openrouter',
    'key',
  ];

  /**
   * The AI provider.
   *
   * @var \Drupal\ai\AiProviderInterface
   */
  protected $provider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    
    // Create a test API key.
    $key_storage = $this->container->get('entity_type.manager')->getStorage('key');
    $key = $key_storage->create([
      'id' => 'test_openrouter_key',
      'label' => 'Test OpenRouter Key',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_input' => 'text_field',
      'key_provider_settings' => [
        'key_value' => getenv('OPENROUTER_API_KEY') ?: 'sk-or-v1-test-key',
      ],
    ]);
    $key->save();

    // Configure the provider.
    $config = $this->config('ai_provider_openrouter.settings');
    $config->set('api_key', 'test_openrouter_key');
    $config->save();

    $this->provider = $this->container->get('ai.provider')->createInstance('openrouter');
  }

  /**
   * Test that provider can be instantiated.
   */
  public function testProviderExists(): void {
    $this->assertNotNull($this->provider);
  }

  /**
   * Test token limits are accurate (critical bug fix).
   */
  public function testTokenLimits(): void {
    $input_tokens = $this->provider->getMaxInputTokens('openai/gpt-4-turbo');
    $output_tokens = $this->provider->getMaxOutputTokens('openai/gpt-4-turbo');
    
    // Should be much higher than the old default of 1024
    $this->assertGreaterThan(100000, $input_tokens, 'Input token limit should be > 100k');
    $this->assertGreaterThan(1000, $output_tokens, 'Output token limit should be > 1k');
  }

  /**
   * Test chat with string input.
   */
  public function testChatWithString(): void {
    if (!getenv('OPENROUTER_API_KEY')) {
      $this->markTestSkipped('OPENROUTER_API_KEY not set - skipping real API test');
    }

    $response = $this->provider->chat('Say TEST', 'openai/gpt-3.5-turbo');
    $text = $response->getNormalized()->getText();
    
    $this->assertNotEmpty($text, 'Chat response should not be empty');
  }

  /**
   * Test chat with ChatInput (no streaming).
   */
  public function testChatWithChatInput(): void {
    if (!getenv('OPENROUTER_API_KEY')) {
      $this->markTestSkipped('OPENROUTER_API_KEY not set - skipping real API test');
    }

    $input = new ChatInput([
      new ChatMessage('user', 'Say HELLO', []),
    ]);
    
    $response = $this->provider->chat($input, 'openai/gpt-3.5-turbo');
    $text = $response->getNormalized()->getText();
    
    $this->assertNotEmpty($text, 'Chat response should not be empty');
  }

  /**
   * Test chat with ChatInput and streaming (AI 1.3.0 compatibility - THE CRITICAL BUG).
   */
  public function testChatWithStreaming(): void {
    if (!getenv('OPENROUTER_API_KEY')) {
      $this->markTestSkipped('OPENROUTER_API_KEY not set - skipping real API test');
    }

    $input = new ChatInput([
      new ChatMessage('user', 'Say WORLD', []),
    ]);
    $input->setStreamedOutput(TRUE);
    
    $response = $this->provider->chat($input, 'openai/gpt-3.5-turbo');
    $normalized = $response->getNormalized();
    
    // Should return a Traversable iterator for streaming
    $this->assertInstanceOf(\Traversable::class, $normalized, 'Streaming response should be Traversable');
    
    // Consume the stream
    $text = '';
    foreach ($normalized as $chunk) {
      $text .= $chunk->getText();
    }
    
    $this->assertNotEmpty($text, 'Streamed response should not be empty');
  }

  /**
   * Test embeddings with single string.
   */
  public function testEmbeddingsSingle(): void {
    if (!getenv('OPENROUTER_API_KEY')) {
      $this->markTestSkipped('OPENROUTER_API_KEY not set - skipping real API test');
    }

    $response = $this->provider->embeddings('test sentence', 'openai/text-embedding-3-small');
    $embedding = $response->getNormalized();
    
    $this->assertIsArray($embedding, 'Embedding should be an array');
    $this->assertCount(1536, $embedding, 'Embedding should have 1536 dimensions');
  }

  /**
   * Test embeddings with batch.
   */
  public function testEmbeddingsBatch(): void {
    if (!getenv('OPENROUTER_API_KEY')) {
      $this->markTestSkipped('OPENROUTER_API_KEY not set - skipping real API test');
    }

    $texts = ['First', 'Second', 'Third'];
    $response = $this->provider->embeddings($texts, 'openai/text-embedding-3-small');
    $embeddings = $response->getNormalized();
    
    $this->assertIsArray($embeddings, 'Embeddings should be an array');
    $this->assertCount(3, $embeddings, 'Should have 3 embeddings');
    
    foreach ($embeddings as $embedding) {
      $this->assertIsArray($embedding, 'Each embedding should be an array');
      $this->assertCount(1536, $embedding, 'Each embedding should have 1536 dimensions');
    }
  }

  /**
   * Test model listing.
   */
  public function testModelListing(): void {
    $chat_models = $this->provider->getConfiguredModels('chat');
    $embedding_models = $this->provider->getConfiguredModels('embeddings');
    
    $this->assertNotEmpty($chat_models, 'Should have chat models');
    $this->assertNotEmpty($embedding_models, 'Should have embedding models');
  }

}
