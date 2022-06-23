<?php

namespace Drupal\cilogon_auth\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Traversable;

/**
 * Provides the CILogon Auth client plugin manager.
 */
class CILogonAuthClientManager extends DefaultPluginManager {

  /**
   * Constructor for CILogonAuthClientManager objects.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(
      Traversable $namespaces,
      CacheBackendInterface $cache_backend,
      ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/CILogonAuthClient',
      $namespaces,
      $module_handler,
      'Drupal\cilogon_auth\Plugin\CILogonAuthClientInterface',
      'Drupal\cilogon_auth\Annotation\CILogonAuthClient'
    );

    $this->alterInfo('cilogon_auth_client_info');
    $this->setCacheBackend(
      $cache_backend,
      'cilogon_auth_client_plugins'
    );
  }

}
