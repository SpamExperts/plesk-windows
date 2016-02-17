<?php
/*
*************************************************************************
*                                                                       *
* ProSpamFilter                                                         *
* Bridge between Webhosting panels & SpamExperts filtering				*
*                                                                       *
* Copyright (c) 2010-2011 SpamExperts B.V. All Rights Reserved,         *
*                                                                       *
*************************************************************************
*                                                                       *
* Email: support@spamexperts.com                                        *
* Website: htttp://www.spamexperts.com                                  *
*                                                                       *
*************************************************************************
*                                                                       *
* This software is furnished under a license and may be used and copied *
* only in accordance with the  terms of such license and with the       *
* inclusion of the above copyright notice. No title to and ownership    *
* of the software is  hereby  transferred.                              *
*                                                                       *
* You may not reverse engineer, decompile or disassemble this software  *
* product or software product license.                                  *
*                                                                       *
* SpamExperts may terminate this license if you don't comply with any   *
* of the terms and conditions set forth in our end user                 *
* license agreement (EULA). In such event, licensee agrees to return    *
* licensor  or destroy  all copies of software upon termination of the  *
* license.                                                              *
*                                                                       *
* Please see the EULA file for the full End User License Agreement.     *
*                                                                       *
*************************************************************************
* @category  SpamExperts
* @package   ProSpamFilter
* @author    $Author$
* @copyright Copyright (c) 2011, SpamExperts B.V., All rights Reserved. (http://www.spamexperts.com)
* @license   Closed Source
* @version   3.0
* @link      https://my.spamexperts.com/kb/34/Addons
* @since     2.5
*/
/** Zend_Controller_Action */
class AdminController extends Zend_Controller_Action
{
    /** @var SpamFilter_Acl */
    protected $_acl;

    /** @var SpamFilter_Controller_Action_Helper_FlashMessenger */
    var $_flashMessenger;

    /** @var Zend_Translate_Adapter_Gettext */
    var $t;

    /** @var SpamFilter_PanelSupport_Plesk */
    protected $_panel;

    /** @var SpamFilter_Brand */
    private $_branding;

    protected $_hasAPIAccess;

    public function init()
    {
        try {
            // Enable the flash messenger helper so we can send messages.
            $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
        } catch (Zend_Session_Exception $e) {
            if (!$this->_helper->hasHelper('FlashMessenger')) {
                if (!Zend_Session::isStarted() && Zend_Session::sessionExists()) {
                    Zend_Controller_Action_HelperBroker::addHelper(
                        new SpamFilter_Controller_Action_Helper_FlashMessenger()
                    );
                    $this->_flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
                } else {
                    Zend_Session::setOptions(array("strict" => false));
                    $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
                    Zend_Session::setOptions(array("strict" => true));
                }
            }
        }
        $this->_branding   = new SpamFilter_Brand();
    }

    public function preDispatch()
    {
        // Setup ACL
        $this->_acl = new SpamFilter_Acl();

        $username = SpamFilter_Core::getUsername();

        // Retrieve usertype using the Panel driver
        $this->_panel = new SpamFilter_PanelSupport();
        $userlevel    = $this->_panel->getUserLevel();

        // Feed the ACL system the current username
        $this->_acl->setRole($username, $userlevel);

        // Get the translator
        $this->t = Zend_Registry::get('translator');

        /**
         * Get changed brandname (in case of it was set)
         * @see https://trac.spamexperts.com/ticket/16804
         */
        $brandname  = $this->_branding->getBrandUsed();

        if (!$brandname) {
            $brandname = 'Professional Spam Filter';
        }

        $this->view->headTitle()->set($brandname);
        $this->view->headTitle()->setSeparator(' | ');
        $this->view->headStyle()->appendStyle(file_get_contents(BASE_PATH . DS . 'public' . DS . 'css' . DS . 'bootstrap.min.css'));
        $this->view->headStyle()->appendStyle(
            file_get_contents(BASE_PATH . '/public' . DS . 'css' . DS . 'bootstrap-responsive.min.css')
        );
        $this->view->headStyle()->appendStyle(file_get_contents(BASE_PATH . DS . 'public' . DS . 'css' . DS . 'addon.css'));
        $this->view->headScript()->appendScript(file_get_contents(BASE_PATH . DS . 'public' . DS . 'js' . DS . 'jquery.min.js'));
        $this->view->headScript()->appendScript(file_get_contents(BASE_PATH . DS . 'public' . DS . 'js' . DS . 'bootstrap.min.js'));

        $this->view->acl = $this->_acl;
        $this->view->t = $this->t;
        $this->view->hasAPIAccess = $this->_hasAPIAccess = $this->_branding->hasAPIAccess();
    }

