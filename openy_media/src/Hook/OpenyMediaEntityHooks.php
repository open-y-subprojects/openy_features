<?php

namespace Drupal\openy_media\Hook;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity-related hook implementations for openy_media.
 */
class OpenyMediaEntityHooks implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new OpenyMediaEntityHooks instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $extra = [];

    $media_bundles = $this->entityTypeManager
      ->getStorage('media_type')
      ->loadMultiple();

    foreach ($media_bundles as $bundle) {
      $extra['media'][$bundle->id()]['display']['edit_link'] = [
        'label' => $this->t('Edit link'),
        'description' => $this->t('A link to edit the media item.'),
        'weight' => 100,
        'visible' => FALSE,
      ];
    }

    return $extra;
  }

  /**
   * Implements hook_ENTITY_TYPE_view() for media entities.
   */
  #[Hook('media_view')]
  public function mediaView(array &$build, MediaInterface $media, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$display->getComponent('edit_link')) {
      return;
    }

    if (!$media->access('update')) {
      return;
    }

    $build['edit_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit'),
      '#url' => $media->toUrl('edit-form'),
      '#attributes' => [
        'class' => ['media-edit-link'],
        'target' => '_blank',
      ],
    ];
  }

}
