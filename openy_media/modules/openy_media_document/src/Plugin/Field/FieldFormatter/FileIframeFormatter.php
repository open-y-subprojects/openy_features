<?php

namespace Drupal\openy_media_document\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;

/**
 * Plugin implementation of the file iframe field formatter.
 *
 * @FieldFormatter(
 *   id = "openy_file_iframe",
 *   label = @Translation("File Iframe"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FileIframeFormatter extends FileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $image_uri = $file->getFileUri();
      $url = \Drupal::service('file_url_generator')->generate($image_uri);
      $elements[$delta] = [
        '#type' => 'inline_template',
        '#template' => '<iframe src="{{ url }}" frameborder="0"></iframe>',
        '#context' => [
          'url' => $url,
        ],
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];
    }

    return $elements;
  }

}