    public function settingsAction()
    {
        $this->view->headTitle()->append("Settings");

        if (!$this->_acl->isAllowed('settings_admin')) {
            $this->_flashMessenger->addMessage(
                array('message' => $this->t->_('You do not have permission to this part of the system.'), 'status' => 'error')
            );

            return false;
        }

        $settings = new SpamFilter_Configuration(CFG_PATH . DS . 'settings.conf'); // <-- General settings

        /** @see https://trac.spamexperts.com/ticket/19688 */
        if (!$settings->GetConfig()->provision_dns && $settings->GetConfig()->bulk_force_change) {
            $this->_flashMessenger->addMessage(
                array('message' => sprintf(
                    "The option's '%s' status has no effect if '%s' is disabled.",
                    'Force changing route & MX records, even if the domain exists.',
                    'Automatically change the MX records for domains'
                ), 'status' => 'error')
            );
        }

        $form = new SpamFilter_Forms_AdminConfig;
        if ($this->_request->isPost()) {
            $values = $_POST;
            if ($form->isValid($_POST)) {

                /**
                 * Do not overwrite value of last_bulkprotect
                 * @see https://trac.spamexperts.com/ticket/14699
                 */
                $config = Zend_Registry::get('general_config');
                if (!empty($config->last_bulkprotect)) {
                    $values['last_bulkprotect'] = $config->last_bulkprotect;
                }

                // When updating the settings keep the configured updatetier, if available
                if (!empty($config->updatetier)) {
                    $values['updatetier'] = $config->updatetier;
                }

                // We don't need it stored in the config file
                unset($values['submit']);

                if ($settings->WriteConfig($values)) {
                    //clear domains list cache if "Add aliases and sub-domains as an alias instead of a normal domain." option was changed
                    if ( $config->add_extra_alias != $_POST['add_extra_alias'] ||
                         $config->handle_extra_domains != $_POST['handle_extra_domains']
                    ) {
                        $cacheKey = SpamFilter_Core::getDomainsCacheId();
                        SpamFilter_Panel_Cache::clear('user_domains_' . $cacheKey);
                        SpamFilter_Panel_Cache::clear(strtolower('user_domains_' . md5(SpamFilter_Core::getUsername())));
                        $domains = $this->_panel->getDomains( array('username' => SpamFilter_Core::getUsername(), 'level' => 'owner' ) );
                        SpamFilter_Panel_Cache::set($cacheKey, $domains);
                    }

                    $this->_flashMessenger->addMessage(
                        array('message' => $this->t->_('The settings have been saved.'), 'status' => 'success')
                    );

                    $this->redirectTo('admin', 'settings');
                } else {
                    $this->_flashMessenger->addMessage(
                        array('message' => $this->t->_('The configuration could not be saved.'), 'status' => 'error')
                    );
                }
            } else {
                $form->populate($values);
                $this->_flashMessenger->addMessage(
                    array('message' => $this->t->_('One or more settings are not correctly set.'), 'status' => 'error')
                );
            }
        }

        $this->view->form = $form;
    }

