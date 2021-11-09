<?php

namespace Drupal\occapi_client\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\occapi_client\JsonDataFetcher;
use Drupal\occapi_client\DataFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OCCAPI provider preview form.
 *
 * @property \Drupal\occapi_client\OccapiProviderInterface $entity
 */
class OccapiProviderPreviewForm extends EntityForm {

  const JSONAPI_DATA      = 'JSON:API data';
  const JSONAPI_RESPONSE  = 'JSON:API response';

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->jsonDataFetcher = $container->get('occapi_client.fetch');
    $instance->dataFormatter = $container->get('occapi_client.format');
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

    $this->occapiEndpoint = $base_url . '/hei/' . $hei_id;

    $header_markup = '<h2>' . $this->entity->label() . '</h2>';
    $header_markup .= '<p>URL: <code>' . $this->occapiEndpoint . '</code></p>';

    $form['header'] = [
      '#type' => 'markup',
      '#markup' => $header_markup,
    ];

    $hei_tempstore = $provider_id . '.hei.' . $hei_id;
    $hei_response = $this->jsonDataFetcher
      ->load($hei_tempstore, $this->occapiEndpoint);

    $hei_data = \json_decode($hei_response, TRUE);
    $hei_table = $this->dataFormatter
      ->resourceTable($hei_data);
    $hei_json = \json_encode($hei_data['data'], JSON_PRETTY_PRINT);

    // Institution data.
    $form['hei_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Institution data'),
      '#tree' => FALSE,
    ];

    $form['hei_wrapper']['data'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => self::JSONAPI_DATA,
    ];

    $form['hei_wrapper']['data']['markup'] = [
      '#type' => 'markup',
      '#markup' => $hei_table,
    ];

    $form['hei_wrapper']['response'] = [
      '#type' => 'details',
      '#title' => self::JSONAPI_RESPONSE,
    ];

    $form['hei_wrapper']['response']['markup'] = [
      '#type' => 'markup',
      '#markup' => '<pre>' . $hei_json . '</pre>',
    ];

    // Organizational Unit data
    $ounit_table = DataFormatter::NOT_AVAILABLE;
    $ounit_json = DataFormatter::NOT_AVAILABLE;

    if (array_key_exists('ounit', ($hei_data['links']))) {
      $ounit_tempstore = $provider_id . '.ounit';
      $ounit_endpoint = $this->occapiEndpoint . '/ounit';
      $ounit_response = $this->jsonDataFetcher
        ->load($ounit_tempstore, $ounit_endpoint);

      $ounit_data = \json_decode($ounit_response, TRUE);
      $ounit_table = $this->dataFormatter
        ->collectionTable($ounit_data['data']);
      $ounit_json = \json_encode($ounit_data['data'], JSON_PRETTY_PRINT);
    }

    $form['ounit_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Organizational Unit data'),
      '#tree' => FALSE,
    ];

    $form['ounit_wrapper']['data'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => self::JSONAPI_DATA,
    ];

    $form['ounit_wrapper']['data']['markup'] = [
      '#type' => 'markup',
      '#markup' => $ounit_table,
    ];

    $form['ounit_wrapper']['response'] = [
      '#type' => 'details',
      '#title' => self::JSONAPI_RESPONSE,
    ];

    $form['ounit_wrapper']['response']['markup'] = [
      '#type' => 'markup',
      '#markup' => '<pre>' . $ounit_json . '</pre>',
    ];

    // Programme data.
    $programme_table = DataFormatter::NOT_AVAILABLE;
    $programme_json = DataFormatter::NOT_AVAILABLE;

    if (array_key_exists('programme', ($hei_data['links']))) {
      $programme_tempstore = $provider_id . '.programme';
      $programme_endpoint = $this->occapiEndpoint . '/programme';
      $programme_response = $this->jsonDataFetcher
        ->load($programme_tempstore, $programme_endpoint);

      $programme_data = \json_decode($programme_response, TRUE);
      $programme_table = $this->dataFormatter
        ->collectionTable($programme_data['data']);
      $programme_json = \json_encode($programme_data['data'], JSON_PRETTY_PRINT);
    }

    $form['programme_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Programme data'),
      '#tree' => FALSE,
    ];

    $form['programme_wrapper']['data'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => self::JSONAPI_DATA,
    ];

    $form['programme_wrapper']['data']['markup'] = [
      '#type' => 'markup',
      '#markup' => $programme_table,
    ];

    $form['programme_wrapper']['response'] = [
      '#type' => 'details',
      '#title' => self::JSONAPI_RESPONSE,
    ];

    $form['programme_wrapper']['response']['markup'] = [
      '#type' => 'markup',
      '#markup' => '<pre>' . $programme_json . '</pre>',
    ];

    // Course data.
    $course_table = DataFormatter::NOT_AVAILABLE;
    $course_json = DataFormatter::NOT_AVAILABLE;

    if (array_key_exists('course', ($hei_data['links']))) {
      $course_tempstore = $provider_id . '.course';
      $course_endpoint = $this->occapiEndpoint . '/course';
      $course_response = $this->jsonDataFetcher
        ->load($course_tempstore, $course_endpoint);

      $course_data = \json_decode($course_response, TRUE);
      $course_table = $this->dataFormatter
        ->collectionTable($course_data['data']);
      $course_json = \json_encode($course_data['data'], JSON_PRETTY_PRINT);
    }

    $form['course_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Course data'),
      '#tree' => FALSE,
    ];

    $form['course_wrapper']['data'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => self::JSONAPI_DATA,
    ];

    $form['course_wrapper']['data']['markup'] = [
      '#type' => 'markup',
      '#markup' => $course_table,
    ];

    $form['course_wrapper']['response'] = [
      '#type' => 'details',
      '#title' => self::JSONAPI_RESPONSE,
    ];

    $form['course_wrapper']['response']['markup'] = [
      '#type' => 'markup',
      '#markup' => '<pre>' . $course_json . '</pre>',
    ];

    // dpm($this->occapiEndpoint);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    return $result;
  }

}
