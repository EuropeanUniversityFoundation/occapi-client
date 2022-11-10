<?php

namespace Drupal\occapi_client\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\JsonDataProcessorInterface;
use Drupal\occapi_client\JsonDataSchemaInterface;
use Drupal\occapi_client\OccapiDataLoaderInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OCCAPI provider preview form.
 *
 * @property \Drupal\occapi_client\OccapiProviderInterface $entity
 */
class OccapiProviderPreviewForm extends EntityForm {

  const JSONAPI_RESPONSE  = 'JSON:API response';

  const DATA_KEY  = JsonDataSchemaInterface::JSONAPI_DATA;
  const LINKS_KEY = JsonDataSchemaInterface::JSONAPI_LINKS;
  const SELF_KEY = JsonDataSchemaInterface::JSONAPI_SELF;

  const TYPE_OUNIT = OccapiTempStoreInterface::TYPE_OUNIT;
  const TYPE_PROGRAMME = OccapiTempStoreInterface::TYPE_PROGRAMME;
  const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

  /**
   * OCCAPI Institution resource.
   *
   * @var array
   */
  protected $heiResource;

  /**
  * The data formatter.
  *
  * @var \Drupal\occapi_client\DataFormatter
  */
  protected $dataFormatter;

  /**
  * The data loader.
  *
  * @var \Drupal\occapi_client\OccapiDataLoaderInterface
  */
  protected $dataLoader;

  /**
   * The JSON data processor.
   *
   * @var \Drupal\occapi_client\JsonDataProcessorInterface
   */
  protected $jsonDataProcessor;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->dataFormatter        = $container->get('occapi_client.format');
    $instance->dataLoader           = $container->get('occapi_client.load');
    $instance->jsonDataProcessor    = $container->get('occapi_client.json');
    $instance->messenger            = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = [];

    $provider_id  = $this->entity->id();
    $ounit_filter = $this->entity->get('ounit_filter');

    $form['header'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->entity->label() . '</h2>',
    ];

    // Primary tabs for automatic data requests.
    $form['primary'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Primary data'),
    ];

    // Prepare Institution data.
    $this->heiResource = $this->dataLoader->loadInstitution($provider_id);

    if (empty($this->heiResource)) {
      $this->messenger->addError($this->t('No available data!'));
      return $form;
    }

    $hei_data = $this->heiResource[self::DATA_KEY];
    $hei_json = \json_encode($hei_data, JSON_PRETTY_PRINT);
    $hei_links = $this->heiResource[self::LINKS_KEY];
    $hei_table = $this->dataFormatter->resourceTable($this->heiResource);
    $hei_endpoint = $this->jsonDataProcessor
      ->getResourceLinkByType($this->heiResource, self::SELF_KEY);

    $hei_markup = '<p><code>GET ' . $hei_endpoint . '</code></p>';
    $hei_markup .= $hei_table;