    public function brandingAction()
    {
        if (!$this->_hasAPIAccess) { return;}

        $this->view->headTitle()->append("Branding");

        if (!$this->_acl->isAllowed('settings_branding')) {
            $this->_flashMessenger->addMessage(
                array('message' => $this->t->_('You do not have permission to this part of the system.'), 'status' => 'error')
            );
            $this->_helper->viewRenderer->setNoRender(); // Do not render the page
            return false;
        }

        $this->view->hasWhitelabel = $this->_branding->hasWhitelabel();

        if ($this->view->hasWhitelabel) // Save on resources, only continue if we actually have something to do here.
        {
            // Initialize Branding config handler.
            $form = new SpamFilter_Forms_BrandingConfig($this->_branding->getBrandUsed());
            if ($this->_request->isPost()) {
                $flashMessenger = $this->_helper->FlashMessenger;
                $values         = $this->_request->getPost(); // Technically we should use *this*
                if ($form->isValid($_POST)) {
                    $uploadedData = $form->getValues();
                    
                    if (empty($uploadedData['brandicon'])) {
                        $values['brandicon'] = trim($this->_branding->getBrandIcon());
                        $this->_flashMessenger->addMessage(
                            array('message' => $this->t->_('No new icon uploaded, using the current one.'), 'status' => 'info')
                        );
                    } else {
                        $values['brandicon'] = trim(
                            base64_encode(file_get_contents(TMP_PATH . DS . $uploadedData['brandicon']))
                        );
                    }
                    if ($this->_branding->updateBranding($values)) {
                        $this->_flashMessenger->addMessage(
                            array('message' => $this->t->_('The branding settings have been saved.'), 'status' => 'success')
                        );
                        $this->_flashMessenger->addMessage(
                            array('message' => $this->t->_("Brandname is set to ") ."'{$values['brandname']}'.", 'status' => 'success')
                        );
                        $icon_size = 0;

                        ($icon_size > 0) ? $this->_flashMessenger->addMessage(
                            array('message' =>
                                  'Brand icon <img src="psf' . DS . 'brandicon.png?' . (filemtime('psf' . DS . 'brandicon.png')) . '">',
                                  'status'  => 'success')
                        ) : '';

                        // Setup data for frontend
                        $this->view->brandname = $values['brandname'];
                        $this->view->brandicon = $values['brandicon'];
                    } else {
                        $this->_flashMessenger->addMessage(
                            array('message' => $this->t->_('The branding settings could not be saved.'), 'status' => 'error')
                        );
                    }
                } else {
                    $form->populate($values);
                    $this->_flashMessenger->addMessage(
                        array('message' => $this->t->_('One or more settings are not correctly set.'), 'status' => 'error')
                    );
                }
            } else {
                // Setup data for frontend
                $this->view->brandname = $this->_branding->getBrandUsed();
                $this->view->brandicon = $this->_branding->getBrandIcon();
            }
            $this->view->form = $form;
        }
    }

    public function listresellersAction()
    {
        $this->view->headTitle()->append("List Resellers");

        if (!$this->_acl->isAllowed('list_resellers')) {
            $this->_flashMessenger->addMessage(
                array('message' => $this->t->_('You do not have permission to this part of the system.'), 'status' => 'error')
            );
            $this->_helper->viewRenderer->setNoRender(); // Do not render the page
            return false;
        }
        $resellers = $this->_panel->getResellers();

        if ((!isset($resellers)) || (empty($resellers)) || (count($resellers) == 0)) {
            $this->_flashMessenger->addMessage(
                array('message' => $this->t->_('Unable to retrieve resellers.'), 'status' => 'error')
            );

            return false;
        }

        $paginator = new Zend_Paginator(new Zend_Paginator_Adapter_Array($resellers));
        $paginator->setItemCountPerPage(25)
            ->setCurrentPageNumber($this->_getParam('page', 1));
        $this->view->paginator = $paginator;
    }

    public function updateAction()
    {
        $this->view->headTitle()->append("Update");

        if (!$this->_acl->isAllowed('update')) {
            $this->_flashMessenger->addMessage(
                array('message' => $this->t->_('You do not have permission to this part of the system.'), 'status' => 'error')
            );
            $this->_helper->viewRenderer->setNoRender(); // Do not render the page

            return false;
        }
    }

    public function loginresellerAction()
    {
        if (!$this->_acl->isAllowed('loginas_reseller')) {
            $this->_flashMessenger->addMessage(
                array('message' => $this->t->_('You do not have permission to this part of the system.'), 'status' => 'error')
            );

            return false;
        }

        // disable view
        $this->_flashMessenger->addMessage(
            array('message' => $this->t->_('This feature is not yet implemented.'), 'status' => 'info')
        );
        $this->_helper->viewRenderer->setNoRender(); // Do not render the page
    }

