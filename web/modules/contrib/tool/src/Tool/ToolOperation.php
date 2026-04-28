<?php

declare(strict_types=1);

namespace Drupal\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the available tool operations.
 *
 * Operations define the nature of what a tool does, its expectations,
 * and implications when called.
 */
enum ToolOperation: string {

  case Explain = 'explain';

  case Read = 'read';

  case Transform = 'transform';

  case Trigger = 'trigger';

  case Write = 'write';

  /**
   * Get a human-readable label for the operation.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The operation label.
   */
  public function getLabel(): TranslatableMarkup {
    return match ($this) {
      self::Explain => new TranslatableMarkup('Explain'),
      self::Read => new TranslatableMarkup('Read'),
      self::Transform => new TranslatableMarkup('Transform'),
      self::Trigger => new TranslatableMarkup('Trigger'),
      self::Write => new TranslatableMarkup('Write'),
    };
  }

  /**
   * Get a detailed description of the operation.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The operation description.
   */
  public function getDescription(): TranslatableMarkup {
    return match ($this) {
      self::Explain => new TranslatableMarkup('Provides structural or contextual information about a system, schema, or definition. Commonly used by non-deterministic tools to understand available structures, definitions and schema before interaction. Produces descriptive output, not data or side effects.'),
      self::Read => new TranslatableMarkup('Retrieves data or resources from a defined source without modifying them. Intended for deterministic access to existing state or assets, such as fetching entities, configuration, or external resources.'),
      self::Transform => new TranslatableMarkup('Accepts input data and returns a modified or derived result. Operates purely on provided or external data processing without persisting changes.'),
      self::Trigger => new TranslatableMarkup('Initiates a defined process, flow, or external operation (e.g., queue worker, cron job, webhook). May include identifiers or minimal input data needed to start the process, but does not directly handle storage or transformation of that data.'),
      self::Write => new TranslatableMarkup('Persists or updates data in storage. Represents tools responsible for creating, updating, or deleting stored entities or configurations, as opposed to those that only process or trigger actions.'),
    };
  }

  /**
   * Check if the operation modifies data or state.
   *
   * @return bool
   *   TRUE if the operation modifies data or state.
   */
  public function isModifying(): bool {
    return match ($this) {
      self::Write, self::Trigger => TRUE,
      self::Explain, self::Read, self::Transform => FALSE,
    };
  }

  /**
   * Check if the operation is idempotent (safe to repeat).
   *
   * @return bool
   *   TRUE if the operation is idempotent.
   */
  public function isIdempotent(): bool {
    return match ($this) {
      self::Explain, self::Read => TRUE,
      self::Transform, self::Trigger, self::Write => FALSE,
    };
  }

}
