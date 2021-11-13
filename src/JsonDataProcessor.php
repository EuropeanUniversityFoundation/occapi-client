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
   * Get a resource type.
   *
   * @param array $resource
   *   An array containing a JSON:API resource data.
   *
   * @return string $type
   *   The type of the JSON:API resource.
   */
  public function getType(array $resource) {
    $type = $resource[Json::DATA_KEY][Json::TYPE_KEY];

    return $type;
  }

  /**
   * Get a resource ID.
   *
   * @param array $resource
   *   An array containing a JSON:API resource data.
   *
   * @return string $id
   *   The id of the JSON:API resource.
   */
  public function getId(array $resource) {
    $id = $resource[Json::DATA_KEY][Json::ID_KEY];

    return $id;
  }

  /**
  * Get a resource title.
  *
  * @param array $resource
  *   An array containing a JSON:API resource data.
  *
  * @return string $title
  *   The title attribute of the JSON:API resource.
  */
  public function getTitle(array $resource) {
    $title = '';
    $data = $resource[self::DATA_KEY];

    // Priority to Drupal entity labels.
    if (
      \array_key_exists(self::ATTR_KEY, $data) &&
      \array_key_exists(self::LABEL_KEY, $data[self::ATTR_KEY])
    ) {
      return $data[self::ATTR_KEY][self::LABEL_KEY];
    }

    if (
      \array_key_exists(self::ATTR_KEY, $data) &&
      \array_key_exists(self::TITLE_KEY, $data[self::ATTR_KEY])
    ) {
      // Enforce an array of title objects.
      if (! \array_key_exists(0, $data[self::ATTR_KEY][self::TITLE_KEY])) {
        $title_array = [$data[self::ATTR_KEY][self::TITLE_KEY]];
      } else {
        $title_array = $data[self::ATTR_KEY][self::TITLE_KEY];
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

  /**
   * Get a resource attribute by key.
   *
   * @param array $resource
   *   An array containing a JSON:API resource data.
   * @param string $attribute
   *   The key to a JSON:API resource attribute.
   *
   * @return array $result
   *   The value of the attribute keyed by attribute name.
   */
  public function getAttribute(array $resource, string $attribute) {
    $result = [];
    $data = $resource[self::DATA_KEY];

    if (
      \array_key_exists(self::ATTR_KEY, $data) &&
      \array_key_exists($attribute, $data[self::ATTR_KEY])
    ) {
      $result[$attribute] = $data[self::ATTR_KEY][$attribute];
    }

    return $result;
  }

  /**
   * Get a resource link by key.
   *
   * @param array $resource
   *   An array containing a JSON:API resource data.
   * @param string $link_type
   *   The JSON:API link type key to extract.
   *
   * @return string $link
   *   The URL of the JSON:API link.
   */
  public function getLink(array $resource, string $link_type) {
    $link = '';
    $data = $resource[self::DATA_KEY];

    if (
      \array_key_exists(self::LINKS_KEY, $resource) &&
      \array_key_exists($link_type, $resource[self::LINKS_KEY])
    ) {
      $link = $resource[self::LINKS_KEY][$link_type][self::HREF_KEY];
    }

    if (
      $link_type === self::SELF_KEY &&
      \array_key_exists(self::LINKS_KEY, $data)
    ) {
      // Data links should take precedence over resource links.
      $link = $data[self::LINKS_KEY][$link_type][self::HREF_KEY];
    }

    return $link;
  }

  /**
   * Gather resource collection titles.
   *
   * @param array $collection
   *   An array containing a JSON:API resource collection.
   *
   * @return array $titles
   *   An array of resource titles keyed by resource ID.
   */
  public function getTitles($collection) {
    $titles = [];

    foreach ($collection as $i => $resource) {
      $id = $this->getId($resource);

      $title = $this->getTitle($resource);

      // Use ID as fallback for missing title.
      $title = ($title) ? $title : $id;

      $titles[$id] = $title;
    }

    return $titles;
  }

  /**
   * Gather resource collection links.
   *
   * @param array $collection
   *   An array containing a JSON:API resource collection.
   *
   * @return array $links
   *   An array of resource 'self' links keyed by resource ID.
   */
  public function getLinks($collection) {
    $links = [];

    foreach ($collection as $i => $resource) {
      $id = $resource[self::ID_KEY];
      $uri = $this->getLink($resource, self::SELF_KEY);

      $links[$id] = $uri;
    }

    return $links;
  }

}
