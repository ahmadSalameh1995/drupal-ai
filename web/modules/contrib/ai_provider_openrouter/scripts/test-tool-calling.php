<?php

/**
 * Test tool-calling with OpenRouter provider.
 * 
 * Usage: ddev drush scr web/modules/contrib/ai_provider_openrouter/test-tool-calling.php
 */

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;

echo "=== Testing Tool-Calling with OpenRouter ===" . PHP_EOL;
echo PHP_EOL;

// Create a simple calculator tool
$calcTool = new ToolsFunctionInput('calculate');
$calcTool->setDescription('Perform a mathematical calculation');

$expression = new ToolsPropertyInput('expression');
$expression->setDescription('The mathematical expression to evaluate');
$expression->setType('string');
$expression->setRequired(TRUE);

$calcTool->setProperties([$expression]);

// Create tools input
$tools = new ToolsInput([$calcTool]);

// Create chat input with the tool
$input = new ChatInput([
  new ChatMessage('user', 'What is 15 multiplied by 8?', [])
]);
$input->setChatTools($tools);

// Call OpenRouter
$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
echo "Provider: OpenRouter" . PHP_EOL;
echo "Model: openai/gpt-4-turbo" . PHP_EOL;
echo "Question: What is 15 multiplied by 8?" . PHP_EOL;
echo "Tool Available: calculate(expression)" . PHP_EOL;
echo PHP_EOL;

try {
  $response = $provider->chat($input, 'openai/gpt-4-turbo');
  $message = $response->getNormalized();
  
  if ($message->getTools()) {
    echo "✅ SUCCESS: Model requested tool call!" . PHP_EOL;
    echo PHP_EOL;
    
    foreach ($message->getTools() as $tool) {
      echo "Function: " . $tool->getName() . PHP_EOL;
      echo "Arguments: " . json_encode($tool->getArguments(), JSON_PRETTY_PRINT) . PHP_EOL;
      echo "Tool ID: " . $tool->getToolId() . PHP_EOL;
    }
    
    echo PHP_EOL;
    echo "✅ Tool-calling is working with OpenRouter!" . PHP_EOL;
  } else {
    echo "⚠️  No tool call - got text response instead:" . PHP_EOL;
    echo $message->getText() . PHP_EOL;
    echo PHP_EOL;
    echo "This might mean:" . PHP_EOL;
    echo "- Model chose to answer directly" . PHP_EOL;
    echo "- Model doesn't support function calling" . PHP_EOL;
    echo "- Tool description wasn't clear enough" . PHP_EOL;
  }
} catch (\Exception $e) {
  echo "❌ ERROR: " . $e->getMessage() . PHP_EOL;
}
