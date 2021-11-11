<?php

namespace Drupal\occapi_client\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\JsonDataFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OCCAPI provider preview form.
 *
 * @property \Drupal\occapi_client\OccapiProviderInterface $entity
 */
class OccapiProviderPreviewForm extends EntityForm {

  const JSONAPI_RESPONSE  = 'JSON:API response';

  const HEI_KEY           = 'hei';
  const OUNIT_KEY         = 'ounit';
  const PROGRAMME_KEY     = 'programme';
  const COURSE_KEY        = 'course';

  /**
   * OCCAPI endpoint.
   *
   * @var string
   */
  protected $occapiEndpoint;

  /**
   * JSON data fetcher service.
   *
   * @var \Drupal\occapi_client\JsonDataFetcher
   */
  protected $jsonDataFetcher;

  /**
   * Data formatter service.
   *
   * @var \Drupal\occapi_client\DataFormatter
   */
  protected $dataFormatter;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->jsonDataFetcher = $container->get('occapi_client.fetch');
    $instance->dataFormatter = $container->get('occapi_client.format');
    $instance->loggerFactory = $container->get('logger.factory');
    $instance->logger = $instance->loggerFactory->get('occapi_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = [];

    $provider_id = $this->entity->id();
    $base_url = $this->entity->get('base_url');
    $hei_id = $this->entity->get('hei_id');
    $ounit_filter = $this->entity->get('ounit_filter');

    $this->occapiEndpoint = $base_url . '/' . self::HEI_KEY . '/' . $hei_id;

    $header_markup = '<h2>' . $this->entity->label() . '</h2>';

    $form['header'] = [
      '#type' => 'markup',
      '#markup' => $header_markup,
    ];

    // Primary tabs for automatic data requests.
    $form['primary'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Primary data'),
    ];

    // Prepare Institution data.
    $hei_tempstore = $provider_id . '.' . self::HEI_KEY . '.' . $hei_id;
    $hei_response = $this->jsonDataFetcher
      ->load($hei_tempstore, $this->occapiEndpoint);

    $hei_data = \json_decode($hei_response, TRUE);
    $hei_json = \json_encode(
      $hei_data[DataFormatter::DATA_KEY],
      JSON_PRETTY_PRINT
    );
    $hei_table = $this->dataFormatter
      ->resourceTable($hei_data);

    $hei_markup = '<p><code>GET ' . $this->occapiEndpoint . '</code></p>';
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

    if (
      array_key_exists(
        self::OUNIT_KEY,
        $hei_data[DataFormatter::LINKS_KEY]
      )
    ) {
      // Prepare Organizational Unit data.
      $ounit_table = DataFormatter::NOT_AVAILABLE;
      $ounit_json = DataFormatter::NOT_AVAILABLE;

      $ounit_tempstore = $provider_id . '.' . self::OUNIT_KEY;
      $ounit_endpoint = $hei_data[DataFormatter::LINKS_KEY][self::OUNIT_KEY][DataFormatter::HREF_KEY];
      $ounit_response = $this->jsonDataFetcher
        ->load($ounit_tempstore, $ounit_endpoint);

      $ounit_data = \json_decode($ounit_response, TRUE);
      $ounit_json = \json_encode(
        $ounit_data[DataFormatter::DATA_KEY],
        JSON_PRETTY_PRINT
      );
      $ounit_table = $this->dataFormatter
        ->collectionTable($ounit_data[DataFormatter::DATA_KEY]);

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

    if (
      ! $ounit_filter &&
      array_key_exists(
        self::PROGRAMME_KEY,
        $hei_data[DataFormatter::LINKS_KEY]
      )
    ) {
      // Prepare Programme data.
      $programme_table = DataFormatter::NOT_AVAILABLE;
      $programme_json = DataFormatter::NOT_AVAILABLE;

      $programme_tempstore = $provider_id . '.' . self::PROGRAMME_KEY;
      $programme_endpoint = $hei_data[DataFormatter::LINKS_KEY][self::PROGRAMME_KEY][DataFormatter::HREF_KEY];

      $programme_response = $this->jsonDataFetcher
        ->load($programme_tempstore, $programme_endpoint);

      $programme_data = \json_decode($programme_response, TRUE);
      $programme_json = \json_encode(
        $programme_data[DataFormatter::DATA_KEY],
        JSON_PRETTY_PRINT
      );
      $programme_table = $this->dataFormatter
        ->collectionTable($programme_data[DataFormatter::DATA_KEY]);

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

    if (
      ! $ounit_filter &&
      array_key_exists(self::COURSE_KEY, ($hei_data[DataFormatter::LINKS_KEY]))
    ) {
      // Prepare Course data.
      $course_table = DataFormatter::NOT_AVAILABLE;
      $course_json = DataFormatter::NOT_AVAILABLE;

      $course_tempstore = $provider_id . '.' . self::COURSE_KEY;
      $course_endpoint = $hei_data[DataFormatter::LINKS_KEY][self::COURSE_KEY][DataFormatter::HREF_KEY];
      $course_response = $this->jsonDataFetcher
        ->load($course_tempstore, $course_endpoint);

      $course_data = \json_decode($course_response, TRUE);
      $course_json = \json_encode(
        $course_data[DataFormatter::DATA_KEY],
        JSON_PRETTY_PRINT
      );
      $course_table = $this->dataFormatter
        ->collectionTable($course_data[DataFormatter::DATA_KEY]);

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

    if (
      array_key_exists(
        self::OUNIT_KEY,
        $hei_data[DataFormatter::LINKS_KEY]
      )
    ) {
      $ounit_titles = $this->dataFormatter
        ->collectionTitles($ounit_data[DataFormatter::DATA_KEY]);

      $ounit_links = $this->dataFormatter
        ->collectionLinks($ounit_data[DataFormatter::DATA_KEY]);

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

    if (
      ! $ounit_filter &&
      array_key_exists(
        self::PROGRAMME_KEY,
        $hei_data[DataFormatter::LINKS_KEY]
      )
    ) {
      $programme_titles = $this->dataFormatter
        ->collectionTitles($programme_data[DataFormatter::DATA_KEY]);

      $programme_links = $this->dataFormatter
        ->collectionLinks($programme_data[DataFormatter::DATA_KEY]);

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
      $ounit_links = $form_state->getValue('ounit_programme_links');
      $ounit_uri = $ounit_links[$ounit_id];

      $ounit_tempstore = $this->entity->id() . '.' . self::OUNIT_KEY;
      $ounit_tempstore .= '.' . $ounit_id;

      $message = $this->t('Retrieving ounit with ID: ') . $ounit_id;
      $this->logger->notice($message);

      $ounit_response = $this->jsonDataFetcher
        ->load($ounit_tempstore, $ounit_uri);

      $ounit_data = \json_decode($ounit_response, TRUE);
      $ounit_table = $this->dataFormatter
        ->resourceTable($ounit_data);

      $ounit_markup = '<p><code>GET ' . $ounit_uri . '</code></p>';
      $ounit_markup .= $ounit_table;

      $markup .= $ounit_markup;

      if (
        array_key_exists(
          self::PROGRAMME_KEY,
          $ounit_data[DataFormatter::LINKS_KEY]
        )
      ) {
        // Programme collection data.
        $programme_tempstore = $this->entity->id() . '.' . self::OUNIT_KEY;
        $programme_tempstore .= '.' . $ounit_id . '.' . self::PROGRAMME_KEY;

        $programme_endpoint = $ounit_data[DataFormatter::LINKS_KEY][self::PROGRAMME_KEY][DataFormatter::HREF_KEY];

        $programme_response = $this->jsonDataFetcher
          ->load($programme_tempstore, $programme_endpoint);

        $programme_data = \json_decode($programme_response, TRUE);
        $programme_table = $this->dataFormatter
          ->collectionTable($programme_data[DataFormatter::DATA_KEY]);

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
      $ounit_links = $form_state->getValue('ounit_course_links');
      $ounit_uri = $ounit_links[$ounit_id];

      $ounit_tempstore = $this->entity->id() . '.' . self::OUNIT_KEY;
      $ounit_tempstore .= '.' . $ounit_id;

      $message = $this->t('Retrieving ounit with ID: ') . $ounit_id;
      $this->logger->notice($message);

      $ounit_response = $this->jsonDataFetcher
        ->load($ounit_tempstore, $ounit_uri);

      $ounit_data = \json_decode($ounit_response, TRUE);
      $ounit_table = $this->dataFormatter
        ->resourceTable($ounit_data);

      $ounit_markup = '<p><code>GET ' . $ounit_uri . '</code></p>';
      $ounit_markup .= $ounit_table;

      $markup .= $ounit_markup;

      if (
        array_key_exists(
          self::COURSE_KEY,
          $ounit_data[DataFormatter::LINKS_KEY]
        )
      ) {
        // Course collection data.
        $course_tempstore = $this->entity->id() . '.' . self::OUNIT_KEY;
        $course_tempstore .= '.' . $ounit_id . '.' . self::COURSE_KEY;

        $course_endpoint = $ounit_data[DataFormatter::LINKS_KEY][self::COURSE_KEY][DataFormatter::HREF_KEY];
        $course_response = $this->jsonDataFetcher
          ->load($course_tempstore, $course_endpoint);

        $course_data = \json_decode($course_response, TRUE);
        $course_table = $this->dataFormatter
          ->collectionTable($course_data[DataFormatter::DATA_KEY]);

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
      $programme_links = $form_state->getValue('programme_course_links');
      $programme_uri = $programme_links[$programme_id];

      $programme_tempstore = $this->entity->id() . '.' . self::PROGRAMME_KEY;
      $programme_tempstore .= '.' . $programme_id;

      $message = $this->t('Retrieving programme with ID: ') . $programme_id;
      $this->logger->notice($message);

      $programme_response = $this->jsonDataFetcher
        ->load($programme_tempstore, $programme_uri);

      $programme_data = \json_decode($programme_response, TRUE);
      $programme_table = $this->dataFormatter
        ->resourceTable($programme_data);

      $programme_markup = '<p><code>GET ' . $programme_uri . '</code></p>';
      $programme_markup .= $programme_table;

      $markup .= $programme_markup;

      if (
        array_key_exists(
          self::COURSE_KEY,
          $programme_data[DataFormatter::LINKS_KEY]
        )
      ) {
        // Course collection data.
        $course_tempstore = $this->entity->id() . '.' . self::PROGRAMME_KEY;
        $course_tempstore .= '.' . $programme_id . '.' . self::COURSE_KEY;

        $course_endpoint = $programme_data[DataFormatter::LINKS_KEY][self::COURSE_KEY][DataFormatter::HREF_KEY];
        $course_response = $this->jsonDataFetcher
          ->load($course_tempstore, $course_endpoint);

        $course_data = \json_decode($course_response, TRUE);
        $course_table = $this->dataFormatter
          ->collectionTable($course_data[DataFormatter::DATA_KEY]);

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
