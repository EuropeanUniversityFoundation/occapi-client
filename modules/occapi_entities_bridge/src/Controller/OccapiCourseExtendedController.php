<?php

namespace Drupal\occapi_entities_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\occapi_client\JsonDataProcessor;
use Drupal\occapi_client\JsonDataSchemaInterface;
use Drupal\occapi_client\OccapiFieldManager;
use Drupal\occapi_client\OccapiProviderManagerInterface;
use Drupal\occapi_entities\Entity\Course;
use Drupal\occapi_entities_bridge\OccapiImportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for OCCAPI entities bridge routes.
 */
class OccapiCourseExtendedController extends ControllerBase {

  const DATA_KEY = JsonDataSchemaInterface::JSONAPI_DATA;
  const ATTR_KEY = JsonDataSchemaInterface::JSONAPI_ATTR;

  /**
   * The OCCAPI Course entity.
   *
   * @var \Drupal\occapi_entities\Entity\Course
   */
  protected $entity;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * The OCCAPI provider manager.
   *
   * @var \Drupal\occapi_client\OccapiProviderManagerInterface
   */
  protected $providerManager;

  /**
   * The OCCAPI remote data handler.
   *
   * @var \Drupal\occapi_client\OccapiRemoteDataInterface
   */
  protected $remoteData;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\occapi_entities_bridge\OccapiImportManager $import_manager
   *   The OCCAPI entity import manager service.
   * @param \Drupal\occapi_client\OccapiProviderManagerInterface $provider_manager
   *   The OCCAPI provider manager.
   * @param \Drupal\occapi_client\OccapiRemoteDataInterface $remote_data
   *   The OCCAPI remote data handler.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    OccapiImportManager $import_manager,
    OccapiProviderManagerInterface $provider_manager,
    OccapiRemoteDataInterface $remote_data
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->importManager     = $import_manager;
    $this->providerManager   = $provider_manager;
    $this->remoteData        = $remote_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('occapi_entities_bridge.manager')
      $container->get('occapi_client.manager');
      $container->get('occapi_entities_bridge.remote')
    );
  }

  /**
   * Provides a title callback for extended data.
   *
   * @return string
   *   The title for the entity controller.
   */
  public function extendedDataTitle() {
    return $this->t('Extended data');
  }

  /**
   * Builds the response for extended data.
   */
  public function extendedData(Course $course) {
    $this->entity = $course;

    $remote_id  = $this->entity->get(OccapiImportManager::REMOTE_ID)->value;
    $remote_url = $this->entity->get(OccapiImportManager::REMOTE_URL)->value;

    // Get the entity ID of the referenced Institution.
    $ref_field = $this->entity->get(OccapiImportManager::REF_HEI)->getValue();
    $target_id = $ref_field[0]['target_id'];

    // Get the Institution ID.
    $hei_id = $this->entityTypeManager
      ->getStorage(OccapiImportManager::REF_HEI)
      ->load($target_id)
      ->get('hei_id')
      ->value;

    // Get the OCCAPI provider that covers the Institution ID.
    $providers = $this->providerManager->getProvidersByHeiId($hei_id);

    $provider_id = '';

    // Account for more than one provider for a given Institution ID.
    if (!empty($providers)) {
      $found = FALSE;
      foreach ($providers as $key => $obj) {
        if (! $found) {
          $provider_id = $key;
          $found = TRUE;
        }
      }
    }

    // Build the TempStore key for this Course.
    $temp_store_key = '';

    if (!empty($remote_id)) {
      $temp_store_key = \implode('.', [
        $provider_id,
        OccapiProviderManager::COURSE_KEY,
        $remote_id,
        OccapiImportManager::EXT_SUFFIX
      ]);
    }

    // Load additional Course data from an external API.
    $course_ext = NULL;

    if (!empty($temp_store_key) && !empty($remote_url)) {
      $course_ext = $this->remoteData
        ->loadExternalCourse($temp_store_key, $remote_url);
    }

    // Prepare the data from the extra fields.
    $display_data = [];

    if (!empty($course_ext)) {
      $course_ext_fields = OccapiFieldManager::getCourseExtraFields();

      $course_ext_data = $course_ext[self::DATA_KEY];
      $course_ext_attributes = $course_ext_data[self::ATTR_KEY];

      foreach ($course_ext_fields as $key => $value) {
        $display_data[$key] = $course_ext_attributes[$key] ?? [];
      }
    }

    $markup = '';

    // Render the stored data first.
    $pre_render = $this->entityTypeManager
      ->getViewBuilder(OccapiImportManager::COURSE_ENTITY)
      ->view($this->entity, 'full');

    $title = $this->t('Stored data');

    $markup .= '<details open>';
    $markup .= '<summary><strong>' . $title . '</strong></summary>';
    $markup .= '<div class="details-wrapper">';
    $markup .= render($pre_render);
    $markup .= '</div>';
    $markup .= '</details>';

    // Render extra field data.
    if (!empty($display_data)) {
      foreach ($display_data as $key => $array) {

        foreach ($array as $i => $value) {
          $lang = $value[self::LANG_KEY];
          $title = ($lang) ? $key . ' <code>' . $lang . '</code>' : $key;

          if (isset($value[JsonDataProcessor::MLSTR_KEY])) {
            $text = $value[JsonDataProcessor::MLSTR_KEY];
          }
          elseif (isset($value[JsonDataProcessor::STR_KEY])) {
            $text = $value[JsonDataProcessor::STR_KEY];
          }
          else {
            $text = '';
          }

          $markup .= '<details>';
          $markup .= '<summary>' . $title . '</summary>';
          $markup .= '<div class="details-wrapper">';
          $markup .= $text;
          $markup .= '</div>';
          $markup .= '</details>';
        }
      }
    }
    else {
      $markup .= '<em>' . $this->t('No external data to display.') . '</em>';
    }

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $markup,
    ];

    return $build;
  }

}
