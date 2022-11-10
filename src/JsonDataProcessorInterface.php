<?php

namespace Drupal\occapi_client;

/**
 * Defines an interface for a JSON data processor.
 */
interface JsonDataProcessorInterface {

  /**
   * Get the data from a resource.
   *
   * @param array $resource
   *   An array containing a JSON:API resource.
   *
   * @return array
   *   The actual data of the JSON:API resource.
   */
  public function getResourceData(array $resource);

  /**
   * Get a resource type.
   *
   * @param array $resource
   *   An array containing a JSON:API resource data.
   *
   * @return string
   *   The type of the JSON:API resource.
   */
  public function getResourceType(array $resource);

  /**
   * Get a resource ID.
   *
   * @param array $resource
   *   An array containing a JSON:API resource data.
   *
   * @return string
   *   The ID of the JSON:API resource.
   */
  public function getResourceId(array $resource);

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
  public function getResourceAttribute(array $resource, string $attribute);

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
  public function getResourceLinkByType(array $resource, string $link_type);

  /**
   * Gather resource collection links.
   *
   * @param array $collection
   *   An array containing a JSON:API resource collection.
   *
   * @return array
   *   An array of resource 'self' links keyed by resource ID.
   */
  public function getResourceLinks(array $collection);

}
