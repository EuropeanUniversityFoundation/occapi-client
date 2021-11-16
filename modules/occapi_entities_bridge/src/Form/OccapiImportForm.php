<?php

namespace Drupal\occapi_entities_bridge\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element\StatusMessages;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\JsonDataProcessor;
use Drupal\occapi_client\OccapiProviderManager;
use Drupal\occapi_entities_bridge\OccapiImportManager as Manager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OCCAPI entities import form.
 */
class OccapiImportForm extends FormBase {

  /**
   * OCCAPI Institution resource.
   *
   * @var array
   */
  protected $heiResource;

  /**
   * Empty data placeholder.
   *
   * @var string
   */
  protected $emptyData;

  /**
   * Data formatter service.
   *
   * @var \Drupal\occapi_client\DataFormatter
   */
  protected $dataFormatter;

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * JSON data processing service.
   *
   * @var \Drupal\occapi_client\JsonDataProcessor
   */
  protected $jsonDataProcessor;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->dataFormatter      = $container->get('occapi_client.format');
    $instance->importManager      = $container->get('occapi_entities_bridge.manager');
    $instance->jsonDataProcessor  = $container->get('occapi_client.json');
    $instance->messenger          = $container->get('messenger');
    $instance->providerManager    = $container->get('occapi_client.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'occapi_entities_bridge_occapi_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $tempstore = NULL) {
    $user = \Drupal::currentUser();

    // Give a user with permission the opportunity to add an entity manually
    if ($user->hasPermission('bypass import occapi entities')) {
      $add_programme_link = Link::fromTextAndUrl(t('add a new Programme'),
        Url::fromRoute('entity.programme.add_form'))->toString();
      $add_course_link = Link::fromTextAndUrl(t('add a new Course'),
        Url::fromRoute('entity.course.add_form'))->toString();

      $warning = $this->t('You can bypass this form and @add_programme or @add_course manually.',[
        '@add_programme' => $add_programme_link,
        '@add_course' => $add_course_link
      ]);

      $form['messages'] = [
        '#type' => 'markup',
        '#markup' => $warning,
        '#weight' => '-20'
      ];
    }

    // Validate the tempstore parameter.
    $error = $this->providerManager
      ->validateResourceTempstore($tempstore, OccapiProviderManager::PROGRAMME_KEY);

    if ($error) {
      $this->messenger->addError($error);
      return $form;
    }

    // Parse the tempstore parameter to get the OCCAPI provider an its HEI ID.
    $components = \explode('.', $tempstore);
    $provider = $this->providerManager
      ->getProvider($components[0]);
    $hei_id = $provider->get('hei_id');

    // Check if the Institution is present in the system.
    $result = $this->importManager
      ->validateInstitution($hei_id);

    if (! $result['status']) {
      $this->messenger->addError($result['message']);
      return $form;
    }
    else {
      $this->messenger->addMessage($result['message']);
    }

    // dpm($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
