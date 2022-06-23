<?php

namespace Drupal\cilogon_auth\Plugin\CILogonAuthClient;

use Drupal\cilogon_auth\Plugin\CILogonAuthClientBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * CILogon Auth client.
 *
 * Used primarily to login to Drupal sites powered by oauth2_server or PHP
 * sites powered by oauth2-server-php.
 *
 * @CILogonAuthClient(
 *   id = "cilogon",
 *   label = @Translation("CILogon")
 * )
 */
class CILogonAuthCilogonClient extends CILogonAuthClientBase {

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration() {
        return parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpoints() {
        return [
            'authorization' => 'https://cilogon.org/authorize',
            'token' => 'https://cilogon.org/oauth2/token',
            'userinfo' => 'https://cilogon.org/oauth2/userinfo',
        ];
    }

}
