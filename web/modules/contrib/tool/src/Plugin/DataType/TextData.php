<?php

namespace Drupal\tool\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\tool\TypedData\Type\TextInterface;

/**
 * The text data type.
 */
#[DataType(
  id: "text",
  label: new TranslatableMarkup("Text")
)]
class TextData extends StringData implements TextInterface {}
