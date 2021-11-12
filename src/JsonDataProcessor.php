<?php

namespace Drupal\occapi_client;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * JSON data processing service.
 */
class JsonDataProcessor {

  use StringTranslationTrait;

  // JSON:API primary keys.
  const DATA_KEY  = 'data';
  const INC_KEY   = 'included';
  const LINKS_KEY = 'links';

  // JSON:API data keys.
  const TYPE_KEY  = 'type';
  const ID_KEY    = 'id';
  const ATTR_KEY  = 'attributes';
  const REL_KEY   = 'relationships';

  // OCCAPI title field.
  const TITLE_KEY = 'title';
  const VALUE_KEY = 'string';
  const LANG_KEY  = 'lang';
  const LANG_PREF = 'en';

  // JSON:API link keys.
  const SELF_KEY  = 'self';
  const HREF_KEY  = 'href';

  // Drupal array keys
  const LABEL_KEY = 'label';

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new JsonDataProcessor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    TranslationInterface $string_translation
  ) {
    $this->logger             = $logger_factory->get('occapi_client');
    $this->stringTranslation  = $string_translation;
  }

  /**
   * Gather resource collection titles.
   */
  public function collectionTitles($collection) {
    $titles = [];

    foreach ($collection as $i => $resource) {
      $id = $resource[self::ID_KEY];
      $title = $this->extractTitle($resource[self::ATTR_KEY]);
      $title = ($title) ? $title : $id;

      $titles[$id] = $title;
    }

    return $titles;
  }

  /**
   * Gather resource collection links.
   */
  public function collectionLinks($collection) {
    $links = [];

    foreach ($collection as $i => $resource) {
      $id = $resource[self::ID_KEY];
      $uri = $resource[self::LINKS_KEY][self::SELF_KEY][self::HREF_KEY];

      $links[$id] = $uri;
    }

    return $links;
  }

  /**
   * Extract title from attributes.
   */
  public function extractTitle($attributes) {
    $title = '';

    if (\array_key_exists(self::TITLE_KEY, $attributes)) {
      // Enforce an array of title objects.
      if (! \array_key_exists(0, $attributes[self::TITLE_KEY])) {
        $title_array = [$attributes[self::TITLE_KEY]];
      } else {
        $title_array = $attributes[self::TITLE_KEY];
      }

      // Sort the title objects by prefered lang value.
      $title_primary = [];
      $title_fallback = [];
      $title_ordered = [];

      foreach ($title_array as $i => $arr) {
        if (
          \array_key_exists(self::LANG_KEY, $arr) &&
          $arr[self::LANG_KEY] === self::LANG_PREF
        ) {
          \array_push($title_primary, $arr);
        } else {
          \array_push($title_fallback, $arr);
        }
        $title_ordered = \array_merge($title_primary, $title_fallback);
      }

      if (count($title_ordered) > 0) {
        $title = $title_ordered[0][self::VALUE_KEY];
      }
    }

    return $title;
  }

}