    // Display Institution data.
    $form['hei_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Institution data'),
      '#tree' => FALSE,
      '#group' => 'primary'
    ];

    $form['hei_wrapper']['markup'] = [
      '#type' => 'markup',
      '#markup' => $hei_markup,
    ];

    $form['hei_wrapper']['response'] = [
      '#type' => 'details',
      '#title' => self::JSONAPI_RESPONSE,
    ];

    $form['hei_wrapper']['response']['markup'] = [
      '#type' => 'markup',
      '#markup' => '<pre>' . $hei_json . '</pre>',
    ];

    if (array_key_exists(self::TYPE_OUNIT, $hei_links)) {
      // Prepare Organizational Unit data.
      $ounit_collection = $this->dataLoader->loadOunits($provider_id);
      $ounit_data = $ounit_collection[self::DATA_KEY];
      $ounit_json = \json_encode($ounit_data, JSON_PRETTY_PRINT);
      $ounit_table = $this->dataFormatter->collectionTable($ounit_collection);
      $ounit_endpoint = $this->jsonDataProcessor
        ->getResourceLinkByType($this->heiResource, self::TYPE_OUNIT);

      $ounit_markup = '<p><code>GET ' . $ounit_endpoint . '</code></p>';
      $ounit_markup .= $ounit_table;

      // Display Organizational Unit data.
      $form['ounit_wrapper'] = [
        '#type' => 'details',
        '#title' => $this->t('Organizational Unit data'),
        '#tree' => FALSE,
        '#group' => 'primary'
      ];

      $form['ounit_wrapper']['markup'] = [
        '#type' => 'markup',
        '#markup' => $ounit_markup,
      ];

      $form['ounit_wrapper']['response'] = [
        '#type' => 'details',
        '#title' => self::JSONAPI_RESPONSE,
      ];

      $form['ounit_wrapper']['response']['markup'] = [
        '#type' => 'markup',
        '#markup' => '<pre>' . $ounit_json . '</pre>',
      ];
    }

    if (!$ounit_filter && array_key_exists(self::TYPE_PROGRAMME, $hei_links)) {
      // Prepare Programme data.
      $programme_collection = $this->dataLoader->loadProgrammes($provider_id);
      $programme_data = $programme_collection[self::DATA_KEY];
      $programme_json = \json_encode($programme_data,JSON_PRETTY_PRINT);
      $programme_table = $this->dataFormatter
        ->collectionTable($programme_collection);
      $programme_endpoint = $this->jsonDataProcessor
        ->getResourceLinkByType($this->heiResource, self::TYPE_PROGRAMME);

      $programme_markup = '<p><code>GET ' . $programme_endpoint . '</code></p>';
      $programme_markup .= $programme_table;

      // Display Programme data.
      $form['programme_wrapper'] = [
        '#type' => 'details',
        '#title' => $this->t('Programme data'),
        '#tree' => FALSE,
        '#group' => 'primary'
      ];

      $form['programme_wrapper']['markup'] = [
        '#type' => 'markup',
        '#markup' => $programme_markup,
      ];

      $form['programme_wrapper']['response'] = [
        '#type' => 'details',
        '#title' => self::JSONAPI_RESPONSE,
      ];

      $form['programme_wrapper']['response']['markup'] = [
        '#type' => 'markup',
        '#markup' => '<pre>' . $programme_json . '</pre>',
      ];
    }

    if (!$ounit_filter && array_key_exists(self::TYPE_COURSE, $hei_links)) {
      // Prepare Course data.
      $course_collection = $this->dataLoader->loadCourses($provider_id);
      $course_data = $course_collection[self::DATA_KEY];
      $course_json = \json_encode($course_data, JSON_PRETTY_PRINT);
      $course_table = $this->dataFormatter->collectionTable($course_collection);
      $course_endpoint = $this->jsonDataProcessor
        ->getResourceLinkByType($this->heiResource, self::TYPE_COURSE);

      $course_markup = '<p><code>GET ' . $course_endpoint . '</code></p>';
      $course_markup .= $course_table;

      // Display Course data.
      $form['course_wrapper'] = [
        '#type' => 'details',
        '#title' => $this->t('Course data'),
        '#tree' => FALSE,
        '#group' => 'primary'
      ];

      $form['course_wrapper']['markup'] = [
        '#type' => 'markup',
        '#markup' => $course_markup,
      ];

      $form['course_wrapper']['response'] = [
        '#type' => 'details',
        '#title' => self::JSONAPI_RESPONSE,
      ];

      $form['course_wrapper']['response']['markup'] = [
        '#type' => 'markup',
        '#markup' => '<pre>' . $course_json . '</pre>',
      ];
    }

    // Secondary tabs for data requests based on select options.
    $form['secondary'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Secondary data'),
    ];

    if (array_key_exists(self::TYPE_OUNIT, $hei_links)) {
      $ounit_titles = $this->jsonDataProcessor
        ->getResourceTitles($ounit_collection);
      $ounit_links = $this->jsonDataProcessor
        ->getResourceLinks($ounit_collection);

      // Select ounit to display programme data.
      $form['ounit_programme_wrapper'] = [
        '#type' => 'details',
        '#title' => $this->t('Programme data per Organizational Unit'),
        '#tree' => FALSE,
        '#group' => 'secondary'
      ];

      $form['ounit_programme_wrapper']['ounit_programme_links'] = [
        '#type' => 'value',
        '#value' => $ounit_links,
      ];

      $form['ounit_programme_wrapper']['ounit_programme_select'] = [
        '#type' => 'select',
        '#options' => $ounit_titles,
        '#empty_option' => $this->t('- Select an Organizational Unit -'),
        '#default_value' => NULL,
        '#ajax' => [
          'callback' => '::ounitProgrammeTable',
          'disable-refocus' => TRUE,
          'event' => 'change',
          'wrapper' => 'ounit_programme_table',
        ],
      ];

      $form['ounit_programme_wrapper']['ounit_programme_table'] = [
        '#type' => 'markup',
        '#markup' => '<div id="ounitProgrammeTable"></div>',
      ];

      // Select ounit to display course data.
      $form['ounit_course_wrapper'] = [
        '#type' => 'details',
        '#title' => $this->t('Course data per Organizational Unit'),
        '#tree' => FALSE,
        '#group' => 'secondary'
      ];

      $form['ounit_course_wrapper']['ounit_course_links'] = [
        '#type' => 'value',
        '#value' => $ounit_links,
      ];

      $form['ounit_course_wrapper']['ounit_course_select'] = [
        '#type' => 'select',
        '#options' => $ounit_titles,
        '#empty_option' => $this->t('- Select an Organizational Unit -'),
        '#default_value' => NULL,
        '#attributes' => [
          'name' => 'ounit_course_select',
        ],
        '#ajax' => [
          'callback' => '::ounitCourseTable',
          'disable-refocus' => TRUE,
          'event' => 'change',
          'wrapper' => 'ounit_course_table',
        ],
      ];

      $form['ounit_course_wrapper']['ounit_course_table'] = [
        '#type' => 'markup',
        '#markup' => '<div id="ounitCourseTable"></div>',
      ];
    }

    if (!$ounit_filter && array_key_exists(self::TYPE_PROGRAMME, $hei_links)) {
      $programme_titles = $this->jsonDataProcessor
        ->getResourceTitles($programme_collection);
      $programme_links = $this->jsonDataProcessor
        ->getResourceLinks($programme_collection);

      // Select programme to display course data.
      $form['programme_course_wrapper'] = [
        '#type' => 'details',
        '#title' => $this->t('Course data per Programme'),
        '#tree' => FALSE,
        '#group' => 'secondary'
      ];

      $form['programme_course_wrapper']['programme_course_links'] = [
        '#type' => 'value',
        '#value' => $programme_links,
      ];

      $form['programme_course_wrapper']['programme_course_select'] = [
        '#type' => 'select',
        '#options' => $programme_titles,
        '#empty_option' => $this->t('- Select a Programme -'),
        '#default_value' => NULL,
        '#attributes' => [
          'name' => 'programme_course_select',
        ],
        '#ajax' => [
          'callback' => '::programmeCourseTable',
          'disable-refocus' => TRUE,
          'event' => 'change',
          'wrapper' => 'programme_course_table',
        ],
      ];

      $form['programme_course_wrapper']['programme_course_table'] = [
        '#type' => 'markup',
        '#markup' => '<div id="programmeCourseTable"></div>',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = [];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    return $result;
  }

  /**
  * AJAX callback to generate and display a ounit + programme table.
  */
  public function ounitProgrammeTable(array $form, FormStateInterface $form_state) {
    $markup = '';

    $ounit_id = $form_state->getValue('ounit_programme_select');

    if ($ounit_id) {
      // OUnit resource data.
      $ounit_data = $this->dataLoader
        ->loadOunit($this->entity->id(), $ounit_id);
      $ounit_links = $ounit_data[self::LINKS_KEY];
      $ounit_table = $this->dataFormatter->resourceTable($ounit_data);
      $ounit_endpoint = $this->jsonDataProcessor
        ->getResourceLinkByType($this->heiResource, self::TYPE_OUNIT);

      $ounit_markup = '<p><code>GET ' . $ounit_endpoint . '</code></p>';
      $ounit_markup .= $ounit_table;

      $markup .= $ounit_markup;

      if (array_key_exists(self::TYPE_PROGRAMME, $ounit_links)) {
        // Programme collection data.
        $programme_collection = $this->dataLoader
          ->loadOunitProgrammes($this->entity->id(), $ounit_id);
        $programme_table = $this->dataFormatter
          ->collectionTable($programme_collection);
        $programme_endpoint = $this->jsonDataProcessor
          ->getResourceLinkByType($this->heiResource, self::TYPE_PROGRAMME);

        $programme_markup = '<p><code>GET ' . $programme_endpoint . '</code></p>';
        $programme_markup .= $programme_table;

        $markup .= '<hr />' . $programme_markup;
      }
    }

    $ajax_response = new AjaxResponse();
    $ajax_response
      ->addCommand(new HtmlCommand('#ounitProgrammeTable', $markup));
    return $ajax_response;
  }

  /**
  * AJAX callback to generate and display a ounit + course table.
  */
  public function ounitCourseTable(array $form, FormStateInterface $form_state) {
    $markup = '';

    $ounit_id = $form_state->getValue('ounit_course_select');

    if ($ounit_id) {
      // OUnit resource data.
      $ounit_data = $this->dataLoader
        ->loadOunit($this->entity->id(), $ounit_id);
      $ounit_links = $ounit_data[self::LINKS_KEY];
      $ounit_table = $this->dataFormatter->resourceTable($ounit_data);
      $ounit_endpoint = $this->jsonDataProcessor
        ->getResourceLinkByType($this->heiResource, self::TYPE_OUNIT);

      $ounit_markup = '<p><code>GET ' . $ounit_endpoint . '</code></p>';
      $ounit_markup .= $ounit_table;

      $markup .= $ounit_markup;

      if (array_key_exists(self::TYPE_COURSE, $ounit_links)) {
        // Course collection data.
        $course_collection = $this->dataLoader
          ->loadOunitCourses($this->entity->id(), $ounit_id);
        $course_table = $this->dataFormatter
          ->collectionTable($course_collection);
        $course_endpoint = $this->jsonDataProcessor
          ->getResourceLinkByType($this->heiResource, self::TYPE_COURSE);

        $course_markup = '<p><code>GET ' . $course_endpoint . '</code></p>';
        $course_markup .= $course_table;

        $markup .= '<hr />' . $course_markup;
      }
    }

    $ajax_response = new AjaxResponse();
    $ajax_response
      ->addCommand(new HtmlCommand('#ounitCourseTable', $markup));
    return $ajax_response;
  }

  /**
  * AJAX callback to generate and display a programme + course table.
  */
  public function programmeCourseTable(array $form, FormStateInterface $form_state) {
    $markup = '';

    $programme_id = $form_state->getValue('programme_course_select');

    if ($programme_id) {
      // Programme resource data.
      $programme_data = $this->dataLoader
        ->loadProgramme($this->entity->id(), $programme_id);
      $programme_links = $programme_data[self::LINKS_KEY];
      $programme_table = $this->dataFormatter->resourceTable($programme_data);
      $programme_endpoint = $this->jsonDataProcessor
        ->getResourceLinkByType($this->heiResource, self::TYPE_PROGRAMME);

      $programme_markup = '<p><code>GET ' . $programme_endpoint . '</code></p>';
      $programme_markup .= $programme_table;

      $markup .= $programme_markup;

      if (array_key_exists(self::TYPE_COURSE, $programme_links)) {
        // Course collection data.
        $course_collection = $this->dataLoader
          ->loadProgrammeCourses($this->entity->id(), $programme_id);
        $course_table = $this->dataFormatter
          ->collectionTable($course_collection);
        $course_endpoint = $this->jsonDataProcessor
          ->getResourceLinkByType($this->heiResource, self::TYPE_COURSE);

        $course_markup = '<p><code>GET ' . $course_endpoint . '</code></p>';
        $course_markup .= $course_table;

        $markup .= '<hr />' . $course_markup;
      }
    }

    $ajax_response = new AjaxResponse();
    $ajax_response
      ->addCommand(new HtmlCommand('#programmeCourseTable', $markup));
    return $ajax_response;
  }

}
