<?php

namespace Drupal\openy_media_document\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;

/**
 * Plugin implementation of the file link field formatter.
 *
 * @FieldFormatter(
 *   id = "openy_file_link",
 *   label = @Translation("File link"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FileLinkFormatter extends FileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $title = $items->getEntity()->get('name')->value;

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $image_uri = $file->getFileUri();
      $url = \Drupal::service('file_url_generator')->generate($image_uri);
      $elements[$delta] = [
        '#title' => $title,
        '#type' => 'link',
        '#url' => $url,
        '#attributes' => ['target' => '_blank'],
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];
    }

    return $elements;
  }

}