    public function supportAction()
    {
        $this->view->headTitle()->append("Support");
        if (!$this->_acl->isAllowed('support')) {
            $this->_flashMessenger->addMessage(
                array('message' => $this->t->_('You do not have permission to this part of the system.'), 'status' => 'error')
            );
            $this->_helper->viewRenderer->setNoRender(); // Do not render the page
            return false;
        }

        $diagform = new SpamFilter_Forms_Diagnostics();
        if ($this->_request->isPost()) {
            if ($diagform->isValid($this->_request->getPost())) {
                #$this->_flashMessenger->addMessage( array('message' => 'Running diagnostics..', 'status' => 'success') );
                $diag    = new SpamFilter_Diagnostics();
                $results = $diag->run();

                $this->view->results = $results;
            }
        }
        $this->view->diagnostics = $diagform;

        $paneltype = ucfirst(strtolower(SpamFilter_Core::getPanelType()));

        $this->view->type_controlpanel    = $paneltype;
        $this->view->version_controlpanel = $this->_panel->getVersion();
        $this->view->php_version          = phpversion();
        $this->view->addon_version        = SpamFilter_Version::getUsedVersion();
    }

    public function migrateAction()
    {
        if (!$this->_hasAPIAccess) { return;}
        // Change the user to a different one and migrate things.
        $this->view->headTitle()->append("Migration");

        if (!$this->_acl->isAllowed('migration')) {
            $this->_flashMessenger->addMessage(
                array('message' => $this->t->_('You do not have permission to this part of the system.'), 'status' => 'error')
            );
            $this->_helper->viewRenderer->setNoRender(); // Do not render the page
            return false;
        }

        $settings = new SpamFilter_Configuration(CFG_PATH . DS . 'settings.conf'); // <-- General settings

        // Check if configured
        $config                   = Zend_Registry::get('general_config');
        $this->view->isConfigured = (!empty($config->apiuser)) ? true : false;
        if (!$this->view->isConfigured) {
            return false;
        }

        $form = new SpamFilter_Forms_Migrate();
        if ($this->_request->isPost()) {
            $values = $_POST;
            if ($form->isValid($_POST)) // Verify new credentials
            {
                Zend_Registry::get('logger')->debug(
                    "[Migrate] Going to migrate all domains to '{$_POST['new_user']}'.. "
                );
                if ($result = $this->_panel->migrateDomainsTo(
                    array(
                         'username' => $_POST['new_user'],
                         'password' => $_POST['new_password']
                    )
                )
                ) {
                    foreach ($result['messages'] as $message) {
                        $this->_flashMessenger->addMessage(
                            array('message' => $message['message'], 'status' => $message['status'])
                        );
                    }
                    if ($result['is_success']) {
                        // Change the settings to update the credentials to the newly provided.
                        $settings->updateOptionsArray(array('apiuser' => filter_input(INPUT_POST, 'new_user', FILTER_SANITIZE_EMAIL), 'apipass' => filter_input(INPUT_POST, 'new_password')));
                        $this->_flashMessenger->addMessage(
                            array('message' => $this->t->_('Credentials have been saved.'), 'status' => 'success')
                        );
                    }
                } else {
                    $this->_flashMessenger->addMessage(
                        array('message' => $this->t->_('Unable to migrate to new user.'), 'status' => 'error')
                    );
                }
            } else {
                $form->populate($values);
                $this->_flashMessenger->addMessage(
                    array('message' => $this->t->_('One or more settings are not correctly set.'), 'status' => 'error')
                );
            }
        }
        $this->view->form = $form;
    }

    private function redirectTo($controller, $action)
    {
        $urlbase = ((false !== stristr($_SERVER['SCRIPT_NAME'], "index.raw")) ? '' : $_SERVER['SCRIPT_NAME']);
        $this->_redirect($urlbase . '?q=' . $this->view->url(array(
            'controller' => $controller,
            'action' => $action,
        )));
    }
}