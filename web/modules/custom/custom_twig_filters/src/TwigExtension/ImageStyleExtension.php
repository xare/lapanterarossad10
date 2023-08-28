<?php

namespace Drupal\custom_twig_filters\TwigExtension;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Image\ImageFactory;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides the custom 'image_style' filter for Twig.
 */
class ImageStyleExtension extends AbstractExtension {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs a new ImageStyleExtension object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ImageFactory $image_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->imageFactory = $image_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('image_style', [$this, 'applyImageStyle']),
    ];
  }

  /**
   * Apply the given image style to an image URI.
   *
   * @param string $uri
   *   The image URI.
   * @param string $style_name
   *   The image style name.
   *
   * @return string
   *   The URL of the image with the applied style.
   */
  public function applyImageStyle($uri, $style_name) {
    $style = ImageStyle::load($style_name);
    if ($style) {
      return $style->buildUrl($uri);
    }
    $image = $this->imageFactory->get($uri);
    return $image->getSource();
  }
}
