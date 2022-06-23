<?php

namespace Drupal\cilogon_auth\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\cilogon_auth\CILogonAuthSession;
use Drupal\cilogon_auth\CILogonAuthAuthmap;
use Drupal\cilogon_auth\CILogonAuthClaims;
use Drupal\cilogon_auth\Plugin\CILogonAuthClientManager;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Class CILogonAuthAccountsForm.
 *
 * @package Drupal\cilogon_auth\Form
 */
class CILogonAuthAccountsForm extends FormBase implements ContainerInjectionInterface {

    /**
     * Drupal\Core\Session\AccountProxy definition.
     *
     * @var \Drupal\Core\Session\AccountProxy
     */
    protected $currentUser;

    /**
     * The CILogon Auth session service.
     *
     * @var \Drupal\cilogon_auth\CILogonAuthSession
     */
    protected $session;

    /**
     * The CILogon Auth authmap service.
     *
     * @var \Drupal\cilogon_auth\CILogonAuthAuthmap
     */
    protected $authmap;

    /**
     * The CILogon Auth claims service.
     *
     * @var \Drupal\cilogon_auth\CILogonAuthClaims
     */
    protected $claims;

    /**
     * The CILogon Auth client plugin manager.
     *
     * @var \Drupal\cilogon_auth\Plugin\CILogonAuthClientManager
     */
    protected $pluginManager;

    /**
     * Drupal\Core\Config\ConfigFactory definition.
     *
     * @var \Drupal\Core\Config\ConfigFactory
     */
    protected $configFactory;

