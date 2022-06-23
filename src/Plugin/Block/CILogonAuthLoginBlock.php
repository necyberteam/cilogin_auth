<?php

namespace Drupal\cilogon_auth\Plugin\Block;

use Drupal\cilogon_auth\Plugin\CILogonAuthClientManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'CILogon Auth login' block.
 *
 * @Block(
 *  id = "cilogon_auth_login",
 *  admin_label = @Translation("CILogon Auth login"),
 * )
 */
class CILogonAuthLoginBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\cilogon_auth\Plugin\CILogonAuthClientManager definition.
   *
   * @var \Drupal\cilogon_auth\Plugin\CILogonAuthClientManager
   */
  protected $pluginManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\cilogon_auth\Plugin\CILogonAuthClientManager $plugin_manager
   *   The CILogon Auth client manager.
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder.
   */
  public function __construct(
      array $configuration,
      $plugin_id,
      $plugin_definition,
      CILogonAuthClientManager $plugin_manager,
      FormBuilder $form_builder
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->pluginManager = $plugin_manager;
    $this->formBuilder = $form_builder;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.cilogon_auth_client.processor'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if ($account->isAnonymous()) {
      return AccessResult::allowed()
        ->addCacheContexts([
          'user.roles:anonymous',
        ]);
    }
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->formBuilder->getForm('Drupal\cilogon_auth\Form\CILogonAuthLoginForm');
  }
}
