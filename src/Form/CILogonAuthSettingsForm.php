<?php

namespace Drupal\cilogon_auth\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\cilogon_auth\CILogonAuth;
use Drupal\cilogon_auth\CILogonAuthClaims;
use Drupal\cilogon_auth\Plugin\CILogonAuthClientManager;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CILogonAuthSettingsForm.
 *
 * @package Drupal\cilogon_auth\Form
 */
class CILogonAuthSettingsForm extends ConfigFormBase implements ContainerInjectionInterface {

    /**
     * The CILogon Auth service.
     *
     * @var \Drupal\cilogon_auth\CILogonAuth
     */
    protected $ciLogonAuth;

    /**
     * Drupal\cilogon_auth\Plugin\CILogonAuthClientManager definition.
     *
     * @var \Drupal\cilogon_auth\Plugin\CILogonAuthClientManager
     */
    protected $pluginManager;

    /**
     * The entity manager.
     *
     * @var \Drupal\Core\Entity\EntityManager
     */
    protected $entityFieldManager;

    /**
     * The CILogon Auth claims.
     *
     * @var \Drupal\cilogon_auth\CILogonAuthClaims
     */
    protected $claims;

    /**
     * CILogon Auth client plugins.
     *
     * @var \Drupal\cilogon_auth\Plugin\CILogonAuthClientInterface[]
     */
    protected static $clients;

    /**
     * The Messenger service.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

    /**
     * The constructor.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The config factory.
     * @param \Drupal\cilogon_auth\CILogonAuth $cilogon_auth
     *   The CILogon Auth service.
     * @param \Drupal\cilogon_auth\Plugin\CILogonAuthClientManager $plugin_manager
     *   The plugin manager.
     * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
     *   The entity field manager.
     * @param \Drupal\cilogon_auth\CILogonAuthClaims $claims
     *   The claims.
     */
    public function __construct(
        ConfigFactoryInterface $config_factory,
        CILogonAuth $cilogon_auth,
        CILogonAuthClientManager $plugin_manager,
        EntityFieldManagerInterface $entity_field_manager,
        CILogonAuthClaims $claims,
        MessengerInterface $messenger
    ) {
        parent::__construct($config_factory);
        $this->ciLogonAuth = $cilogon_auth;
        $this->pluginManager = $plugin_manager;
        $this->entityFieldManager = $entity_field_manager;
        $this->claims = $claims;
        $this->messenger = $messenger;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('config.factory'),
            $container->get('cilogon_auth.cilogon_auth'),
            $container->get('plugin.manager.cilogon_auth_client.processor'),
            $container->get('entity_field.manager'),
            $container->get('cilogon_auth.claims'),
            $container->get('messenger')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'cilogon_auth_admin_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return [
            'cilogon_auth.settings'
        ];
    }

    /**
    * {@inheritdoc}
    */
    public function buildForm(array $form, FormStateInterface $form_state) {
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
            $this->messenger->addWarning("This site is not using https.");
        }
