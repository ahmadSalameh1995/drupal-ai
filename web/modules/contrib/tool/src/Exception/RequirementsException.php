<?php

declare(strict_types=1);

namespace Drupal\tool\Exception;

/**
 * Exception thrown when a tool's static requirements are not met.
 *
 * This is thrown by ToolInterface::checkRequirements() when required
 * configuration (e.g. API keys, Drupal config, enabled modules) is absent.
 * It is not intended for runtime failures such as stale credentials or
 * network unavailability.
 */
class RequirementsException extends \RuntimeException {}