    /**
     * The Messenger service.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

    /**
     * The constructor.
     *
     * @param \Drupal\Core\Session\AccountProxy $current_user
     *   The current user account.
     * @param \Drupal\cilogon_auth\CILogonAuthSession $session
     *   The CILogon Auth service.
     * @param \Drupal\cilogon_auth\CILogonAuthAuthmap $authmap
     *   The authmap storage.
     * @param \Drupal\cilogon_auth\CILogonAuthClaims $claims
     *   The CILogon Auth claims.
     * @param \Drupal\cilogon_auth\Plugin\CILogonAuthClientManager $plugin_manager
     *   The CILogon Auth client manager.
     * @param \Drupal\Core\Config\ConfigFactory $config_factory
     *   The config factory.
     * @param \Drupal\Core\Messenger\MessengerInterface $messenger
     *   The messenger service.
     */
    public function __construct(
        AccountProxy $current_user,
        CILogonAuthSession $session,
        CILogonAuthAuthmap $authmap,
        CILogonAuthClaims $claims,
        CILogonAuthClientManager $plugin_manager,
        ConfigFactory $config_factory,
        MessengerInterface $messenger
    ) {

        $this->currentUser = $current_user;
        $this->session = $session;
        $this->authmap = $authmap;
        $this->claims = $claims;
        $this->pluginManager = $plugin_manager;
        $this->configFactory = $config_factory;
        $this->messenger = $messenger;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('current_user'),
            $container->get('cilogon_auth.session'),
            $container->get('cilogon_auth.authmap'),
            $container->get('cilogon_auth.claims'),
            $container->get('plugin.manager.cilogon_auth_client.processor'),
            $container->get('config.factory'),
            $container->get('messenger')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'cilogon_auth_accounts_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, AccountInterface $user = NULL) {
        $form_state->set('account', $user);

        $clients = $this->pluginManager->getDefinitions();

        $form['help'] = [
            '#prefix' => '<p class="description">',
            '#suffix' => '</p>',
        ];

        if (empty($clients)) {
            $form['help']['#markup'] = $this->t('No external account providers are available.');
            return $form;
        }
        elseif ($this->currentUser->id() == $user->id()) {
            $form['help']['#markup'] = $this->t('You can connect your account here.');
        }

        $connected_accounts = $this->authmap->getConnectedAccounts($user);

        foreach ($clients as $client) {
            $enabled = $this->configFactory
                ->getEditable('cilogon_auth.settings.' . $client['id'])
                ->get('enabled');
            if (!$enabled) {
                continue;
            }

            $form[$client['id']] = [
                '#type' => 'fieldset',
                '#title' => $this->t('Provider: @title', ['@title' => $client['label']]),
            ];
            $fieldset = &$form[$client['id']];
            $connected = isset($connected_accounts[$client['id']]);
            $fieldset['status'] = [
                '#type' => 'item',
                '#title' => $this->t('Status'),
                '#markup' => $this->t('Not connected'),
            ];

            $show_idp = $this->configFactory->get('cilogon_auth.settings')
                ->get('show_idp');

            if ($show_idp) {
                $fieldset['idp_name'] = [
                    '#type' => 'item',
                    '#title' => $this->t('Identity Provider Name'),
                    '#markup' => $this->t('Unavailable'),
                ];
            }
            if ($connected) {
                $subs = '';
                foreach ($connected_accounts['cilogon']['sub'] as $sub) {
                    if (!empty($sub)) {
                        $subs .= $sub . ', ';
                    }
                }
                $fieldset['status']['#markup'] = $this->t('Connected as %sub', [
                    '%sub' => substr($subs, 0, -2),
                ]);
                $fieldset['cilogon_auth_client_' . $client['id'] . '_disconnect'] = [
                    '#type' => 'submit',
                    '#value' => $this->t('Disconnect from @client_title', ['@client_title' => $client['label']]),
                    '#name' => 'disconnect__' . $client['id'],
                ];
                if ($show_idp) {
                    $idps = '';
                    foreach ($connected_accounts['cilogon']['idp'] as $idp) {
                        if (!empty($idp)) {
                            $idps .= $idp . ', ';
                        }
                    }
                    $fieldset['idp_name']['#markup'] = $this->t('%idp_name', [
                        '%idp_name' => substr($idps, 0, -2),
                    ]);
                }
            }
            else {
                $fieldset['status']['#markup'] = $this->t('Not connected');
                $fieldset['cilogon_auth_client_' . $client['id'] . '_connect'] = [
                    '#type' => 'submit',
                    '#value' => $this->t('Connect with @client_title', ['@client_title' => $client['label']]),
                    '#name' => 'connect__' . $client['id'],
                    '#access' => $this->currentUser->id() == $user->id(),
                ];
                if ($show_idp) {
                    $fieldset['idp_name']['#markup'] = $this->t('Not connected');
                }
            }
        }
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        list($op, $client_name) = explode('__', $form_state->getTriggeringElement()['#name'], 2);

        if ($op === 'disconnect') {
            $this->authmap->deleteAssociation($form_state->get('account')->id(), $client_name);
            $client = $this->pluginManager->getDefinition($client_name);
            $this->messenger->addMessage($this->t('Account successfully disconnected from @client.', ['@client' => $client['label']]));
            return;
        }

        if ($this->currentUser->id() !== $form_state->get('account')->id()) {
            $this->messenger->addError("You cannot connect another user's account.");
            return;
        }

        $this->session->saveDestination();

        $configuration = $this->config('cilogon_auth.settings.' . $client_name)
            ->get('settings');
        $client = $this->pluginManager->createInstance(
            $client_name,
            $configuration
        );
        $scopes = $this->claims->getScopes();
        $_SESSION['cilogon_auth_op'] = $op;
        $_SESSION['cilogon_auth_connect_uid'] = $this->currentUser->id();
        $response = $client->authorize($scopes, $form_state);
        $form_state->setResponse($response);
    }

    /**
     * Checks access for the CILogon-Auth accounts form.
     *
     * @param \Drupal\Core\Session\AccountInterface $user
     *   The user having accounts.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result.
     */
    public function access(AccountInterface $user) {
        if ($this->currentUser->hasPermission('administer users')) {
            return AccessResult::allowed();
        }

        if ($this->currentUser->id() && $this->currentUser->id() === $user->id() &&
            $this->currentUser->hasPermission('manage own cilogon auth account')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }
}
