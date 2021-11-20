<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ewp_institutions_get\InstitutionManager;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\Entity\OccapiProvider;
use Drupal\occapi_client\JsonDataFetcher;
use Drupal\occapi_client\JsonDataProcessor;
use Drupal\occapi_client\OccapiFieldManager;
use Drupal\occapi_client\OccapiProviderManager;

/**
 * Service for managing OCCAPI entity imports.
 */
class OccapiImportManager {

  use StringTranslationTrait;

  // Machine names of OCCAPI entity types.
  const PROGRAMME_ENTITY = 'programme';
  const COURSE_ENTITY    = 'course';

  // Machine name of the entity label.
  const LABEL_KEY = 'label';

  // Machine names of entity reference fields.
  const REF_HEI       = 'hei';
  const REF_PROGRAMME = 'related_programme';

  // Machine names of OCCAPI extra fields.
  const REMOTE_ID  = 'remote_id';
  const REMOTE_URL = 'remote_url';
  const JSON_META  = 'meta';

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
  * Data formatting service.
  *
  * @var \Drupal\occapi_client\DataFormatter
  */
  protected $dataFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
  * EWP Institutions manager service.
  *
  * @var \Drupal\ewp_institutions_get\InstitutionManager
  */
  protected $heiManager;

  /**
  * JSON data fetching service.
  *
  * @var \Drupal\occapi_client\JsonDataFetcher
  */
  protected $jsonDataFetcher;

