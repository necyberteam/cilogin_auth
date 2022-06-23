<?php

namespace Drupal\cilogon_auth\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Console\Bootstrap\Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\cilogon_auth\CILogonAuthStateToken;
use Exception;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base class for CILogon Auth client plugins.
 */
abstract class CILogonAuthClientBase extends PluginBase implements CILogonAuthClientInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The request stack used to access request globals.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory used for logging.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The kill switch service
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * The constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin identifier.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The http client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $kill_switch
   *   The kill switch service.
   */
  public function __construct(
      array $configuration,
      $plugin_id,
      $plugin_definition,
      RequestStack $request_stack,
      ClientInterface $http_client,
      LoggerChannelFactoryInterface $logger_factory,
      LanguageManagerInterface $language_manager,
      KillSwitch $kill_switch
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $this->requestStack = $request_stack;
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
    $this->languageManager = $language_manager;
    $this->killSwitch = $kill_switch;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
      ContainerInterface $container,
      array $configuration,
      $plugin_id,
      $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('language_manager'),
      $container->get('page_cache_kill_switch')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'client_id' => '',
      'client_secret' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $redirect_url = URL::fromRoute(
      'cilogon_auth.redirect_controller_redirect',
      [
        'client_name' => $this->pluginId,
      ],
      [
        'absolute' => TRUE,
      ]
    );
    $form['redirect_url'] = [
      '#title' => $this->t('Callback URL'),
      '#type' => 'item',
      '#markup' => $redirect_url->toString(),
      '#description' => '(This must use https)'
    ];
    $form['client_id'] = [
      '#title' => $this->t('Client ID'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['client_id'],
    ];
    $form['client_secret'] = [
      '#title' => $this->t('Client secret'),
      '#type' => 'textfield',
      '#maxlength' => 1024,
      '#default_value' => $this->configuration['client_secret'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Provider label as array for StringTranslationTrait::t() argument.
    $provider = [
      '@provider' => $this->getPluginDefinition()['label'],
    ];

    // Get plugin setting values.
    $configuration = $form_state->getValues();

    // Whether a client ID is given.
    if (empty($configuration['client_id'])) {
      $form_state->setErrorByName('client_id', $this->t('The client ID is missing for @provider.', $provider));
    }
    // Whether a client secret is given.
    if (empty($configuration['client_secret'])) {
      $form_state->setErrorByName('client_secret', $this->t('The client secret is missing for @provider.', $provider));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // No need to do anything, but make the function have a body anyway
    // so that it's callable by overriding methods.
  }

  /**
   * Implements CILogonAuthClientInterface::getEndpoints().
   */
  public function getEndpoints() {
    throw new Exception('Unimplemented method getEndpoints().');
  }

  /**
   * Implements CILogonAuthClientInterface::authorize().
   *
   * @param string $scope
   *   A string of scopes.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   A trusted redirect response object.
   */
  public function authorize($scope = 'openid email') {
    $language_none = $this->languageManager
      ->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE);
    $redirect_uri = Url::fromRoute(
      'cilogon_auth.redirect_controller_redirect',
      [
        'client_name' => $this->pluginId,
      ],
      [
        'absolute' => TRUE,
        'language' => $language_none,
      ]
    )->toString(TRUE);
    $redirect_uri = str_ireplace( 'http://', 'https://', $redirect_uri->getGeneratedUrl() );
    $url_options = [
      'query' => [
        'client_id' => $this->configuration['client_id'],
        'response_type' => 'code',
        'scope' => $scope,
        'redirect_uri' => $redirect_uri,
        'state' => CILogonAuthStateToken::create(),
      ],
    ];

    $endpoints = $this->getEndpoints();
    // Clear _GET['destination'] because we need to override it.
    $this->requestStack->getCurrentRequest()->query->remove('destination');
    $authorization_endpoint = Url::fromUri($endpoints['authorization'], $url_options)->toString(TRUE);

    $response = new TrustedRedirectResponse($authorization_endpoint->getGeneratedUrl());
    // We can't cache the response, since this will prevent the state to be
    // added to the session. The kill switch will prevent the page getting
    // cached for anonymous users when page cache is active.
    $this->killSwitch->trigger();

    return $response;
  }

  /**
   * Implements CILogonAuthClientInterface::retrieveIDToken().
   *
   * @param string $authorization_code
   *   A authorization code string.
   *
   * @return array|bool
   *   A result array or false.
   */
  public function retrieveTokens($authorization_code) {
    // Exchange `code` for access token and ID token.
    $language_none = $this->languageManager
      ->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE);
    $redirect_uri = Url::fromRoute(
      'cilogon_auth.redirect_controller_redirect',
      [
        'client_name' => $this->pluginId,
      ],
      [
        'absolute' => TRUE,
        'language' => $language_none,
      ]
    )->toString();
    $endpoints = $this->getEndpoints();
    $redirect_uri = str_ireplace( 'http://', 'https://', $redirect_uri);
    $request_options = [
      'form_params' => [
        'code' => $authorization_code,
        'client_id' => $this->configuration['client_id'],
        'client_secret' => $this->configuration['client_secret'],
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code',
      ],
      'headers' => [
        'Accept' => 'application/json',
      ],
    ];

    /* @var \GuzzleHttp\ClientInterface $client */
    $client = $this->httpClient;
    try {
      $response = $client->post($endpoints['token'], $request_options);
      $response_data = json_decode((string) $response->getBody(), TRUE);

      // Expected result.
      $tokens = [
        'id_token' => isset($response_data['id_token']) ? $response_data['id_token'] : NULL,
        'access_token' => isset($response_data['access_token']) ? $response_data['access_token'] : NULL,
      ];
      if (array_key_exists('expires_in', $response_data)) {
        $tokens['expire'] = REQUEST_TIME + $response_data['expires_in'];
      }
      if (array_key_exists('refresh_token', $response_data)) {
        $tokens['refresh_token'] = $response_data['refresh_token'];
      }
      return $tokens;
    }
    catch (Exception $e) {
      $variables = [
        '@message' => 'Could not retrieve tokens',
        '@error_message' => $e->getMessage(),
      ];
      $this->loggerFactory->get('cilogon_auth_' . $this->pluginId)
        ->error('@message. Details: @error_message', $variables);
      return FALSE;
    }
  }

  /**
   * Implements CILogonAuthClientInterface::decodeIdToken().
   */
  public function decodeIdToken($id_token) {
    // @codingStandardsIgnoreStart
    list($headerb64, $claims64, $signatureb64) = explode('.', $id_token);
    // @codingStandardsIgnoreEnd
    $claims64 = str_replace(['-', '_'], ['+', '/'], $claims64);
    $claims64 = base64_decode($claims64);
    return json_decode($claims64, TRUE);
  }

  /**
   * Implements CILogonAuthClientInterface::retrieveUserInfo().
   *
   * @param string $access_token
   *   An access token string.
   *
   * @return array|bool
   *   A result array or false.
   */
  public function retrieveUserInfo($access_token) {
    $request_options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Accept' => 'application/json',
      ],
    ];
    $endpoints = $this->getEndpoints();

    $client = $this->httpClient;
    try {
      $response = $client->get($endpoints['userinfo'], $request_options);
      $response_data = (string) $response->getBody();

      return json_decode($response_data, TRUE);
    }
    catch (Exception $e) {
      $variables = [
        '@message' => 'Could not retrieve user profile information',
        '@error_message' => $e->getMessage(),
      ];
      $this->loggerFactory->get('cilogon_auth_' . $this->pluginId)
        ->error('@message. Details: @error_message', $variables);
      return FALSE;
    }
  }

}
