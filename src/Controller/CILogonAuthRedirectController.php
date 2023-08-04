<?php

namespace Drupal\cilogon_auth\Controller;

use Drupal\cilogon_auth\CILogonAuth;
use Drupal\cilogon_auth\CILogonAuthStateToken;
use Drupal\cilogon_auth\Plugin\CILogonAuthClientManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * Class CILogonRedirectController.
 *
 * @package Drupal\cilogon_auth\Controller
 */
class CILogonAuthRedirectController extends ControllerBase implements AccessInterface {

    /**
     * Drupal\cilogon_auth\Plugin\CILogonAuthClientManager definition.
     *
     * @var \Drupal\cilogon_auth\Plugin\CILogonAuthClientManager
     */
    protected $pluginManager;

    /**
     * The request stack used to access request globals.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;

    /**
     * The logger factory.
     *
     * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
     */
    protected $loggerFactory;

    /**
     * Drupal\Core\Session\AccountProxy definition.
     *
     * @var \Drupal\Core\Session\AccountProxy
     */
    protected $currentUser;

    /**
     * The CILogon Auth service.
     *
     * @var \Drupal\cilogon_auth\CILogonAuth
     */
    protected $ciLogonAuth;

    /**
     * The Messenger service.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        CILogonAuthClientManager $plugin_manager,
        CILogonAuth $cilogon_auth,
        RequestStack $request_stack,
        LoggerChannelFactoryInterface $logger_factory,
        AccountInterface $current_user,
        MessengerInterface $messenger
    ) {
        $this->pluginManager = $plugin_manager;
        $this->ciLogonAuth = $cilogon_auth;
        $this->requestStack = $request_stack;
        $this->loggerFactory = $logger_factory;
        $this->currentUser = $current_user;
        $this->messenger = $messenger;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('plugin.manager.cilogon_auth_client.processor'),
            $container->get('cilogon_auth.cilogon_auth'),
            $container->get('request_stack'),
            $container->get('logger.factory'),
            $container->get('current_user'),
            $container->get('messenger')
        );
    }

    /**
     * Access callback: Redirect page.
     *
     * @return bool
     *   Whether the state token matches the previously created one that is stored
     *   in the session.
     */
    public function access() {
        // Confirm anti-forgery state token. This round-trip verification helps to
        // ensure that the user, not a malicious script, is making the request.
        $query = $this->requestStack->getCurrentRequest()->query;
        $state_token = $query->get('state');
        if ($state_token && CILogonAuthStateToken::confirm($state_token)) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }

    /**
     * Redirect.
     *
     * @param string $client_name
     *   The client name.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *   The redirect response starting the authentication request.
     */
    public function authenticate($client_name) {
        $query = $this->requestStack->getCurrentRequest()->query;

        // Delete the state token, since it's already been confirmed.
        unset($_SESSION['cilogon_auth_state']);

        // Get parameters from the session, and then clean up.
        $parameters = [
            'destination' => 'user',
            'op' => 'login',
            'connect_uid' => NULL,
        ];
        foreach ($parameters as $key => $default) {
            if (isset($_SESSION['cilogon_auth_' . $key])) {
                $parameters[$key] = $_SESSION['cilogon_auth_' . $key];
                unset($_SESSION['cilogon_auth_' . $key]);
            }
        }
        $destination = $parameters['destination'];

        $configuration = $this->config('cilogon_auth.settings.' . $client_name)
            ->get('settings');
        $client = $this->pluginManager->createInstance(
            $client_name,
            $configuration
        );
        if (!$query->get('error') && (!$client || !$query->get('code'))) {
            // In case we don't have an error, but the client could not be loaded or
            // there is no state token specified, the URI is probably being visited
            // outside of the login flow.
            throw new NotFoundHttpException();
        }

        $provider_param = ['@provider' => $client->getPluginDefinition()['label']];

        if ($query->get('error')) {
            if (in_array($query->get('error'), [
                'interaction_required',
                'login_required',
                'account_selection_required',
                'consent_required',
            ])) {
                // If we have an one of the above errors, that means the user hasn't
                // granted the authorization for the claims.
                $this->messenger->addWarning($this->t('Logging in with @provider has been canceled.', $provider_param));
            }
            else {
                // Any other error should be logged. E.g. invalid scope.
                $variables = [
                    '@error' => $query->get('error'),
                    '@details' => $query->get('error_description') ? $query->get('error_description') : $this->t('Unknown error.'),
                ];
                $message = 'Authorization failed: @error. Details: @details';
                $this->loggerFactory->get('cilogon_auth_' . $client_name)->error($message, $variables);
                $this->messenger->addError($this->t('Could not authenticate with @provider.', $provider_param));
            }
        }
        else {
            // Process the login or connect operations.
            $tokens = $client->retrieveTokens($query->get('code'));
            if ($tokens) {
                if ($parameters['op'] === 'login') {
                    $success = $this->ciLogonAuth->completeAuthorization($client, $tokens, $destination);

                    if (!$success) {
                        // Check Drupal user register settings before saving.
                        $register = $this->config('user.settings')->get('register');
                        // Respect possible override from CILogono-Auth settings.
                        $register_override = $this->config('cilogon_auth.settings')
                            ->get('override_registration_settings');
                        if ($register === UserInterface::REGISTER_ADMINISTRATORS_ONLY && $register_override) {
                            $register = UserInterface::REGISTER_VISITORS;
                        }

                        switch ($register) {
                            case UserInterface::REGISTER_ADMINISTRATORS_ONLY:
                            case UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL:

                            case UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL:
                                // Skip creating an error message, as completeAuthorization
                                // already added according messages.
                                break;

                            default:
                                $this->messenger->addError($this->t('Logging in with @provider could not be completed due to an error.', $provider_param));
                                break;
                        }
                    }
                }
                elseif ($parameters['op'] === 'connect' && $parameters['connect_uid'] === $this->currentUser->id()) {
                    $success = $this->ciLogonAuth->connectCurrentUser($client, $tokens);
                    if ($success) {
                        $this->messenger->addMessage($this->t('Account successfully connected with @provider.', $provider_param));
                    }
                    else {
                        $this->messenger->addError($this->t('Connecting with @provider could not be completed due to an error.', $provider_param));
                    }
                }
            }
        }

        // It's possible to set 'options' in the redirect destination.
        if (is_array($destination)) {
            $query = !empty($destination[1]['query']) ? '?' . $destination[1]['query'] : '';
            $redirect = Url::fromUri('internal:/' . ltrim($destination[0], '/') . $query)->toString();
        }
        else {
            $redirect = Url::fromUri('internal:/' . ltrim($destination, '/'))->toString();
        }
        return \Drupal::service('request_stack')->getCurrentRequest()->query->set('destination', $redirect);
    }
}
