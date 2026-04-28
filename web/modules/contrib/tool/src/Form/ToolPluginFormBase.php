<?php

namespace Drupal\tool\Form;

use Drupal\Core\Plugin\PluginFormBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for tool plugin forms.
 */
abstract class ToolPluginFormBase extends PluginFormBase {
  use StringTranslationTrait;

  /**
   * The plugin this form is for.
   *
   * @var \Drupal\tool\Tool\ToolInterface
   */
  protected $plugin;

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected array $configuration = [];

}
