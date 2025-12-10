<?php

namespace Drupal\openy_media\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Views hook implementations for openy_media.
 */
class OpenyMediaViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   *
   * Adds a custom filter for media entities to filter by taxonomy terms
   * with depth support, similar to how nodes can be filtered by taxonomy.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(&$data): void {
    // Add filters to media__field_media_tags table if it exists.
    // This table is created when field_media_tags is added to media types.
    if (isset($data['media__field_media_tags'])) {
      // Override the default filter on field_media_tags_target_id to use our
      // custom filter with depth support.
      $data['media__field_media_tags']['field_media_tags_target_id']['filter'] = [
        'title' => $this->t('Media Tags (with depth)'),
        'id' => 'openy_media_taxonomy_tags',
        'vocabulary' => 'media_tags',
      ];
    }

    // Also add a pseudo filter on media_field_data for convenience.
    // This allows adding the filter directly from the media base table
    // without requiring the user to add a relationship first.
    if (isset($data['media_field_data'])) {
      $data['media_field_data']['openy_media_tags_filter'] = [
        'title' => $this->t('Media Tags (with depth)'),
        'help' => $this->t('Filter media by taxonomy terms from the Media Tags vocabulary. Supports hierarchical term matching with depth.'),
        'filter' => [
          'title' => $this->t('Media Tags (with depth)'),
          'id' => 'openy_media_taxonomy_tags',
          'vocabulary' => 'media_tags',
          // Point to the field table and column.
          'field table' => 'media__field_media_tags',
          'field field' => 'field_media_tags_target_id',
          'allow empty' => TRUE,
        ],
        'relationship' => [
          'title' => $this->t('Media Tags'),
          'help' => $this->t('Relate media to taxonomy terms via field_media_tags.'),
          'id' => 'standard',
          'base' => 'media__field_media_tags',
          'base field' => 'entity_id',
          'relationship field' => 'mid',
          'label' => $this->t('Media Tags'),
        ],
      ];

      // Also add a filter for media_folders vocabulary.
      $data['media_field_data']['openy_media_folders_filter'] = [
        'title' => $this->t('Media Folders (with depth)'),
        'help' => $this->t('Filter media by taxonomy terms from the Media Folders vocabulary. Supports hierarchical term matching with depth.'),
        'filter' => [
          'title' => $this->t('Media Folders (with depth)'),
          'id' => 'openy_media_taxonomy_tags',
          'vocabulary' => 'media_folders',
          'allow empty' => TRUE,
        ],
      ];
    }
  }

}
