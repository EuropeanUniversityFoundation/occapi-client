<?php

namespace Drupal\occapi_client\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\occapi_client\OccapiProviderInterface;

/**
 * Defines the OCCAPI provider entity type.
 *
 * @ConfigEntityType(
 *   id = "occapi_provider",
 *   label = @Translation("OCCAPI provider"),
 *   label_collection = @Translation("OCCAPI providers"),
 *   label_singular = @Translation("OCCAPI provider"),
 *   label_plural = @Translation("OCCAPI providers"),
 *   label_count = @PluralTranslation(
 *     singular = "@count OCCAPI provider",
 *     plural = "@count OCCAPI providers",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\occapi_client\OccapiProviderListBuilder",
 *     "form" = {
 *       "add" = "Drupal\occapi_client\Form\OccapiProviderForm",
 *       "edit" = "Drupal\occapi_client\Form\OccapiProviderForm",
 *       "preview" = "Drupal\occapi_client\Form\OccapiProviderPreviewForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "occapi_provider",
 *   admin_permission = "administer occapi_provider",
 *   links = {
 *     "collection" = "/admin/config/services/occapi/occapi-provider",
 *     "add-form" = "/admin/config/services/occapi/occapi-provider/add",
 *     "edit-form" = "/admin/config/services/occapi/occapi-provider/{occapi_provider}",
 *     "preview-form" = "/admin/config/services/occapi/occapi-provider/{occapi_provider}/preview",
 *     "delete-form" = "/admin/config/services/occapi/occapi-provider/{occapi_provider}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "base_url",
 *     "hei_id",
 *     "ounit_filter",
 *     "description"
 *   }
 * )
 */
class OccapiProvider extends ConfigEntityBase implements OccapiProviderInterface {

  /**
   * The OCCAPI provider ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The OCCAPI provider label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Institution SCHAC code covered by the OCCAPI provider.
   *
   * @var string
   */
  protected $hei_id;

  /**
   * The OCCAPI provider base URL.
   *
   * @var string
   */
  protected $base_url;

  /**
   * Support for filtering by Organizational Unit.
   *
   * @var bool
   */
  protected $ounit_filter;

  /**
   * The OCCAPI provider status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The OCCAPI provider description.
   *
   * @var string
   */
  protected $description;

  /**
   * Returns the Institution ID.
   *
   * @return string|null
   *   The Institution ID if it exists, or NULL otherwise.
   */
  public function heiId(): ?string {
    return $this->get('hei_id');
  }

}
