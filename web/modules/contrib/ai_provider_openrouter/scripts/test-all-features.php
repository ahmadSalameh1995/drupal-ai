#!/usr/bin/env php
<?php

/**
 * Simple working test script for OpenRouter provider.
 * 
 * Usage: ddev drush scr web/modules/contrib/ai_provider_openrouter/scripts/test-all-features.php
 */

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

class TestTracker {
  public static $passed = 0;
  public static $failed = 0;
  public static $total = 0;
  
  public static function result($name, $passed, $message = '') {
    self::$total++;
    
    if ($passed) {
      self::$passed++;
      echo "✓ {$name} - PASSED";
      if ($message) {
        echo " ({$message})";
      }
      echo "\n";
    } else {
      self::$failed++;
      echo "✗ {$name} - FAILED";
      if ($message) {
        echo ": {$message}";
      }
      echo "\n";
    }
  }
}

echo "==========================================\n";
echo "OpenRouter Provider - Feature Tests\n";
echo "==========================================\n\n";

try {
  $provider = \Drupal::service('ai.provider')->createInstance('openrouter');
  
  // Test 1: Token limits (critical bug fix)
  echo "Testing token limits...\n";
  $input_tokens = $provider->getMaxInputTokens('openai/gpt-4-turbo');
  $output_tokens = $provider->getMaxOutputTokens('openai/gpt-4-turbo');
  TestTracker::result(
    'Token limits accurate',
    $input_tokens > 100000 && $output_tokens > 1000,
    "Got input: {$input_tokens}, output: {$output_tokens}"
  );
  
  // Test 2: Simple string chat
  echo "\nTesting chat with string input...\n";
  try {
    $response = $provider->chat('Say only the word TEST', 'openai/gpt-3.5-turbo');
    $text = $response->getNormalized()->getText();
    TestTracker::result(
      'Chat with string input',
      !empty($text) && strlen($text) > 0,
      empty($text) ? 'No response text' : ''
    );
  } catch (\Exception $e) {
    TestTracker::result('Chat with string input', false, $e->getMessage());
  }
  
  // Test 3: Chat with ChatInput (no streaming)
  echo "\nTesting chat with ChatInput (no streaming)...\n";
  try {
    $input = new ChatInput([
      new ChatMessage('user', 'Say only the word HELLO', [])
    ]);
    $response = $provider->chat($input, 'openai/gpt-3.5-turbo');
    $text = $response->getNormalized()->getText();
    TestTracker::result(
      'Chat with ChatInput (no streaming)',
      !empty($text) && strlen($text) > 0,
      empty($text) ? 'No response text' : ''
    );
  } catch (\Exception $e) {
    TestTracker::result('Chat with ChatInput (no streaming)', false, $e->getMessage());
  }
  
  // Test 4: Chat with ChatInput + streaming (THE CRITICAL BUG)
  echo "\nTesting chat with ChatInput + streaming (AI 1.3.0 compatibility)...\n";
  try {
    $input = new ChatInput([
      new ChatMessage('user', 'Say only the word WORLD', [])
    ]);
    $input->setStreamedOutput(TRUE);
    $response = $provider->chat($input, 'openai/gpt-3.5-turbo');
    
    // For streaming, getNormalized() returns a Traversable iterator
    $normalized = $response->getNormalized();
    if ($normalized instanceof \Traversable) {
      // Consume the stream to get the full text
      $text = '';
      foreach ($normalized as $chunk) {
        $text .= $chunk->getText();
      }
      TestTracker::result(
        'Chat with streaming enabled',
        !empty($text) && strlen($text) > 0,
        empty($text) ? 'No response text from stream' : "Got: {$text}"
      );
    } elseif (is_object($normalized) && method_exists($normalized, 'getText')) {
      // Non-streaming response (shouldn't happen but handle it)
      $text = $normalized->getText();
      TestTracker::result(
        'Chat with streaming enabled',
        !empty($text),
        'Got non-streaming response instead of iterator'
      );
    } else {
      TestTracker::result('Chat with streaming enabled', false, 'Unexpected response type: ' . get_class($normalized));
    }
  } catch (\Exception $e) {
    TestTracker::result('Chat with streaming enabled', false, $e->getMessage());
  }
  
  // Test 5: Single string embedding
  echo "\nTesting embeddings (single string)...\n";
  try {
    $response = $provider->embeddings('test sentence', 'openai/text-embedding-3-small');
    $embedding = $response->getNormalized();
    TestTracker::result(
      'Embeddings (single string)',
      is_array($embedding) && count($embedding) === 1536,
      is_array($embedding) ? 'Got ' . count($embedding) . ' dimensions' : 'Not an array'
    );
  } catch (\Exception $e) {
    TestTracker::result('Embeddings (single string)', false, $e->getMessage());
  }
  
  // Test 6: Batch embeddings
  echo "\nTesting embeddings (batch)...\n";
  try {
    $texts = ['First', 'Second', 'Third'];
    $response = $provider->embeddings($texts, 'openai/text-embedding-3-small');
    $embeddings = $response->getNormalized();
    $is_batch = is_array($embeddings) && count($embeddings) === 3;
    if ($is_batch) {
      $all_correct_size = true;
      foreach ($embeddings as $emb) {
        if (!is_array($emb) || count($emb) !== 1536) {
          $all_correct_size = false;
          break;
        }
      }
      TestTracker::result('Embeddings (batch)', $all_correct_size);
    } else {
      TestTracker::result('Embeddings (batch)', false, 'Expected 3 embeddings, got ' . count($embeddings));
    }
  } catch (\Exception $e) {
    TestTracker::result('Embeddings (batch)', false, $e->getMessage());
  }
  
  // Test 7: Text-to-image (expensive, optional)
  echo "\nTesting text-to-image (skipping - expensive)...\n";
  echo "  (Run manually if needed: \$provider->textToImage('test', 'openai/dall-e-3'))\n";
  
  // Test 8: Model listing
  echo "\nTesting model listing...\n";
  try {
    $chat_models = $provider->getConfiguredModels('chat');
    $embedding_models = $provider->getConfiguredModels('embeddings');
    TestTracker::result(
      'Model listing',
      count($chat_models) > 0 && count($embedding_models) > 0,
      "Chat: " . count($chat_models) . ", Embeddings: " . count($embedding_models)
    );
  } catch (\Exception $e) {
    TestTracker::result('Model listing', false, $e->getMessage());
  }
  
} catch (\Exception $e) {
  echo "\n✗ FATAL ERROR: " . $e->getMessage() . "\n";
  exit(1);
}

// Summary
echo "\n==========================================\n";
echo "TEST SUMMARY\n";
echo "==========================================\n";
echo "Total: " . TestTracker::$total . "\n";
echo "Passed: " . TestTracker::$passed . "\n";
echo "Failed: " . TestTracker::$failed . "\n";

if (TestTracker::$failed === 0) {
  echo "\n✓ ALL TESTS PASSED\n";
  exit(0);
} else {
  echo "\n✗ SOME TESTS FAILED\n";
  exit(1);
}
