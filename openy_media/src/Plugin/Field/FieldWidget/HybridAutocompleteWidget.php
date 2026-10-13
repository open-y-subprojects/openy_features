<?php

namespace Drupal\openy_media\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected ElementInfoManagerInterface $elementInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->elementInfo = $container->get('element_info');
    return $instance;
  }

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

    // The custom JS renders its own dropdown, so keep core's autocomplete from
    // binding a second one to the same input.
    $process = $this->elementInfo->getInfoProperty('entity_autocomplete', '#process', []);
    array_splice($process, 1, 0, [[static::class, 'disableCoreAutocomplete']]);
    $element['target_id']['#process'] = $process;

    return $element;
  }

  /**
   * Process callback: removes the core autocomplete route from the element.
   *
   * Runs after EntityAutocomplete::processEntityAutocomplete() sets the route
   * and before FormElementBase::processAutocomplete() would attach it.
   */
  public static function disableCoreAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element['#autocomplete_route_name'] = NULL;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    return $values['target_id'];
  }

}
