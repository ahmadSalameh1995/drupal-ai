# Testing Tool-Calling with OpenRouter Provider

Tool-calling (also called function-calling) allows AI models to call external functions/tools to perform actions or retrieve information.

## Quick Test via Drush

### 1. Simple Weather Tool Example

```bash
ddev drush ev "
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;

// Create a weather tool
\$weatherTool = new ToolsFunctionInput('get_current_weather');
\$weatherTool->setDescription('Get the current weather in a given location');

// Add location parameter
\$locationProp = new ToolsPropertyInput('location');
\$locationProp->setDescription('The city and state, e.g. San Francisco, CA');
\$locationProp->setType('string');
\$locationProp->setRequired(TRUE);

// Add unit parameter
\$unitProp = new ToolsPropertyInput('unit');
\$unitProp->setDescription('Temperature unit');
\$unitProp->setType('string');
\$unitProp->setEnum(['celsius', 'fahrenheit']);

\$weatherTool->setProperties([\$locationProp, \$unitProp]);

// Create tools input
\$tools = new ToolsInput([\$weatherTool]);

// Create chat input with tool
\$input = new ChatInput([
  new ChatMessage('user', 'What is the weather like in San Francisco?', [])
]);
\$input->setChatTools(\$tools);

// Call the provider
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$response = \$provider->chat(\$input, 'openai/gpt-4-turbo');

// Check if model wants to call the tool
\$message = \$response->getNormalized();
if (\$message->getTools()) {
  echo 'Tool call requested!' . PHP_EOL;
  foreach (\$message->getTools() as \$tool) {
    echo 'Function: ' . \$tool->getFunctionCall()->getName() . PHP_EOL;
    echo 'Arguments: ' . json_encode(\$tool->getArguments(), JSON_PRETTY_PRINT) . PHP_EOL;
  }
} else {
  echo 'No tool call, regular response: ' . \$message->getText() . PHP_EOL;
}
"
```

Expected output:
```
Tool call requested!
Function: get_current_weather
Arguments: {
    "location": "San Francisco, CA",
    "unit": "fahrenheit"
}
```

---

## 2. Test via AI API Explorer

The AI module includes a built-in tool-calling explorer:

1. **Navigate to:** `/admin/config/ai/api-explorer/chat`

2. **Select Provider:** OpenRouter

3. **Select Model:** Choose a model that supports function calling:
   - `openai/gpt-4-turbo`
   - `openai/gpt-4o`
   - `anthropic/claude-3.5-sonnet`
   - `google/gemini-pro-1.5`

4. **Expand "Function Calling" section**

5. **Add a function:**
   - Function Name: `get_weather`
   - Description: `Get current weather for a location`
   - Add parameters:
     - `location` (string, required): "City and state"
     - `unit` (string, enum): ["celsius", "fahrenheit"]

6. **Enter prompt:** "What's the weather in New York?"

7. **Click "Generate"**

8. **Check response** - Should show tool call request with arguments

---

## 3. Test with AI Assistant (Agent-based)

AI Assistants can use tools automatically:

### Create an Assistant with Tools

1. **Go to:** `/admin/config/ai/assistants`

2. **Add Assistant:**
   - Name: "Weather Assistant"
   - Provider: OpenRouter
   - Model: `openai/gpt-4-turbo`

3. **Add Function Call:**
   - Select or create a function call plugin
   - Configure parameters

4. **Test in DeepChat:**
   - Add DeepChat block
   - Select "Weather Assistant"
   - Ask: "What's the weather in London?"
   - Assistant should call the tool automatically

---

## 4. Create a Custom Function Call Plugin

For production use, create a proper plugin:

```php
<?php

namespace Drupal\my_module\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\AiFunctionCall;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use Drupal\ai\Plugin\AiFunctionCallBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Weather function call plugin.
 */
#[AiFunctionCall(
  id: 'get_weather',
  label: new TranslatableMarkup('Get Weather'),
  description: new TranslatableMarkup('Get current weather for a location'),
)]
class GetWeatherFunctionCall extends AiFunctionCallBase {

  /**
   * {@inheritdoc}
   */
  public function normalize(): ToolsFunctionInput {
    $function = new ToolsFunctionInput('get_weather');
    $function->setDescription('Get the current weather in a given location');

    $location = new ToolsPropertyInput('location');
    $location->setDescription('The city and state, e.g. San Francisco, CA');
    $location->setType('string');
    $location->setRequired(TRUE);

    $unit = new ToolsPropertyInput('unit');
    $unit->setDescription('Temperature unit');
    $unit->setType('string');
    $unit->setEnum(['celsius', 'fahrenheit']);

    $function->setProperties([$location, $unit]);

    return $function;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments): array {
    // In real implementation, call weather API
    $location = $arguments['location'] ?? 'Unknown';
    $unit = $arguments['unit'] ?? 'fahrenheit';
    
    // Mock response
    return [
      'location' => $location,
      'temperature' => 72,
      'unit' => $unit,
      'conditions' => 'Sunny',
    ];
  }

}
```

---

## 5. Models That Support Tool-Calling

Not all models support function calling. Here are verified working models on OpenRouter:

### OpenAI Models
- ✅ `openai/gpt-4-turbo`
- ✅ `openai/gpt-4o`
- ✅ `openai/gpt-4o-mini`
- ✅ `openai/gpt-3.5-turbo`

### Anthropic Models
- ✅ `anthropic/claude-3.5-sonnet`
- ✅ `anthropic/claude-3-opus`
- ✅ `anthropic/claude-3-sonnet`

