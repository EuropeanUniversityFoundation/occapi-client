<?php

namespace Drupal\occapi_client\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\occapi_client\JsonDataFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OCCAPI provider preview form.
 *
 * @property \Drupal\occapi_client\OccapiProviderInterface $entity
 */
class OccapiProviderPreviewForm extends EntityForm {

  const NOT_AVAILABLE = '<em>n/a</em>';
  const JSONAPI_RESPONSE = 'JSON:API data';

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->jsonDataFetcher = $container->get('occapi_client.fetch');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = [];

    $base_url = $this->entity->get('base_url');
    $hei_id = $this->entity->get('hei_id');

    $this->occapiEndpoint = $base_url . '/hei/' . $hei_id;

    $header_markup = '<h2>' . $this->entity->label() . '</h2>';
    $header_markup .= '<p>URL: <code>' . $this->occapiEndpoint . '</code></p>';

    $form['header'] = [
      '#type' => 'markup',
      '#markup' => $header_markup,
    ];

    $hei_response = $this->jsonDataFetcher->get($this->occapiEndpoint);
    $hei_data = \json_decode($hei_response, TRUE);
    $hei_data_pretty = \json_encode($hei_data['data'], JSON_PRETTY_PRINT);

    // Institution data.
    $form['hei_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Institution data'),
      '#tree' => FALSE,
    ];

    $form['hei_wrapper']['response'] = [
      '#type' => 'details',
      '#title' => self::JSONAPI_RESPONSE,
    ];

    $form['hei_wrapper']['response']['markup'] = [
      '#type' => 'markup',
      '#markup' => '<pre>' . $hei_data_pretty . '</pre>',
    ];

    // Organizational Unit data
    $ounit_data_pretty = self::NOT_AVAILABLE;

    if (array_key_exists('ounit', ($hei_data['links']))) {
      $ounit_endpoint = $this->occapiEndpoint . '/ounit';
      $ounit_response = $this->jsonDataFetcher->get($ounit_endpoint);
      $ounit_data = \json_decode($ounit_response, TRUE);
      $ounit_data_pretty = \json_encode($ounit_data['data'], JSON_PRETTY_PRINT);
    }

    $form['ounit_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Organizational Unit data'),
      '#tree' => FALSE,
    ];

    $form['ounit_wrapper']['response'] = [
      '#type' => 'details',
      '#title' => self::JSONAPI_RESPONSE,
    ];

    $form['ounit_wrapper']['response']['markup'] = [
      '#type' => 'markup',
      '#markup' => '<pre>' . $ounit_data_pretty . '</pre>',
    ];

    // Programme data.
    $programme_data_pretty = self::NOT_AVAILABLE;

    if (array_key_exists('programme', ($hei_data['links']))) {
      $programme_endpoint = $this->occapiEndpoint . '/programme';
      $programme_response = $this->jsonDataFetcher->get($programme_endpoint);
      $programme_data = \json_decode($programme_response, TRUE);
      $programme_data_pretty = \json_encode($programme_data['data'], JSON_PRETTY_PRINT);
    }

    $form['programme_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Programme data'),
      '#tree' => FALSE,
    ];

    $form['programme_wrapper']['response'] = [
      '#type' => 'details',
      '#title' => self::JSONAPI_RESPONSE,
    ];

    $form['programme_wrapper']['response']['markup'] = [
      '#type' => 'markup',
      '#markup' => '<pre>' . $programme_data_pretty . '</pre>',
    ];

    // Course data.
    $course_data_pretty = self::NOT_AVAILABLE;

    if (array_key_exists('course', ($hei_data['links']))) {
      $course_endpoint = $this->occapiEndpoint . '/course';
      $course_response = $this->jsonDataFetcher->get($course_endpoint);
      $course_data = \json_decode($course_response, TRUE);
      $course_data_pretty = \json_encode($course_data['data'], JSON_PRETTY_PRINT);
    }

    $form['course_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Course data'),
      '#tree' => FALSE,
    ];

    $form['course_wrapper']['response'] = [
      '#type' => 'details',
      '#title' => self::JSONAPI_RESPONSE,
    ];

    $form['course_wrapper']['response']['markup'] = [
      '#type' => 'markup',
      '#markup' => '<pre>' . $course_data_pretty . '</pre>',
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
