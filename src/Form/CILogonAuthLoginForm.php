<?php

namespace Drupal\cilogon_auth\Form;

use Drupal\cilogon_auth\CILogonAuthClaims;
use Drupal\cilogon_auth\CILogonAuthSession;
use Drupal\cilogon_auth\Plugin\CILogonAuthClientManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CILogonAuthLoginForm.
 *
 * @package Drupal\cilogon_auth\Form
 */
class CILogonAuthLoginForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The CILogon Auth session service.
   *
   * @var \Drupal\cilogon_auth\CILogonAuthSession
   */
  protected $session;

  /**
   * Drupal\cilogon_auth\Plugin\CILogonAuthClientManager definition.
   *
   * @var \Drupal\cilogon_auth\Plugin\CILogonAuthClientManager
   */
  protected $pluginManager;

  /**
   * The CILogon Auth claims.
   *
   * @var \Drupal\cilogon_auth\CILogonAuthClaims
   */
  protected $claims;

  /**
   * The constructor.
   *
   * @param \Drupal\cilogon_auth\CILogonAuthSession $session
   *   The CILogon Auth session service.
   * @param \Drupal\cilogon_auth\Plugin\CILogonAuthClientManager $plugin_manager
   *   The plugin manager.
   * @param \Drupal\cilogon_auth\CILogonAuthClaims $claims
   *   The CILogon Auth claims.
   */
  public function __construct(
      CILogonAuthSession $session,
      CILogonAuthClientManager $plugin_manager,
      CILogonAuthClaims $claims
  ) {
    $this->session = $session;
    $this->pluginManager = $plugin_manager;
    $this->claims = $claims;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cilogon_auth.session'),
      $container->get('plugin.manager.cilogon_auth_client.processor'),
      $container->get('cilogon_auth.claims')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cilogon_auth_login_form';
  }

  /**
   * {@inheritdoc}
   */
    public function buildForm(array $form, FormStateInterface $form_state) {
        if ($this->config('cilogon_auth.settings.cilogon')->get('enabled')) {
            $form['cilogon_auth_client_cilogon_login'] = [
                '#type' => 'submit',
                '#value' => $this->config('cilogon_auth.settings')->get('logon_button_text'),
                '#name' => 'cilogon',
                '#attributes' => [
                    'id' => 'cilogon-auth-login-button',
                ],
                '#prefix' => '<div id="cilogon-auth-login-group"><div id="cilogon-auth-login-prefix">' . $this->config('cilogon_auth.settings')->get('logon_block_prefix') . '</div>',
                '#suffix' => '<div id="cilogon-auth-login-suffix">' . $this->config('cilogon_auth.settings')->get('logon_block_suffix') . '</div></div>',
            ];
        }
        return $form;
    }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->session->saveDestination();
    $client_name = $form_state->getTriggeringElement()['#name'];

    $configuration = $this->config('cilogon_auth.settings.' . $client_name)
      ->get('settings');
    $client = $this->pluginManager->createInstance(
      $client_name,
      $configuration
    );
    $scopes = $this->claims->getScopes();
    $_SESSION['cilogon_auth_op'] = 'login';
    $response = $client->authorize($scopes, $form_state);
    $form_state->setResponse($response);
  }

}
