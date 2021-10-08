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
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "occapi_provider",
 *   admin_permission = "administer occapi_provider",
 *   links = {
 *     "collection" = "/admin/config/services/occapi/occapi-provider",
 *     "add-form" = "/admin/config/services/occapi/occapi-provider/add",
 *     "edit-form" = "/admin/config/services/occapi/occapi-provider/{occapi_provider}",
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
 *     "description"
 *   }
 * )
 */
class OccapiProvider extends ConfigEntityBase implements OccapiProviderInterface {

  /**
   * The occapi provider ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The occapi provider label.
   *
   * @var string
   */
  protected $label;

  /**
   * The occapi provider status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The occapi_provider description.
   *
   * @var string
   */
  protected $description;

}
