<?php

namespace Drupal\openy_media\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'entity_reference_hybrid_autocomplete' widget.
 *
 * Shows all options on focus with inline filtering (dropdown + autocomplete).
 */
#[FieldWidget(
  id: 'entity_reference_hybrid_autocomplete',
  label: new TranslatableMarkup('Dropdown with Autocomplete'),
  description: new TranslatableMarkup('Shows all options on focus with inline filtering.'),
  field_types: ['entity_reference'],
  multiple_values: TRUE,
)]
class HybridAutocompleteWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'match_operator' => 'CONTAINS',
      'match_limit' => 0,
      'size' => 60,
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Enable tags mode for multi-value.
    $element['target_id']['#tags'] = TRUE;
    $element['target_id']['#default_value'] = $items->referencedEntities();

    // Attach custom JS library.
    $element['#attached']['library'][] = 'openy_media/hybrid_autocomplete';
    $element['target_id']['#attributes']['class'][] = 'hybrid-autocomplete';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    return $values['target_id'];
  }

}
