<?php

namespace Drupal\cilogon_auth\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a CILogon Auth client item annotation object.
 *
 * @see \Drupal\cilogon_auth\Plugin\CILogonAuthClientManager
 * @see plugin_api
 *
 * @Annotation
 */
class CILogonAuthClient extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
