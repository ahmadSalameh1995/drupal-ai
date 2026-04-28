<?php

declare(strict_types=1);

namespace Drupal\ai_provider_openrouter\OperationType\Chat;

use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;

/**
 * OpenRouter streamed chat message iterator.
 *
 * Wraps a Traversable stream from the OpenAI/OpenRouter client and yields
 * StreamedChatMessage chunks compatible with the AI module.
 */
class OpenRouterStreamedChatMessageIterator extends StreamedChatMessageIterator {

  /**
   * {@inheritdoc}
   */
  public function doIterate(): \Generator {
    // Iterate directly over the traversable stream.
    foreach ($this->iterator as $data) {
      $metadata = $data->usage ? $data->usage->toArray() : [];
      $message = $this->createStreamedChatMessage(
        $data->choices[0]->delta->role ?? '',
        $data->choices[0]->delta->content ?? '',
        $metadata,
        $data->choices[0]->delta->toolCalls ?? NULL,
        $data->toArray(),
      );
      if ($data->usage !== NULL) {
        $message->setInputTokenUsage($data->usage->promptTokens ?? 0);
        $message->setOutputTokenUsage($data->usage->completionTokens ?? 0);
        $message->setTotalTokenUsage($data->usage->totalTokens ?? 0);
        $message->setReasoningTokenUsage($data->usage->completionTokenDetails->reasoningTokens ?? 0);
        $message->setCachedTokenUsage($data->usage->completionTokenDetails->cachedTokens ?? 0);
      }
      if (isset($data->choices[0]->finishReason)) {
        $this->setFinishReason($data->choices[0]->finishReason);
      }
      yield $message;
    }
  }

}
