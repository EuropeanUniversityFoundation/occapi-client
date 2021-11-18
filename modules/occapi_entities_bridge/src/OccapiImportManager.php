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
   *   An array containing a JSON:API resource collection.
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

        dpm($metadata);

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

}
