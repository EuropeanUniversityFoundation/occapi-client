<?php

namespace Drupal\occapi_entities_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\occapi_client\JsonDataProcessor;
use Drupal\occapi_client\JsonDataSchemaInterface;
use Drupal\occapi_client\OccapiDataLoaderInterface;
use Drupal\occapi_client\OccapiFieldManager;
use Drupal\occapi_client\OccapiProviderManagerInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;
use Drupal\occapi_entities\Entity\Course;
use Drupal\occapi_entities_bridge\OccapiEntityManagerInterface;
use Drupal\occapi_entities_bridge\OccapiRemoteDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for OCCAPI entities bridge routes.
 */
class OccapiCourseExtendedController extends ControllerBase {

  const DATA_KEY = JsonDataSchemaInterface::JSONAPI_DATA;
  const ATTR_KEY = JsonDataSchemaInterface::JSONAPI_ATTR;

  const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

  const FIELD_REMOTE_ID = OccapiRemoteDataInterface::FIELD_REMOTE_ID;
  const FIELD_REMOTE_URL = OccapiRemoteDataInterface::FIELD_REMOTE_URL;
  const PARAM_EXTERNAL = OccapiRemoteDataInterface::PARAM_EXTERNAL;

  const ENTITY_HEI = OccapiEntityManagerInterface::ENTITY_HEI;
  const REF_HEI = OccapiEntityManagerInterface::ENTITY_REF[self::ENTITY_HEI];
  const UNIQUE_HEI = OccapiEntityManagerInterface::UNIQUE_ID[self::ENTITY_HEI];
  const ENTITY_COURSE = OccapiEntityManagerInterface::ENTITY_COURSE;

  /**
   * The OCCAPI Course entity.
   *
   * @var \Drupal\occapi_entities\Entity\Course
   */
  protected $entity;

  /**
   * The OCCAPI data loader.
   *
   * @var \Drupal\occapi_client\OccapiDataLoaderInterface
   */
  protected $dataLoader;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * The shared TempStore key manager.
   *
   * @var \Drupal\occapi_client\OccapiTempStoreInterface
   */
  protected $occapiTempStore;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The constructor.
   *
   * @param \Drupal\occapi_client\OccapiDataLoaderInterface $data_loader
   *   The OCCAPI data loader.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\occapi_client\OccapiProviderManagerInterface $provider_manager
   *   The OCCAPI provider manager.
   * @param \Drupal\occapi_client\OccapiRemoteDataInterface $remote_data
   *   The OCCAPI remote data handler.
   * @param \Drupal\occapi_client\OccapiTempStoreInterface $occapi_tempstore
   *   The shared TempStore key manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    OccapiDataLoaderInterface $data_loader,
    EntityTypeManagerInterface $entity_type_manager,
    OccapiProviderManagerInterface $provider_manager,
    OccapiRemoteDataInterface $remote_data,
    OccapiTempStoreInterface $occapi_tempstore,
    RendererInterface $renderer
  ) {
    $this->dataLoader        = $data_loader;
    $this->entityTypeManager = $entity_type_manager;
    $this->providerManager   = $provider_manager;
    $this->remoteData        = $remote_data;
    $this->occapiTempStore   = $occapi_tempstore;
    $this->renderer          = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('occapi_client.load'),
      $container->get('entity_type.manager'),
      $container->get('occapi_client.manager'),
      $container->get('occapi_entities_bridge.remote'),
      $container->get('occapi_client.tempstore')
    );
  }

  /**
   * Provides a title callback for extended data.
   *
   * @return string
   *   The title for the entity controller.
   */
  public function extendedDataTitle(Course $course) {
    return $this->t('Extended data for @course', [
      '@course' => $course->label()
    ]);
  }

  /**
   * Builds the response for extended data.
   */
  public function extendedData(Course $course) {
    $this->entity = $course;

    $remote_id  = $this->entity->get(self::FIELD_REMOTE_ID)->value;
    $remote_url = $this->entity->get(self::FIELD_REMOTE_URL)->value;

    // Get the entity ID of the referenced Institution.
    $ref_field = $this->entity->get(self::REF_HEI)->getValue();
    $target_id = $ref_field[0]['target_id'];

    // Get the Institution ID.
    $hei_id = $this->entityTypeManager
      ->getStorage(self::REF_HEI)
      ->load($target_id)
      ->get(self::UNIQUE_HEI)
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
      $temp_store_params = [
        OccapiTempStoreInterface::PARAM_PROVIDER => $provider_id,
        OccapiTempStoreInterface::PARAM_FILTER_TYPE => NULL,
        OccapiTempStoreInterface::PARAM_FILTER_ID => NULL,
        OccapiTempStoreInterface::PARAM_RESOURCE_TYPE => self::TYPE_COURSE,
        OccapiTempStoreInterface::PARAM_RESOURCE_ID => $remote_id,
        OccapiTempStoreInterface::PARAM_EXTERNAL => self::PARAM_EXTERNAL,
      ];

      $temp_store_key = $this->occapiTempStore
        ->keyFromParams($temp_store_params);
    }

    // Load additional Course data from an external API.
    $course_ext = NULL;

    if (!empty($temp_store_key) && !empty($remote_url)) {
      $course_ext = $this->dataLoader
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
      ->getViewBuilder(self::ENTITY_COURSE)
      ->view($this->entity, 'full');

    $title = $this->t('Stored data');

    $markup .= '<details open>';
    $markup .= '<summary><strong>' . $title . '</strong></summary>';
    $markup .= '<div class="details-wrapper">';
    $markup .= $this->renderer->render($pre_render);
    $markup .= '</div>';
    $markup .= '</details>';

    // Render extra field data.
    if (!empty($display_data)) {
      foreach ($display_data as $key => $array) {

        foreach ($array as $i => $value) {
          $lang = $value[JsonDataProcessor::LANG_KEY];
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