### Google Models
- ✅ `google/gemini-pro-1.5`
- ✅ `google/gemini-flash-1.5`

### Meta Models
- ✅ `meta-llama/llama-3.1-70b-instruct`
- ✅ `meta-llama/llama-3.1-405b-instruct`

### Mistral Models
- ✅ `mistralai/mistral-large`
- ✅ `mistralai/mixtral-8x22b-instruct`

---

## 6. Advanced: Multi-Turn Tool Calling

```bash
ddev drush ev "
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;

// Step 1: Define tool
\$calcTool = new ToolsFunctionInput('calculate');
\$calcTool->setDescription('Perform a calculation');

\$exprProp = new ToolsPropertyInput('expression');
\$exprProp->setDescription('Math expression to evaluate');
\$exprProp->setType('string');
\$exprProp->setRequired(TRUE);

\$calcTool->setProperties([\$exprProp]);
\$tools = new ToolsInput([\$calcTool]);

// Step 2: Initial request
\$input = new ChatInput([
  new ChatMessage('user', 'What is 25 * 4 + 10?', [])
]);
\$input->setChatTools(\$tools);

\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$response = \$provider->chat(\$input, 'openai/gpt-4-turbo');

\$message = \$response->getNormalized();

if (\$message->getTools()) {
  echo 'Step 1: Model requested tool call' . PHP_EOL;
  \$toolCall = \$message->getTools()[0];
  \$args = \$toolCall->getArguments();
  
  // Step 3: Execute the tool (simulate)
  \$result = eval('return ' . \$args['expression'] . ';');
  echo 'Step 2: Executed calculation: ' . \$result . PHP_EOL;
  
  // Step 4: Send result back to model
  \$messages = [
    new ChatMessage('user', 'What is 25 * 4 + 10?', []),
    \$message, // The assistant's tool call request
    new ChatMessage('tool', json_encode(['result' => \$result]), []),
  ];
  
  // Set the tool ID on the tool response message
  \$messages[2]->setToolsId(\$toolCall->getId());
  
  \$followUp = new ChatInput(\$messages);
  \$finalResponse = \$provider->chat(\$followUp, 'openai/gpt-4-turbo');
  
  echo 'Step 3: Final answer: ' . \$finalResponse->getNormalized()->getText() . PHP_EOL;
}
"
```

---

## 7. Debugging Tool Calls

### Enable Verbose Logging

```bash
# Check what's being sent to OpenRouter
ddev drush ev "
\$provider = \Drupal::service('ai.provider')->createInstance('openrouter');
\$provider->setDebugData('verbose', TRUE);
// ... rest of your tool call test
"
```

### Check Tool Call Format

The OpenRouter provider sends tools in OpenAI format:
```json
{
  "tools": [
    {
      "type": "function",
      "function": {
        "name": "get_weather",
        "description": "Get current weather",
        "parameters": {
          "type": "object",
          "properties": {
            "location": {
              "type": "string",
              "description": "City and state"
            }
          },
          "required": ["location"]
        }
      }
    }
  ]
}
```

---

## 8. Common Issues

### Issue: Model doesn't call the tool

**Causes:**
- Model doesn't support function calling
- Tool description not clear enough
- User prompt doesn't trigger tool use

**Solution:**
- Use a model from the supported list above
- Make tool descriptions very specific
- Ask questions that clearly need the tool

### Issue: Invalid tool call arguments

**Causes:**
- Parameter types not specified
- Enum values not provided
- Required fields not marked

**Solution:**
```php
$prop = new ToolsPropertyInput('status');
$prop->setType('string');           // Always set type
$prop->setEnum(['open', 'closed']); // Provide valid values
$prop->setRequired(TRUE);           // Mark required fields
```

### Issue: Tool response not recognized

**Causes:**
- Missing tool ID on response message
- Wrong message role (should be 'tool')

**Solution:**
```php
$toolResponse = new ChatMessage('tool', json_encode($result), []);
$toolResponse->setToolsId($toolCall->getId()); // Must match tool call ID
```

---

## 9. Test Suite Addition

Add to your test suite:

```bash
# Add to run-tests script
run_test "Tool calling support" '
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;

try {
  $tool = new ToolsFunctionInput("test_function");
  $tool->setDescription("A test function");
  
  $prop = new ToolsPropertyInput("param");
  $prop->setType("string");
  $prop->setRequired(TRUE);
  $tool->setProperties([$prop]);
  
  $tools = new ToolsInput([$tool]);
  
  $input = new ChatInput([
    new ChatMessage("user", "Call test_function with param=hello", [])
  ]);
  $input->setChatTools($tools);
  
  $p = \Drupal::service("ai.provider")->createInstance("openrouter");
  $r = $p->chat($input, "openai/gpt-4-turbo");
  
  $msg = $r->getNormalized();
  echo ($msg->getTools() !== NULL) ? "PASS" : "FAIL: no tool calls";
} catch (\Exception $e) {
  echo "FAIL: " . $e->getMessage();
}
'
```

---

## Summary

**Quick Start:**
1. Use AI API Explorer at `/admin/config/ai/api-explorer/chat`
2. Select a tool-calling compatible model (GPT-4, Claude 3.5, etc.)
3. Add a function in the "Function Calling" section
4. Test with a relevant prompt

**Production:**
1. Create function call plugins in your module
2. Add them to AI Assistants
3. Use in DeepChat chatbots
4. Handle tool execution and responses

**OpenRouter Provider:** ✅ Fully supports tool-calling via OpenAI SDK
