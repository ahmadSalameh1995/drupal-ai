<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openrouter\Kernel;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * Kernel tests for OpenRouter chat operations.
 *
 * @group ai_provider_openrouter
 */
class OpenRouterProviderChatKernelTest extends OpenRouterKernelTestBase {

  /**
   * Test that the provider supports the chat operation type.
   */
  public function testProviderSupportsChatOperationType(): void {
    $operation_types = $this->provider->getSupportedOperationTypes();
    $this->assertContains('chat', $operation_types, 'OpenRouter provider supports the chat operation type.');
  }

  /**
   * Test getMaxInputTokens() returns reasonable values.
   */
  public function testGetMaxInputTokens(): void {
    // Test with a known model (if API is available).
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    // Test with GPT-4 which should have a large context window.
    $tokens = $this->provider->getMaxInputTokens('openai/gpt-4-turbo');
    $this->assertGreaterThan(8000, $tokens, 'GPT-4 Turbo should have >8k context window.');
    $this->assertLessThan(200000, $tokens, 'Context window should be reasonable (<200k).');

    // Test with unknown model falls back to default.
    $tokens = $this->provider->getMaxInputTokens('unknown/model-xyz');
    $this->assertEquals(8192, $tokens, 'Unknown model should return default 8192 tokens.');
  }

  /**
   * Test getMaxOutputTokens() returns reasonable values.
   */
  public function testGetMaxOutputTokens(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    // Test with a known model.
    $tokens = $this->provider->getMaxOutputTokens('openai/gpt-4-turbo');
    $this->assertGreaterThan(1000, $tokens, 'GPT-4 Turbo should support >1k output tokens.');
    $this->assertLessThan(50000, $tokens, 'Output tokens should be reasonable (<50k).');

    // Test with unknown model falls back to 25% of input.
    $tokens = $this->provider->getMaxOutputTokens('unknown/model-xyz');
    $this->assertEquals(2048, $tokens, 'Unknown model should return 25% of default input (8192 * 0.25 = 2048).');
  }

  /**
   * Test simple text chat with string input.
   */
  public function testSimpleTextChatWithString(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $response = $this->provider->chat(
      'Say "test successful" and nothing else.',
      'openai/gpt-3.5-turbo',
      []
    );

    $this->assertNotNull($response, 'Chat response should not be null.');
    $message = $response->getNormalized()->getText();
    $this->assertNotEmpty($message, 'Chat response should contain text.');
    $this->assertStringContainsStringIgnoringCase('test', $message, 'Response should contain "test".');
  }

  /**
   * Test chat with ChatInput object.
   */
  public function testChatWithChatInput(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $input = new ChatInput([
      new ChatMessage('user', 'What is 2+2? Reply with only the number.', []),
    ]);

    $response = $this->provider->chat($input, 'openai/gpt-3.5-turbo', []);

    $this->assertNotNull($response, 'Chat response should not be null.');
    $message = $response->getNormalized()->getText();
    $this->assertNotEmpty($message, 'Chat response should contain text.');
    $this->assertStringContainsString('4', $message, 'Response should contain "4".');
  }

  /**
   * Test multi-turn conversation.
   */
  public function testMultiTurnConversation(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $input = new ChatInput([
      new ChatMessage('user', 'My name is Alice.', []),
      new ChatMessage('assistant', 'Hello Alice! Nice to meet you.', []),
      new ChatMessage('user', 'What is my name?', []),
    ]);

    $response = $this->provider->chat($input, 'openai/gpt-3.5-turbo', []);

    $this->assertNotNull($response, 'Chat response should not be null.');
    $message = $response->getNormalized()->getText();
    $this->assertStringContainsStringIgnoringCase('alice', $message, 'Response should remember the name Alice.');
  }

  /**
   * Test system prompt handling.
   */
  public function testSystemPrompt(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    // Set a system role via the provider's chatSystemRole property.
    $this->provider->setChatSystemRole('You are a helpful assistant that always responds in uppercase.');

    $response = $this->provider->chat(
      'hello',
      'openai/gpt-3.5-turbo',
      []
    );

    $message = $response->getNormalized()->getText();
    // Verify the response is non-empty, confirming the system prompt was
    // accepted and did not cause an error.
    $this->assertNotEmpty($message, 'Response with system prompt should not be empty.');
  }

  /**
   * Test token usage tracking in non-streaming response.
   */
  public function testTokenUsageTracking(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $response = $this->provider->chat(
      'Say hello.',
      'openai/gpt-3.5-turbo',
      []
    );

    $usage = $response->getTokenUsage();
    $this->assertNotNull($usage, 'Token usage should be tracked.');
    $this->assertGreaterThan(0, $usage->total, 'Total tokens should be > 0.');
    $this->assertGreaterThan(0, $usage->input, 'Input tokens should be > 0.');
    $this->assertGreaterThan(0, $usage->output, 'Output tokens should be > 0.');
  }

  /**
   * Test streaming chat (if supported).
   */
  public function testStreamingChat(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    // Enable streaming via ChatInput (AI 1.3+ API).
    $input = new ChatInput([
      new ChatMessage('user', 'Count from 1 to 5.', []),
    ]);
    $input->setStreamedOutput(TRUE);

    $response = $this->provider->chat(
      $input,
      'openai/gpt-3.5-turbo',
      []
    );

    $this->assertNotNull($response, 'Streaming response should not be null.');

    // For streaming, getNormalized() returns the StreamedChatMessageIterator.
    $iterator = $response->getNormalized();
    $this->assertInstanceOf(
      'Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface',
      $iterator,
      'Streaming response should return a StreamedChatMessageIteratorInterface.'
    );

    // Collect all chunks by iterating.
    $full_text = '';
    foreach ($iterator as $chunk) {
      $full_text .= $chunk->getText();
    }

    $this->assertNotEmpty($full_text, 'Streaming should produce text content.');
  }

  /**
   * Test error handling for invalid model.
   */
  public function testInvalidModelError(): void {
    if ($this->shouldSkipRealApiTests()) {
      $this->markTestSkipped('Skipping real API test. Set OPENROUTER_API_KEY to enable.');
    }

    $this->configureRealApiKey();

    $this->expectException(\Drupal\ai\Exception\AiResponseErrorException::class);

    $this->provider->chat(
      'test',
      'invalid/nonexistent-model-xyz',
      []
    );
  }

  /**
   * Test rate limit exception handling.
   */
  public function testRateLimitException(): void {
    // This test is difficult to trigger reliably without actually hitting rate limits.
    // We'll document it for manual testing or mocking in the future.
    $this->markTestSkipped('Rate limit testing requires mocking or intentional rate limit triggering.');
  }

}
