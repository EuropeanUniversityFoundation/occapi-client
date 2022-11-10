<?php

namespace Drupal\occapi_client;

/**
 * Service for processing JSON data.
 */
class JsonDataProcessor implements JsonDataProcessorInterface {

  // JSON:API primary keys.
  const DATA_KEY  = JsonDataSchemaInterface::JSONAPI_DATA;
  const INC_KEY   = JsonDataSchemaInterface::JSONAPI_INC;
  const LINKS_KEY = JsonDataSchemaInterface::JSONAPI_LINKS;

  // JSON:API data keys.
  const TYPE_KEY  = JsonDataSchemaInterface::JSONAPI_TYPE;
  const ID_KEY    = JsonDataSchemaInterface::JSONAPI_ID;
  const ATTR_KEY  = JsonDataSchemaInterface::JSONAPI_ATTR;
  const REL_KEY   = JsonDataSchemaInterface::JSONAPI_REL;
  const META_KEY  = JsonDataSchemaInterface::JSONAPI_META;

  // JSON:API link keys.
  const SELF_KEY  = JsonDataSchemaInterface::JSONAPI_SELF;
  const HREF_KEY  = JsonDataSchemaInterface::JSONAPI_HREF;

  // Drupal specific keys.
  const LABEL_KEY = 'label';

  // EWP compound field keys.
  const STR_KEY   = 'string';
  const MLSTR_KEY = 'multiline';
  const URI_KEY   = 'uri';
  const LANG_KEY  = 'lang';

  const TITLE_KEY = 'title';
  const LANG_PREF = 'en';

  /**
   * Get the data from a resource.
   *
   * @param array $resource
   *   An array containing a JSON:API resource.
   *
   * @return array
   *   The actual data of the JSON:API resource.
   */
  public function getResourceData(array $resource): array {
    $data = (\array_key_exists(self::DATA_KEY, $resource))
      ? $resource[self::DATA_KEY]
      : $resource;

    return $data;
  }

  /**
   * Get a resource type.
   *
   * @param array $resource
   *   An array containing a JSON:API resource data.
   *
   * @return string
   *   The type of the JSON:API resource.
   */
  public function getResourceType(array $resource): string {
    $data = $this->getResourceData($resource);

    return $data[self::TYPE_KEY];
  }

  /**
   * Get a resource ID.
   *
   * @param array $resource
   *   An array containing a JSON:API resource data.
   *
   * @return string
   *   The ID of the JSON:API resource.
   */
  public function getResourceId(array $resource): string {
    $data = $this->getResourceData($resource);

    return $data[self::ID_KEY];
  }

  /**
  * Get a resource title.
  *
  * @param array $resource
  *   An array containing a JSON:API resource data.
  *
  * @return string
  *   The title of the JSON:API resource.
  */
  public function getResourceTitle(array $resource): string {
    $title = '';

    $data = $this->getResourceData($resource);
    $data_attributes = $data[self::ATTR_KEY] ?? [];

    // If there are no attributes, return the empty title.
    if (empty($data_attributes)) {
      return $title;
    }

    // If found, use Drupal entity label as title.
    if (!empty($data_attributes[self::LABEL_KEY] ?? '')) {
      return $data_attributes[self::LABEL_KEY];
    }

    $title_attribute = $data_attributes[self::TITLE_KEY] ?? [];

    if (!empty($title_attribute)) {
      // Enforce an array of title objects.
      $title_items = (! \array_key_exists(0, $title_attribute))
        ? [$title_attribute]
        : $title_attribute;

      $title_ordered = $this->sortByLang($title_items);

      $title = $title_ordered[0][self::STR_KEY] ?? '';
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
   * @return array
   *   The value of the attribute keyed by attribute name.
   */
  public function getResourceAttribute(array $resource, string $attribute): array {
    $data = $this->getResourceData($resource);

    $data_attributes = $data[self::ATTR_KEY] ?? [];

    if (\array_key_exists($attribute, $data_attributes)) {
      return [$attribute => $data_attributes[$attribute]];
    }

    return [];
  }

  /**
   * Get a resource link by key.
   *
   * @param array $resource
   *   An array containing a JSON:API resource data.
   * @param string $link_type
   *   The JSON:API link type key to extract.
   *
   * @return string
   *   The URL of the JSON:API link.
   */
  public function getResourceLinkByType(array $resource, string $link_type): string {
    $link = '';

    $data_links = $resource[self::DATA_KEY][self::LINKS_KEY] ?? [];

    if (!empty($data_links) && $link_type === self::SELF_KEY) {
      $link = $data_links[$link_type][self::HREF_KEY] ?? '';
    }

    $resource_links = $resource[self::LINKS_KEY] ?? [];

    if (empty($link) && \array_key_exists($link_type, $resource_links)) {
      $link = $resource_links[$link_type][self::HREF_KEY] ?? '';
    }

    return $link;
  }

  /**
   * Gather resource collection titles.
   *
   * @param array $collection
   *   An array containing a JSON:API resource collection.
   *
   * @return array
   *   An array of resource titles keyed by resource ID.
   */
  public function getResourceTitles(array $collection): array {
    $titles = [];

    $data = $collection[self::DATA_KEY];

    foreach ($data as $resource) {
      $id = $this->getResourceId($resource);
      $title = $this->getResourceTitle($resource);

      // Use ID as fallback for missing title.
      $titles[$id] = ($title) ? $title : $id;
    }

    return $titles;
  }

  /**
   * Gather resource collection links.
   *
   * @param array $collection
   *   An array containing a JSON:API resource collection.
   *
   * @return array
   *   An array of resource 'self' links keyed by resource ID.
   */
  public function getResourceLinks(array $collection): array {
    $links = [];

    $data = $collection[self::DATA_KEY];

    foreach ($data as $resource) {
      $id = $this->getResourceId($resource);
      $uri = $this->getResourceLinkByType($resource, self::SELF_KEY);

      $links[$id] = $uri;
    }

    return $links;
  }

  /**
   * Sort language typed data by preferred language.
   *
   * @param array $language_typed_data
   *   An array containing language typed data.
   *
   * @return array
   *   The sorted data.
   */
  public function sortByLang(array $language_typed_data): array {
    $preferred = [];
    $remaining = [];

    foreach ($language_typed_data as $item) {
      $item_lang = $item[self::LANG_KEY] ?? NULL;

      if ($item_lang === self::LANG_PREF) {
        \array_push($preferred, $item);
      }
      else {
        \array_push($remaining, $item);
      }
    }

    return \array_merge($preferred, $remaining);
  }

}
