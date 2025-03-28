<?php

namespace Drupal\openy_media_document\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
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

    $allowed_iframe_types = [
      'application/pdf',
      'text/html',
      'text/plain',
      'application/vnd.oasis.opendocument.text',  // .odt
      'application/vnd.oasis.opendocument.spreadsheet',  // .ods
      'application/vnd.oasis.opendocument.presentation',  // .odp
    ];

    $blocked_iframe_types = [
      'text/csv',
      'application/msword',  // .doc
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',  // .docx
      'application/vnd.oasis.opendocument.text-template',  // .ott
      'application/vnd.oasis.opendocument.spreadsheet-template',  // .ots
      'application/vnd.oasis.opendocument.presentation-template',  // .otp
      'application/rtf',  // .rtf
      'application/vnd.ms-excel',  // .xls
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',  // .xlsx
      'application/vnd.ms-powerpoint',  // .ppt
      'application/vnd.openxmlformats-officedocument.presentationml.presentation',  // .pptx
    ];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      if (!$file instanceof FileInterface) {
        continue;
      }

      $file_uri = $file->getFileUri();
      $url = \Drupal::service('file_url_generator')->generateString($file_uri);
      $mime_type = $file->getMimeType();
      $filename = $file->getFilename();

      if (in_array($mime_type, $allowed_iframe_types)) {
        $elements[$delta] = [
          '#type' => 'inline_template',
          '#template' => '<iframe src="{{ url }}" frameborder="0" width="100%" height="500px"></iframe>',
          '#context' => [
            'url' => $url,
          ],
          '#cache' => [
            'tags' => $file->getCacheTags(),
          ],
        ];
      }
      elseif (in_array($mime_type, $blocked_iframe_types)) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $filename,
          '#url' => Url::fromUri($url, ['attributes' => ['download' => TRUE]]),
          '#cache' => [
            'tags' => $file->getCacheTags(),
          ],
        ];
      }
      else {
        $elements[$delta] = [
          '#markup' => $this->t('File: <a href="@url" download>@filename</a>', [
            '@url' => $url,
            '@filename' => $filename,
          ]),
          '#cache' => [
            'tags' => $file->getCacheTags(),
          ],
        ];
      }
    }

    return $elements;
  }
}