  /**
  * JSON data processing service.
  *
  * @var \Drupal\occapi_client\JsonDataProcessor
  */
  protected $jsonDataProcessor;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * OCCAPI provider manager service.
   *
   * @var \Drupal\occapi_client\OccapiProviderManager
   */
  protected $providerManager;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\occapi_client\DataFormatter $data_formatter
   *   Data formatting service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\ewp_institutions_get\InstitutionManager $hei_manager
   *   EWP Institutions manager service.
   * @param \Drupal\occapi_client\JsonDataFetcher $json_data_fetcher
   *   JSON data fetching service.
   * @param \Drupal\occapi_client\JsonDataProcessor $json_data_processor
   *   JSON data processing service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\occapi_client\OccapiProviderManager $provider_manager
   *   The provider manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
      ConfigFactoryInterface $config_factory,
      DataFormatter $data_formatter,
      EntityTypeManagerInterface $entity_type_manager,
      InstitutionManager $hei_manager,
      JsonDataFetcher $json_data_fetcher,
      JsonDataProcessor $json_data_processor,
      LoggerChannelFactoryInterface $logger_factory,
      MessengerInterface $messenger,
      OccapiProviderManager $provider_manager,
      TranslationInterface $string_translation
  ) {
    $this->configFactory      = $config_factory;
    $this->dataFormatter      = $data_formatter;
    $this->entityTypeManager  = $entity_type_manager;
    $this->heiManager         = $hei_manager;
    $this->jsonDataFetcher    = $json_data_fetcher;
    $this->jsonDataProcessor  = $json_data_processor;
    $this->logger             = $logger_factory->get('occapi_entities_bridge');
    $this->messenger          = $messenger;
    $this->providerManager    = $provider_manager;
    $this->stringTranslation  = $string_translation;
  }

  /**
   * Get the field mapping for Programme entities.
   *
   * @return array $field_map
   *   An array in the format [apiAttribute => drupal_field].
   */
  public function programmeFieldMap(): array {
    $field_map = [
      'title'                 => 'title',
      'code'                  => 'code',
      'description'           => 'description',
      'ects'                  => 'ects',
      'eqfLevelProvided'      => 'eqf_level_provided',
      'iscedCode'             => 'isced_code',
      'languageOfInstruction' => 'language_of_instruction',
      'length'                => 'length',
      'url'                   => 'url',
    ];

    return $field_map;
  }

  /**
   * Get the field mapping for Course entities.
   *
   * @return array $field_map
   *   An array in the format [apiAttribute => drupal_field].
   */
  public function courseFieldMap(): array {
    $field_map = [
      'title'                 => 'title',
      'code'                  => 'code',
      'description'           => 'description',
      'learningOutcomes'      => 'learning_outcomes',
      'ects'                  => 'ects',
      'iscedCode'             => 'isced_code',
      'subjectArea'           => 'subject_area',
      'otherCategorization'   => 'other_categorization',
      'languageOfInstruction' => 'language_of_instruction',
      'academicTerm'          => 'academic_term',
      'url'                   => 'url',
    ];

    return $field_map;
  }

  /**
   * Display API fields and Drupal fields as HTML table.
   *
   * @param array $resource
   *   An array containing a JSON:API resource collection.
   * @param string $entity_type
   *   The target OCCAPI entity type.
   *
   * @return string
   *   Rendered table markup.
   */
  public function fieldTable(array $resource, string $entity_type): string {
    switch ($entity_type) {
      case self::PROGRAMME_ENTITY:
        $field_map = $this->programmeFieldMap();
        break;

      case self::COURSE_ENTITY:
        $field_map = $this->courseFieldMap();
        break;

      default:
        return '<em>' . $this->t('Nothing to display.') . '</em>';
        break;
    }

    $data = (\array_key_exists(JsonDataProcessor::DATA_KEY, $resource)) ?
      $resource[JsonDataProcessor::DATA_KEY] :
      $resource;

    $header = [
      $this->t('API object'),
      $this->t('API field key'),
      $this->t('API field value'),
      $this->t('Drupal field key')
    ];

    $rows = [];

    if (\array_key_exists(JsonDataProcessor::LINKS_KEY, $data)) {
      $links = $data[JsonDataProcessor::LINKS_KEY];
    }

    if (\array_key_exists(JsonDataProcessor::REL_KEY, $data)) {
      $relationships = $data[JsonDataProcessor::REL_KEY];
    }

    if (\array_key_exists(JsonDataProcessor::ATTR_KEY, $data)) {
      $attributes = $data[JsonDataProcessor::ATTR_KEY];

      $obj = JsonDataProcessor::ATTR_KEY;

      // Loop over attributes.
      foreach ($attributes as $field => $field_value) {
        $label = \implode('.', [$field]);
        if (! empty($field_value)) {
          if (! \is_array($field_value)) {
            // Print the field value.
            $rows[] = [
              $obj,
              $label,
              $attributes[$field],
              (\array_key_exists($field, $field_map)) ? $field_map[$field] : ''
            ];
          }
          else {
            // Print a row with an empty value.
            $rows[] = [
              $obj,
              $label,
              '',
              (\array_key_exists($field, $field_map)) ? $field_map[$field] : ''
            ];

            if (\array_key_exists(0, $field_value)) {
              // Loop over field values.
              foreach ($field_value as $i => $item_value) {
                $label = \implode('.', [$field, $i]);

                if (! \is_array($item_value)) {
                  // Print the field value.
                  $rows[] = [
                    $obj,
                    $label,
                    $attributes[$field][$i],
                    (\array_key_exists($field, $field_map)) ? $field_map[$field].'.'.$i : ''
                  ];
                }
                else {
                  // Print a row with an empty value.
                  $rows[] = [
                    $obj,
                    $label,
                    $attributes[$field][$i],
                    (\array_key_exists($field, $field_map)) ? $field_map[$field].'.'.$i : ''
                  ];

                  // Expand to property level.
                  foreach ($item_value as $prop => $prop_value) {
                    $label = \implode('.', [$field, $i, $prop]);

                    if (! empty($prop_value)) {
                      $rows[] = [
                        $obj,
                        $label,
                        $attributes[$field][$i][$prop],
                        (\array_key_exists($field, $field_map)) ? $field_map[$field].'.'.$i.'.'.$prop : ''
                      ];
                    }
                  }
                }
              }
            }
            else {
              // Expand to property level.
              foreach ($field_value as $prop => $prop_value) {
                $label = \implode('.', [$field, $prop]);

                if (! \is_array($prop_value) && ! empty($field_value)) {
                  $rows[] = [
                    $obj,
                    $label,
                    $attributes[$field][$prop],
                    (\array_key_exists($field, $field_map)) ? $field_map[$field].'.'.$prop : ''
                  ];
                }
                else {
                  // Print an empty row.
                  $rows[] = [
                    $obj,
                    $label,
                    '',
                    (\array_key_exists($field, $field_map)) ? $field_map[$field].'.'.$prop : ''
                  ];
                }
              }
            }
          }
        }
      }

      // Grab the ID.
      $rows[] = [
        JsonDataProcessor::ID_KEY,
        '',
        $this->jsonDataProcessor->getId($resource),
        self::REMOTE_ID
      ];

      // Grab the link.
      $rows[] = [
        JsonDataProcessor::LINKS_KEY,
        \implode('.',[JsonDataProcessor::SELF_KEY, JsonDataProcessor::HREF_KEY]),
        $this->jsonDataProcessor->getLink($resource, JsonDataProcessor::SELF_KEY),
        self::REMOTE_URL
      ];
    }

    if ($entity_type === self::COURSE_ENTITY) {
      if (\array_key_exists(JsonDataProcessor::META_KEY, $data)) {
        $metadata = $data[JsonDataProcessor::META_KEY];

        // Grab the metadata.
        $rows[] = [
          JsonDataProcessor::META_KEY,
          '',
          \json_encode($metadata, JSON_PRETTY_PRINT),
          self::JSON_META
        ];
      }
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return render($build);
  }

  /**
   * Validate the existence of an Institution entity.
   *
   * @param string $hei_id
   *   Institution ID to validate.
   *
   * @return array $result
   *   An array containing the validation status and related message.
   */
  public function validateInstitution(string $hei_id): array {
    // Check if an entity with the same hei_id already exists
    $exists = $this->heiManager
      ->getInstitution($hei_id);

    if (!empty($exists)) {
      foreach ($exists as $id => $hei) {
        $link = $hei->toLink();
        $renderable = $link->toRenderable();
      }

      $message = $this->t('Institution with ID <code>@hei_id</code> already exists: @link', [
        '@hei_id' => $hei_id,
        '@link' => render($renderable),
      ]);

      $status = TRUE;
    }
    else {
      $message = $this->t('Institution with ID <code>@hei_id</code> does not exist!', [
        '@hei_id' => $hei_id,
      ]);

      $status = FALSE;
    }

    $result['status'] = $status;
    $result['message'] = $message;

    return $result;
  }

  /**
   * Get the ID of a Programme entity;
   *   optionally, create a new entity from a TempStore.
   *
   * @param string $tempstore
   *   TempStore key with programme data.
   *
   * @return array|NULL
   *   An array of [id => Drupal\occapi_entities\Entity\Programme].
   */
  public function getProgramme(string $tempstore): ?array {
    // Validate the tempstore parameter.
    $error = $this->providerManager
      ->validateResourceTempstore(
        $tempstore,
        OccapiProviderManager::PROGRAMME_KEY
      );

    if ($error) {
      $this->messenger->addError($error);
      return NULL;
    }

    // Parse the tempstore key to get the OCCAPI provider and the resource ID.
    $components  = \explode('.', $tempstore);
    $provider_id = $components[0];
    $resource_id = $components[2];

    // Check if an entity with the same remote ID already exists.
    $exists = $this->entityTypeManager
      ->getStorage(self::PROGRAMME_ENTITY)
      ->loadByProperties([self::REMOTE_ID => $resource_id]);

    // Create a new entity if none exists.
    if (empty($exists)) {
      $new = $this->createProgrammme($provider_id, $resource_id);

      if (! empty($new)) {
        $exists = $this->entityTypeManager
          ->getStorage(self::PROGRAMME_ENTITY)
          ->loadByProperties([self::REMOTE_ID => $resource_id]);

        foreach ($exists as $id => $entity) {
          $renderable = $entity->toLink()->toRenderable();
        }
        $message = $this->t('Programme successfully created: @link', [
          '@link' => render($renderable),
        ]);
        $this->messenger->addMessage($message);
      }
      else {
        $message = $this->t('Programme cannot be created');
        $this->messenger->addError($message);
      }
    }
    else {
      foreach ($exists as $id => $entity) {
        $renderable = $entity->toLink()->toRenderable();
      }
      $message = $this->t('Programme already exists: @link', [
        '@link' => render($renderable),
      ]);
      $this->messenger->addWarning($message);
    }

    return $exists;
  }

  /**
   * Create a new Programme entity.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $programme_id
   *   Key found in the HEI list.
   *
   * @return array|NULL
   *   An array of [id => Drupal\occapi_entities\Entity\Programme].
   */
  private function createProgrammme(string $provider_id, string $programme_id): ?array {
    $provider = $this->providerManager
      ->getProvider($provider_id);

    $hei_id = $provider->get('hei_id');

    // Check if the Institution is present in the system.
    $result = $this->validateInstitution($hei_id);

    if (! $result['status']) {
      $this->messenger->addError($result['message']);
      return NULL;
    }
    else {
      $this->messenger->addMessage($result['message']);
    }

    // Load Programme data.
    $resource = $this->providerManager
      ->loadProgramme($provider_id, $programme_id);

    if (empty($resource)) {
      $this->messenger->addError($this->t('Missing programme data!'));
      return NULL;
    }

    $data = (\array_key_exists(JsonDataProcessor::DATA_KEY, $resource)) ?
      $resource[JsonDataProcessor::DATA_KEY] :
      $resource;

    if (! \array_key_exists(JsonDataProcessor::ATTR_KEY, $data)) {
      $this->messenger->addError($this->t('Missing programme attributes!'));
      return NULL;
    }

    $attributes = $data[JsonDataProcessor::ATTR_KEY];

    // Assemble data array for the new entity.
    $entity_data = [];

    // Start with the label.
    $entity_data[self::LABEL_KEY] = $this->jsonDataProcessor
      ->getTitle($resource);

    // Handle the attributes.
    $field_map = $this->programmeFieldMap();

    foreach ($field_map as $key => $value) {
      $entity_data[$value] = $attributes[$key];
    }

    // Handle the entity references.
    $hei = $this->heiManager
      ->getInstitution($hei_id);

    foreach ($hei as $id => $value) {
      $entity_data[self::REF_HEI] = ['target_id' => $id];
    }

    // Finally the remote API fields.
    $entity_data[self::REMOTE_ID] = $this->jsonDataProcessor
      ->getId($resource);

    $entity_data[self::REMOTE_URL] = $this->jsonDataProcessor
      ->getLink($resource, JsonDataProcessor::SELF_KEY);

    // Create the new entity.
    $new_entity = $this->entityTypeManager
      ->getStorage(self::PROGRAMME_ENTITY)
      ->create($entity_data);
    $new_entity->save();

    $created = $this->entityTypeManager
      ->getStorage(self::PROGRAMME_ENTITY)
      ->loadByProperties([self::REMOTE_ID => $programme_id]);

    return $created;
  }

  /**
   * Get the ID of a Course entity;
   *   optionally, create a new entity from a TempStore.
   *
   * @param string $tempstore
   *   TempStore key with course data.
   * @param string $filter
   *   OCCAPI entity type key used as filter.
   *
   * @return array|NULL
   *   An array of [id => Drupal\occapi_entities\Entity\Course].
   */
  public function getCourses(string $tempstore, string $filter): ?array {
    // Validate the tempstore parameter.
    $error = $this->providerManager
      ->validateCollectionTempstore(
        $tempstore,
        $filter,
        OccapiProviderManager::COURSE_KEY,
      );

    if ($error) {
      $this->messenger->addError($error);
      return NULL;
    }

    // Parse the tempstore key to get the OCCAPI provider and the resource ID.
    $components  = \explode('.', $tempstore);
    $provider_id = $components[0];
    $resource_id = $components[2];

    $existing_items = [];
    $created_items = [];
    $failed_items = [];

    if ($filter === self::PROGRAMME_ENTITY) {
      $collection = $this->providerManager
        ->loadProgrammeCourses($provider_id, $resource_id);

      if (
        ! empty($collection) &&
        \array_key_exists(JsonDataProcessor::DATA_KEY, $collection) &&
        ! empty($collection[JsonDataProcessor::DATA_KEY])
      ) {
        foreach ($collection[JsonDataProcessor::DATA_KEY] as $i => $item) {
          $item_id = $this->jsonDataProcessor->getId($item);

          // Check if an entity with the same remote ID already exists.
          $exists = $this->entityTypeManager
            ->getStorage(self::COURSE_ENTITY)
            ->loadByProperties([self::REMOTE_ID => $item_id]);

          // Create a new entity if none exists.
          if (empty($exists)) {
            $new = $this->createCourse($provider_id, $item);

            if (! empty($new)) {
              $exists = $this->entityTypeManager
                ->getStorage(self::COURSE_ENTITY)
                ->loadByProperties([self::REMOTE_ID => $item_id]);

              foreach ($exists as $id => $entity) {
                $created_items[$id] = $entity;;
              }
            }
            else {
              $failed_items[] = $item_id;
            }
          }
          else {
            foreach ($exists as $id => $entity) {
              $existing_items[$id] = $entity;
            }
          }
        }
      }
    }

    // Count the occurrences and issue messages accordingly.
    if (! empty($created_items)) {
      $message = $this->t('Successfully created @count Courses.', [
        '@count' => \count($created_items),
      ]);
      $this->messenger->addMessage($message);
    }

    if (! empty($existing_items)) {
      $message = $this->t('Found @count already existing Courses.', [
        '@count' => \count($existing_items),
      ]);
      $this->messenger->addWarning($message);
    }

    if (! empty($failed_items)) {
      $message = $this->t('Failed to import @count Courses', [
        '@count' => \count($failed_items),
      ]);
      $this->messenger->addMessage($message);
    }

    $courses = $created_items + $existing_items;

    if (! empty($courses)) {
      return $courses;
    }

    return NULL;
  }

  /**
   * Create a new Course entity.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param array $resource
   *   The resource array for the Course entity.
   * @param string $programme_id|NULL
   *   The resource ID for a related Programme entity.
   *
   * @return array|NULL
   *   An array of [id => Drupal\occapi_entities\Entity\Course].
   */
  private function createCourse(string $provider_id, array $resource, string $programme_id = NULL): ?array {
    if (empty($resource)) {
      return NULL;
    }

    $provider = $this->providerManager
      ->getProvider($provider_id);

    $hei_id = $provider->get('hei_id');

    // Check if the Institution is present in the system.
    $result = $this->validateInstitution($hei_id);

    if (! $result['status']) {
      return NULL;
    }

    $data = (\array_key_exists(JsonDataProcessor::DATA_KEY, $resource)) ?
      $resource[JsonDataProcessor::DATA_KEY] :
      $resource;

    if (! \array_key_exists(JsonDataProcessor::ATTR_KEY, $data)) {
      return NULL;
    }

    $attributes = $data[JsonDataProcessor::ATTR_KEY];

    // Assemble data array for the new entity.
    $entity_data = [];

    // Start with the label.
    $entity_data[self::LABEL_KEY] = $this->jsonDataProcessor
      ->getTitle($resource);

    // Handle the attributes.
    $field_map = $this->courseFieldMap();

    foreach ($field_map as $key => $value) {
      $entity_data[$value] = $attributes[$key];
    }

    // Handle the entity references.
    $hei = $this->heiManager
      ->getInstitution($hei_id);

    foreach ($hei as $id => $value) {
      $entity_data[self::REF_HEI] = ['target_id' => $id];
    }

    if (! empty($programme_id)) {
      $entity_data[self::REF_PROGRAMME] = ['target_id' => $programme_id];
    }

    // Finally the remote API fields.
    $resource_id = $this->jsonDataProcessor
      ->getId($resource);

    $entity_data[self::REMOTE_ID] = $resource_id;

    $entity_data[self::REMOTE_URL] = $this->jsonDataProcessor
      ->getLink($resource, JsonDataProcessor::SELF_KEY);

    if (\array_key_exists(JsonDataProcessor::META_KEY, $data)) {
      $json_metadata = \json_encode($data[JsonDataProcessor::META_KEY]);
      $entity_data[self::JSON_META] = $json_metadata;
    }

    // Create the new entity.
    $new_entity = $this->entityTypeManager
      ->getStorage(self::COURSE_ENTITY)
      ->create($entity_data);
    $new_entity->save();

    $created = $this->entityTypeManager
      ->getStorage(self::COURSE_ENTITY)
      ->loadByProperties([self::REMOTE_ID => $resource_id]);

    return $created;
  }

}
