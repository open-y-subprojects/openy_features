<?php

namespace Drupal\openy_media\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Media Library hook implementations for openy_media.
 */
class OpenyMediaLibraryHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_views_exposed_form_alter().
   *
   * Adds a Media Tags filter to the Media Library exposed form dynamically.
   * This avoids modifying core's media_library view config directly.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    $view = $form_state->get('view');
    if (!$view instanceof ViewExecutable) {
      return;
    }

    // Only apply to media_library view's widget displays.
    if ($view->id() !== 'media_library') {
      return;
    }

    $display_id = $view->current_display;
    if (!in_array($display_id, ['widget', 'widget_table'])) {
      return;
    }

    // Check if field_media_tags exists on any media type.
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $media_bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('media');
    $has_media_tags = FALSE;

    foreach (array_keys($media_bundles) as $bundle) {
      $fields = $entity_field_manager->getFieldDefinitions('media', $bundle);
      if (isset($fields['field_media_tags'])) {
        $has_media_tags = TRUE;
        break;
      }
    }

    if (!$has_media_tags) {
      return;
    }

    // Check if media_tags vocabulary exists.
    $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load('media_tags');
    if (!$vocabulary) {
      return;
    }

    // Get default value from request (comma-separated term IDs).
    $default_value = [];
    $media_tags_param = \Drupal::request()->query->get('media_tags', '');
    if (!empty($media_tags_param)) {
      $term_ids = is_array($media_tags_param) ? $media_tags_param : explode(',', $media_tags_param);
      $term_ids = array_filter(array_map('intval', $term_ids));
      if (!empty($term_ids)) {
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($term_ids);
        $default_value = array_values($terms);
      }
    }

    // Add the Media Tags filter element with autocomplete and multiple values.
    $form['media_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Media Tags'),
      '#default_value' => $this->formatDefaultValue($default_value),
      '#weight' => -10,
      '#size' => 30,
      '#placeholder' => $this->t('Filter by tags...'),
      '#maxlength' => 1024,
      '#attributes' => [
        'class' => ['hybrid-autocomplete'],
      ],
    ];

    // Attach our custom libraries.
    $form['#attached']['library'][] = 'openy_media/hybrid_autocomplete';
    $form['#attached']['library'][] = 'openy_media/media_library_auto_apply';
  }

  /**
   * Formats default value for the hybrid autocomplete field.
   *
   * @param array $terms
   *   Array of term entities.
   *
   * @return string
   *   Comma-separated string of "term (id)" values.
   */
  protected function formatDefaultValue(array $terms): string {
    $values = [];
    foreach ($terms as $term) {
      $values[] = $term->label() . ' (' . $term->id() . ')';
    }
    return implode(', ', $values);
  }

  /**
   * Implements hook_views_query_alter().
   *
   * Applies the Media Tags filter condition when the parameter is present.
   */
  #[Hook('views_query_alter')]
  public function viewsQueryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    // Only apply to media_library view's widget displays.
    if ($view->id() !== 'media_library') {
      return;
    }

    if (!in_array($view->current_display, ['widget', 'widget_table'])) {
      return;
    }

    // Check for media_tags parameter in the request.
    $media_tags = \Drupal::request()->query->get('media_tags');
    if (empty($media_tags)) {
      return;
    }

    // Parse term IDs from autocomplete format "Term 1 (1), Term 2 (2)" or array.
    $selected_tids = $this->parseAutocompleteTags($media_tags);
    if (empty($selected_tids)) {
      return;
    }

    // Get term IDs including children (depth of 10 levels) for all selected tags.
    $tids = [];
    foreach ($selected_tids as $tid) {
      $tids = array_merge($tids, $this->getTermIdsWithChildren($tid, 10));
    }
    $tids = array_unique($tids);

    // Join to the field_media_tags table and add the condition.
    $definition = [
      'table' => 'media__field_media_tags',
      'field' => 'entity_id',
      'left_table' => 'media_field_data',
      'left_field' => 'mid',
      'type' => 'INNER',
    ];

    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $definition);
    $query->addRelationship('media__field_media_tags', $join, 'media_field_data');

    $operator = count($tids) > 1 ? 'IN' : '=';
    $value = count($tids) > 1 ? $tids : reset($tids);
    $query->addWhere(0, 'media__field_media_tags.field_media_tags_target_id', $value, $operator);
  }

  /**
   * Parses autocomplete tags format and extracts term IDs.
   *
   * Handles multiple formats:
   * - Autocomplete string: "Tag 1 (1), Tag 2 (2)"
   * - Array of entity objects with target_id
   * - Comma-separated IDs: "1,2,3"
   *
   * @param mixed $input
   *   The input value from the autocomplete field.
   *
   * @return array
   *   Array of term IDs.
   */
  protected function parseAutocompleteTags($input): array {
    if (empty($input)) {
      return [];
    }

    $tids = [];

    // Handle array format from entity_autocomplete with #tags.
    if (is_array($input)) {
      foreach ($input as $item) {
        if (is_array($item) && isset($item['target_id'])) {
          $tids[] = (int) $item['target_id'];
        }
        elseif (is_object($item) && method_exists($item, 'id')) {
          $tids[] = (int) $item->id();
        }
        elseif (is_numeric($item)) {
          $tids[] = (int) $item;
        }
      }
      return array_filter($tids);
    }

    // Handle string format.
    $input = (string) $input;

    // Try autocomplete format: "Tag 1 (1), Tag 2 (2)".
    if (preg_match_all('/\((\d+)\)/', $input, $matches)) {
      $tids = array_map('intval', $matches[1]);
      if (!empty($tids)) {
        return $tids;
      }
    }

    // Try comma-separated IDs: "1,2,3".
    $parts = explode(',', $input);
    foreach ($parts as $part) {
      $part = trim($part);
      if (is_numeric($part)) {
        $tids[] = (int) $part;
      }
    }

    return array_filter($tids);
  }

  /**
   * Gets term IDs including all children.
   *
   * @param int $tid
   *   The parent term ID.
   * @param int $depth
   *   Maximum depth to traverse.
   *
   * @return array
   *   Array of term IDs including the parent and all children.
   */
  protected function getTermIdsWithChildren(int $tid, int $depth = 10): array {
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $term = $term_storage->load($tid);

    if (!$term) {
      return [$tid];
    }

    $tids = [$tid];
    $children = $term_storage->loadTree($term->bundle(), $tid, $depth, FALSE);

    foreach ($children as $child) {
      $tids[] = $child->tid;
    }

    return array_unique($tids);
  }

}
