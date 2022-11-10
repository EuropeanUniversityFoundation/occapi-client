<?php

namespace Drupal\occapi_client;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an OCCAPI provider entity type.
 */
interface OccapiProviderInterface extends ConfigEntityInterface {

  /**
   * Returns the Institution ID.
   *
   * @return string|null
   *   The Institution ID if it exists, or NULL otherwise.
   */
  public function heiId(): ?string;

  /**
   * Returns the base URL.
   *
   * @return string|null
   *   The base URL if it exists, or NULL otherwise.
   */
  public function baseUrl(): ?string;

}
