<?php

namespace Drupal\cilogon_auth;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The CILogon Auth claims service.
 *
 * @package Drupal\cilogon_auth
 */
class CILogonAuthClaims implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * Drupal\Core\Config\ConfigFactory definition.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The CILogon Auth claims.
   *
   * @var array
   */
  protected static $claims;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   */
  public function __construct(
    ConfigFactory $config_factory,
    ModuleHandler $module_handler
  ) {

    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('config.factory')
    );
  }

  /**
   * Returns CILogon Auth claims.
   *
   * Allows them to be extended via an alter hook.
   *
   * @see http://openid.net/specs/openid-connect-core-1_0.html#StandardClaims
   * @see http://openid.net/specs/openid-connect-core-1_0.html#ScopeClaims
   *
   * @return array
   *   List of claims.
   */
  public function getClaims() {
    if (!isset(self::$claims)) {
      $claims = $this->getDefaultClaims();
      $this->moduleHandler->alter('cilogon_auth_claims', $claims);
      self::$claims = $claims;
    }
    return self::$claims;
  }

  /**
   * Returns CILogon Auth standard Claims as a Form API options array.
   *
   * @return array
   *   List of claims as options.
   */
  public function getOptions() {
    $options = [];
    foreach ($this->getClaims() as $claim_name => $claim) {
      $options[ucfirst($claim['scope'])][$claim_name] = $claim['title'];
    }
    return $options;
  }

  /**
   * Returns scopes that have to be requested based on the configured claims.
   *
   * @see http://openid.net/specs/openid-connect-core-1_0.html#ScopeClaims
   *
   * @return string
   *   Space delimited case sensitive list of ASCII scope values.
   */
  public function getScopes() {
    $claims = $this->configFactory
      ->getEditable('cilogon_auth.settings')
      ->get('userinfo_mappings');

    $scopes = ['openid', 'email', 'profile', 'org.cilogon.userinfo'];
    $claims_info = $this->getClaims();
    foreach ($claims as $claim) {
      if (isset($claims_info[$claim]) &&
          !isset($scopes[$claims_info[$claim]['scope']]) &&
          $claim != 'email') {

        $scopes[$claims_info[$claim]['scope']] = $claims_info[$claim]['scope'];
      }
    }
    return implode(' ', $scopes);
  }

  /**
   * Return default claims supported by the CILogon Auth module.
   *
   * @return array
   *   Default claims supported by the CILogon Auth module.
   */
  protected function getDefaultClaims() {
    return [
      'name' => [
        'scope' => 'profile',
        'title' => $this->t('Name'),
        'type' => 'string',
        'description' => $this->t('Full name'),
      ],
      'given_name' => [
        'scope' => 'profile',
        'title' => $this->t('Given name'),
        'type' => 'string',
        'description' => $this->t('Given name(s) or first name(s)'),
      ],
      'family_name' => [
        'scope' => 'profile',
        'title' => $this->t('Family name'),
        'type' => 'string',
        'description' => $this->t('Surname(s) or last name(s)'),
      ],
      'middle_name' => [
        'scope' => 'profile',
        'title' => $this->t('Middle name'),
        'type' => 'string',
        'description' => $this->t('Middle name(s)'),
      ],
      'nickname' => [
        'scope' => 'profile',
        'title' => $this->t('Nickname'),
        'type' => 'string',
        'description' => $this->t('Casual name'),
      ],
      'preferred_username' => [
        'scope' => 'profile',
        'title' => $this->t('Preferred username'),
        'type' => 'string',
        'description' => $this->t('Shorthand name by which the End-User wishes to be referred to'),
      ],
      'profile' => [
        'scope' => 'profile',
        'title' => $this->t('Profile'),
        'type' => 'string',
        'description' => $this->t('Profile page URL'),
      ],
      'picture' => [
        'scope' => 'profile',
        'title' => $this->t('Picture'),
        'type' => 'string',
        'description' => $this->t('Profile picture URL'),
      ],
      'website' => [
        'scope' => 'profile',
        'title' => $this->t('Website'),
        'type' => 'string',
        'description' => $this->t('Web page or blog URL'),
      ],
      'email' => [
        'scope' => 'email',
        'title' => $this->t('Email'),
        'type' => 'string',
        'description' => $this->t('Preferred e-mail address'),
      ],
      'email_verified' => [
        'scope' => 'email',
        'title' => $this->t('Email verified'),
        'type' => 'boolean',
        'description' => $this->t('True if the e-mail address has been verified; otherwise false'),
      ],
      'gender' => [
        'scope' => 'profile',
        'title' => $this->t('Gender'),
        'type' => 'string',
        'description' => $this->t('Gender'),
      ],
      'birthdate' => [
        'scope' => 'profile',
        'title' => $this->t('Birthdate'),
        'type' => 'string',
        'description' => $this->t('Birthday'),
      ],
      'zoneinfo' => [
        'scope' => 'profile',
        'title' => $this->t('Zoneinfo'),
        'type' => 'string',
        'description' => $this->t('Time zone'),
      ],
      'locale' => [
        'scope' => 'profile',
        'title' => $this->t('Locale'),
        'type' => 'string',
        'description' => $this->t('Locale'),
      ],
      'phone_number' => [
        'scope' => 'phone',
        'title' => $this->t('Phone number'),
        'type' => 'string',
        'description' => $this->t('Preferred telephone number'),
      ],
      'phone_number_verified' => [
        'scope' => 'phone',
        'title' => $this->t('Phone number verified'),
        'type' => 'boolean',
        'description' => $this->t('True if the phone number has been verified; otherwise false'),
      ],
      'address' => [
        'scope' => 'address',
        'title' => $this->t('Address'),
        'type' => 'json',
        'description' => $this->t('Preferred postal address'),
      ],
      'updated_at' => [
        'scope' => 'profile',
        'title' => $this->t('Updated at'),
        'type' => 'number',
        'description' => $this->t('Time the information was last updated'),
      ],
    ];
  }

}
