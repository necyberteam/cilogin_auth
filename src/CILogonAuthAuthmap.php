<?php

namespace Drupal\cilogon_auth;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The CILogon Auth authmap service.
 *
 * @package Drupal\cilogon_auth
 */
class CILogonAuthAuthmap {

    const AUTHMAP_TABLE = "cilogon_auth_authmap";

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The User entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a CILogonAuthAuthmap service object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager) {
    $this->connection = $connection;
    $this->userStorage = $entity_type_manager->getStorage('user');
  }

  /**
   * Create a local to remote account association.
   *
   * @param object $account
   *   A user account object.
   * @param string $client_name
   *   The client name.
   * @param string $sub
   *   The remote subject identifier.
   */
  public function createAssociation($account, $client_name, $sub, $idp_name) {
    $fields = [
      'uid' => $account->id(),
      'client_name' => $client_name,
      'sub' => $sub,
      'idp_name' => $idp_name
    ];
    $this->connection->insert(SELF::AUTHMAP_TABLE)
      ->fields($fields)
      ->execute();
  }

  /**
   * Deletes a user's authmap entries.
   *
   * @param int $uid
   *   A user id.
   * @param string $client_name
   *   A client name.
   */
  public function deleteAssociation($uid, $client_name = '') {
    $query = $this->connection->delete(SELF::AUTHMAP_TABLE)
      ->condition('uid', $uid);
    if (!empty($client_name)) {
      $query->condition('client_name', $client_name, '=');
    }
    $query->execute();
  }

  /**
   * Loads a user based on a sub-id and a login provider.
   *
   * @param string $sub
   *   The remote subject identifier.
   * @param string $client_name
   *   The client name.
   *
   * @return object|bool
   *   A user account object or FALSE
   */
  public function userLoadBySub($sub, $client_name) {
    $result = $this->connection->select(SELF::AUTHMAP_TABLE, 'a')
      ->fields('a', ['uid'])
      ->condition('client_name', $client_name, '=')
      ->condition('sub', $sub, '=')
      ->execute();
    foreach ($result as $record) {
      /* @var \Drupal\user\Entity\User $account */
      $account = $this->userStorage->load($record->uid);
      if (is_object($account)) {
        return $account;
      }
    }
    return FALSE;
  }

  /**
   * Get a list of external CIlogon Auth accounts connected to this Drupal account.
   *
   * @param object $account
   *   A Drupal user entity.
   *
   * @return array
   *   An array of 'sub' properties keyed by the client name.
   */
  public function getConnectedAccounts($account) {
    $result = $this->connection->select(SELF::AUTHMAP_TABLE, 'a')
      ->fields('a', ['sub', 'idp_name'])
      ->condition('uid', $account->id())
      ->execute();
    $idps = [];
    $subs = [];
    foreach ($result as $record) {
        array_push($subs, $record->sub);
        array_push($idps, $record->idp_name);
    }
    $subs = array_unique($subs);
    $idps = array_unique($idps);

    $authmaps = [
        'cilogon' => [
            'sub' => $subs,
            'idp' => $idps,
        ]
    ];

    if(empty($subs)) {
        return [];
    }

    return $authmaps;
  }

}
