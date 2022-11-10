<?php

namespace Drupal\occapi_client;

/**
 * Defines an interface for a JSON:API data schema.
 */
interface JsonDataSchemaInterface {

  // JSON:API primary keys.
  const JSONAPI_DATA  = 'data';
  const JSONAPI_INC   = 'included';
  const JSONAPI_LINKS = 'links';

  // JSON:API data keys.
  const JSONAPI_TYPE  = 'type';
  const JSONAPI_ID    = 'id';
  const JSONAPI_ATTR  = 'attributes';
  const JSONAPI_REL   = 'relationships';
  const JSONAPI_META  = 'meta';
  // JSON:API data objects may also include links objects.

  // JSON:API link keys.
  const JSONAPI_SELF  = 'self';
  const JSONAPI_HREF  = 'href';

  // Requirements.
  const REQUIRED_ATTR = '_required';

  /**
   * Defines the JSON:API data schema.
   *
   * @return array
   *   The JSON:API data schema represented as an array.
   */
  public static function schema();

  /**
   * Provides the JSON:API data schema.
   *
   * @return array
   *   The JSON:API data schema represented as an array.
   */
  public function getSchema();

}