//        else {
//            $this->messenger->addMessage("This site is using https.");
//        }

        $settings = $this->configFactory()->getEditable('cilogon_auth.settings');

        $form['#tree'] = TRUE;
        $form['clients_enabled'] = [
          '#title' => $this->t('Enable CILogon Auth'),
          '#type' => 'checkboxes',
        ];

        $clients = $this->getClients();
        $options = [];
        $clients_enabled = [];

        foreach ($clients as $client_plugin) {
          $plugin_definition = $client_plugin->getPluginDefinition();
          $plugin_id = $plugin_definition['id'];
          $plugin_label = $plugin_definition['label'];

          $options[$plugin_id] = $plugin_label;
          $enabled = $this->configFactory()
            ->getEditable('cilogon_auth.settings.' . $plugin_id)
            ->get('enabled');
          $clients_enabled[$plugin_id] = (bool) $enabled ? $plugin_id : 0;

          $element = 'clients_enabled[' . $plugin_id . ']';
          $form['clients'][$plugin_id] = [
            '#title' => $plugin_label,
            '#type' => 'fieldset',
            '#tree' => TRUE,
          ];
          $form['clients'][$plugin_id]['settings'] = [];
          $subform_state = SubformState::createForSubform($form['clients'][$plugin_id]['settings'], $form, $form_state);
          $form['clients'][$plugin_id]['settings'] += $client_plugin->buildConfigurationForm($form['clients'][$plugin_id]['settings'], $subform_state);
        }

        $form['clients_enabled']['#options'] = $options;
        $form['clients_enabled']['#default_value'] = $clients_enabled;

        $form['logon_button_text'] = [
            '#type' => 'textfield',
            '#title' => 'CILogon Button Label',
            '#default_value' => $settings->get('logon_button_text'),
            '#maxlength' => 256,
        ];

        $form['logon_block_prefix'] = [
            '#type' => 'textfield',
            '#title' => 'CILogon Block Prefix',
            '#description' => 'Adds description above CILogon button, (html markup is allowed)',
            '#default_value' => $settings->get('logon_block_prefix'),
            '#maxlength' => 256,
        ];

        $form['logon_block_suffix'] = [
            '#type' => 'textfield',
            '#title' => 'CILogon Block Suffix',
            '#description' => 'Adds description below CILogon button, (html markup is allowed)',
            '#default_value' => $settings->get('logon_block_suffix'),
            '#maxlength' => 256,
        ];

        $form['username_generation_scheme'] = [
            '#prefix' => '<strong>Username generation scheme</strong><div class="description">If you did not request email scope with CILogon registration, username will be generated by default or custom prefix method only.</div>',
            '#type' => 'radios',
            '#default_value' => $settings->get('username_generation_scheme'),
            '#options' => [
                'default' => 'default (e.g. cilogon_hashValue)',
                'email' => 'email (e.g. john@example.com), requires cilogon\'s email scope',
                'email prefix' => 'email prefix (e.g. john parsed from john@example.com), requires cilogon\'s email scope',
                'custom prefix' => 'custom prefix (e.g. user will generate user1, user2, user3)',
            ]
        ];

        $form['username_custom_prefix'] = [
            '#type' => 'textfield',
            '#default_value' => $settings->get('username_custom_prefix'),
            '#size' => 10,
            '#states' => [
                'visible' => [
                    ':input[name=username_generation_scheme]' => [
                        'value' => 'custom prefix'
                    ],
                ],
            ],
        ];

        $form['assign_role'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('<strong>Assign predefined role on first user login</strong>'),
            '#default_value' => $settings->get('assign_role'),
            '#suffix' => '</div>',
        ];

        $form['role'] = [
            '#prefix' => '<div id="auth_roles_dropdown">',
            '#type' => 'select',
            '#options' => array_filter(user_role_names(), function ($role) {
                return $role != 'Anonymous user';
            }),
            '#default_value' => $settings->get('role'),
            '#suffix' => '</div>',
        ];

        $form['override_registration_settings'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Override registration settings'),
            '#description' => $this->t('If enabled, a user will be registered even if registration is set to "Administrators only".'),
            '#default_value' => $settings->get('override_registration_settings'),
        ];

        $form['unblock_account'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Unblock account during registration'),
          '#description' => $this->t('If enabled, user account will register as unblocked even if registration is set to require admin approval.'),
          '#default_value' => $settings->get('unblock_account'),
          '#states' => [
              'visible' => [
                  ':input[name=override_registration_settings]' => [
                      'checked' => TRUE
                  ],
              ],
          ],
        ];

        $form['always_save_userinfo'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Save user claims on every login'),
          '#description' => $this->t('If disabled, user claims will only be saved when the account is first created.'),
          '#default_value' => $settings->get('always_save_userinfo'),
        ];

        $form['connect_existing_users'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Automatically connect existing users'),
          '#description' => $this->t('If disabled, authentication will fail for existing email addresses.'),
          '#default_value' => $settings->get('connect_existing_users'),
        ];

        $form['show_idp'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Show connected IDP of user'),
            '#description' => $this->t('If enabled, the user page will show the idp name that the user connected via cilogon; requires cilogon\'s org.cilogon.userinfo scope.'),
            '#default_value' => $settings->get('show_idp'),
        ];

        $admin_status = "admin-disabled";
        $ed = "disabled";
        $user_restriction_disabled = TRUE;
        if (!is_null($this->ciLogonAuth->getUserRestrictionManager()))
        {
            $user_restriction_disabled = FALSE;
            $admin_status = "admin-enabled";
            $ed = "enabled";
        }
        $form['enable_user_restriction'] = [
            '#type' => 'checkbox',
            '#title' => 'Enable user restrictions <span class="' . $admin_status . '">('. $ed .')</span>',
            '#description' => $this->t('If enabled, the user restriction rules will apply to CILogin Authentication.'),
            '#default_value' => $settings->get('enable_user_restriction'),
            '#disabled' => $user_restriction_disabled,
        ];

        $form['userinfo_mappings'] = [
          '#title' => $this->t('User claims mapping'),
          '#type' => 'fieldset',
        ];

        $form['reset'] = [
            '#type' => 'submit',
            '#value' => 'Restore default configuration',
            '#submit' => array('::reset_submit'),
        ];

        $properties = $this->entityFieldManager->getFieldDefinitions('user', 'user');
        $properties_skip = $this->ciLogonAuth->userPropertiesIgnore();
        $claims = $this->claims->getOptions();
        $mappings = $settings->get('userinfo_mappings');
        foreach ($properties as $property_name => $property) {
          if (isset($properties_skip[$property_name])) {
            continue;
          }
          // Always map the timezone.
          $default_value = 0;
          if ($property_name == 'timezone') {
            $default_value = 'zoneinfo';
          }

          $form['userinfo_mappings'][$property_name] = [
            '#type' => 'select',
            '#title' => $property->getLabel(),
            '#description' => $property->getDescription(),
            '#options' => (array) $claims,
            '#empty_value' => 0,
            '#empty_option' => $this->t('- No mapping -'),
            '#default_value' => isset($mappings[$property_name]) ? $mappings[$property_name] : $default_value,
          ];
        }

        return parent::buildForm($form, $form_state);
    }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Get clients' enabled status.
    $clients_enabled = $form_state->getValue('clients_enabled');
    // Get client plugins.
    $clients = $this->getClients();

    // Trigger validation for enabled clients.
    foreach ($clients_enabled as $plugin_id => $status) {
      // Whether the client is not enabled.
      if (!(bool) $status) {
        continue;
      }

      // Get subform and subform state.
      $subform = $form['clients'][$plugin_id]['settings'];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);

      // Let the plugin validate its form.
      $clients[$plugin_id]->validateConfigurationForm($subform, $subform_state);
    }

    if ($form_state->getValue('username_generation_scheme') == "custom prefix" && $form_state->getValue('username_custom_prefix') == "") {
        $form_state->setErrorByName("username_custom_prefix","Did you forget to set your custom prefix.");
    }
  }

  public function reset_submit(array &$form, FormStateInterface $form_state) {
      $config = $this->configFactory()->get('cilogon_auth.settings.default');

      $this->config('cilogon_auth.settings')
          ->set('always_save_userinfo', $config->get('always_save_userinfo'))
          ->set('connect_existing_users', $config->get('connect_existing_users'))
          ->set('override_registration_settings', $config->get('override_registration_settings'))
          ->set('userinfo_mappings', $config->get('userinfo_mappings'))
          ->set('unblock_account', $config->get('unblock_account'))
          ->set('username_generation_scheme', $config->get('username_generation_scheme'))
          ->set('username_custom_prefix', $config->get('username_custom_prefix'))
          ->set('show_idp', $config->get('show_idp'))
          ->set('enable_user_restriction', $config->get('enable_user_restriction'))
          ->set('logon_block_prefix', $config->get('logon_block_prefix'))
          ->set('logon_block_suffix', $config->get('logon_block_suffix'))
          ->set('logon_button_text', $config->get('logon_button_text'))
          ->save();

      $config = $this->configFactory()->get('cilogon_auth.settings.cilogon.default');
      $this->configFactory()
          ->getEditable('cilogon_auth.settings.cilogon')
          ->set('enabled', $config->get('enabled'))
          ->set('settings', $config->get('settings'))
          ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('cilogon_auth.settings')
        ->set('assign_role', $form_state->getValue('assign_role'))
        ->set('role', $form_state->getValue('role'))
        ->set('always_save_userinfo', $form_state->getValue('always_save_userinfo'))
        ->set('connect_existing_users', $form_state->getValue('connect_existing_users'))
        ->set('override_registration_settings', $form_state->getValue('override_registration_settings'))
        ->set('userinfo_mappings', $form_state->getValue('userinfo_mappings'))
        ->set('unblock_account', $form_state->getValue('unblock_account'))
        ->set('username_generation_scheme', $form_state->getValue('username_generation_scheme'))
        ->set('username_custom_prefix', $form_state->getValue('username_custom_prefix'))
        ->set('show_idp', $form_state->getValue('show_idp'))
        ->set('enable_user_restriction', $form_state->getValue('enable_user_restriction'))
        ->set('logon_block_prefix', $form_state->getValue('logon_block_prefix'))
        ->set('logon_block_suffix', $form_state->getValue('logon_block_suffix'))
        ->set('logon_button_text', $form_state->getValue('logon_button_text'))
        ->save();

    // Get clients' enabled status.
    $clients_enabled = $form_state->getValue('clients_enabled');
    // Get client plugins.
    $clients = $this->getClients();

    // Save client settings.
    foreach ($clients_enabled as $plugin_id => $status) {
      $this->configFactory()
        ->getEditable('cilogon_auth.settings.' . $plugin_id)
        ->set('enabled', $status)
        ->save();

      // Whether the client is not enabled.
      if (!(bool) $status) {
        continue;
      }

      // Get subform and subform state.
      $subform = $form['clients'][$plugin_id]['settings'];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);

      // Let the plugin preprocess submitted values.
      $clients[$plugin_id]->submitConfigurationForm($subform, $subform_state);

      // Save plugin settings.
      $this->configFactory()
        ->getEditable('cilogon_auth.settings.' . $plugin_id)
        ->set('settings', $subform_state->getValues())
        ->save();
    }
  }

    /**
     * Return array of CILogon Auth client plugins.
     *
     * As the list of clients is used several times during form submission,
     * we are using this little helper method and a static collection of
     * initialized client plugins for this form.
     *
     * @return \Drupal\cilogon_auth\Plugin\CILogonAuthClientInterface[]
     *   Associative array of CILogon Auth client plugins with client IDs
     *   as keys and the corresponding initialized client plugins as values.
     */
    protected function getClients() {
        if (!isset(self::$clients)) {
            $clients = [];

            $definitions = $this->pluginManager->getDefinitions();

            ksort($definitions);
            foreach ($definitions as $client_name => $client_plugin) {
                $configuration = $this->configFactory()
                    ->getEditable('cilogon_auth.settings.' . $client_name)
                    ->get('settings');

                /* @var \Drupal\cilogon_auth\Plugin\CILogonAuthClientInterface $client */
                $client = $this->pluginManager->createInstance(
                    $client_name,
                    $configuration ?: []
                );

                $clients[$client_name] = $client;
            }

            self::$clients = $clients;
        }

        return self::$clients;
    }
}
