<?php

namespace Step\Acceptance;

use Pages\DomainListPage;
use Pages\ConfigurationPage;
use Pages\SpampanelPage;
use Facebook\WebDriver\WebDriver;
use Codeception\Lib\Interfaces\Web;
use Pages\ProfessionalSpamFilterPage;
use Pages\PleskWindowsClientPage;

class CommonSteps extends \WebGuy
{
    protected $domainName;
    protected $resellerUsername;
    protected $resellerPassword;
    protected $domain;
    protected $customerUsername;
    protected $customerPassword;

    private static $accounts = array();

    public function login($username = "", $password = "", $isCustomer = false)
    {
        if (empty($username)) {
            $username = getenv($this->getEnvParameter('username'));
        }
        if (empty($password)) {
            $password = getenv($this->getEnvParameter('password'));
        }

        $I = $this;
        $I->amGoingTo("\n\n --- Login as '{$username}' --- \n");
        $I->amOnPage('/');
        $I->waitForElement("//input[@id='loginSection-username']");
        $I->fillField("//input[@id='loginSection-username']", $username);
        $I->fillField("//input[@id='loginSection-password']", $password);
        $I->click('Log in');
        if ($isCustomer) {
            $I->wait(2);
            $canSeeElement = $I->canSeeElement("//button[contains(text(), 'OK, back to Plesk')]");
            if ($canSeeElement) {
                $I->click("//button[contains(text(), 'OK, back to Plesk')]");
            }
            $I->see("Websites & Domains");
            $I->seeElement("//span[contains(.,'Professional Spam Filter')]");
        } else {
            $I->switchToTopFrame();
            $I->waitForElement("//img[contains(@name,'logo')]");
            $I->see('Log out', "//a[contains(.,'Log out')]");
        }
    }

    public function setConfigurationOptions(array $options)
    {
        $options = array_merge($this->getDefaultConfigurationOptions(), $options);

        foreach ($options as $option => $check) {
            if ($check) {
                $this->checkOption($option);
            } else {
                $this->uncheckOption($option);
            }
        }

        $this->click(ConfigurationPage::SAVE_SETTINGS_BTN);
        $this->see('The settings have been saved.');
    }

    private function getDefaultConfigurationOptions()
    {
        return array(
            ConfigurationPage::ENABLE_SSL_FOR_API_OPT => false,
            ConfigurationPage::ENABLE_AUTOMATIC_UPDATES_OPT => false,
            ConfigurationPage::AUTOMATICALLY_ADD_DOMAINS_OPT => true,
            ConfigurationPage::AUTOMATICALLY_DELETE_DOMAINS_OPT => true,
            ConfigurationPage::AUTOMATICALLY_CHANGE_MX_OPT => true,
            ConfigurationPage::CONFIGURE_EMAIL_ADDRESS_OPT => true,
            ConfigurationPage::PROCESS_ADDON_OPT => true,
            ConfigurationPage::ADD_ADDON_OPT => false,
            ConfigurationPage::USE_EXISTING_MX_OPT => true,
            ConfigurationPage::DO_NOT_PROTECT_REMOTE_DOMAINS_OPT => false,
            ConfigurationPage::REDIRECT_BACK_TO_OPT => false,
            ConfigurationPage::ADD_DOMAIN_DURING_LOGIN_OPT => true,
            ConfigurationPage::FORCE_CHANGE_MX_ROUTE_OPT => false,
            ConfigurationPage::USE_IP_AS_DESTINATION_OPT => false,
        );
    }

    public function logout()
    {
        $I = $this;
        $I->amOnPage('/login_up.php3');
    }

    public function loginOnSpampanel($domain)
    {
        $I = $this;
        $href = $I->grabAttributeFrom('//a[contains(text(), "Login")]', 'href');
        $I->amOnUrl($href);
        $I->waitForText("Welcome to the $domain control panel", 60);
        $I->see("Logged in as: $domain");
        $I->see("Domain User");
    }

    public function logoutFromSpampanel()
    {
        $this->waitForElementVisible(SpampanelPage::LOGOUT_LINK);
        $this->click(SpampanelPage::LOGOUT_LINK);
        $this->waitForElementVisible(SpampanelPage::LOGOUT_CONFIRM_LINK);
        $this->click(SpampanelPage::LOGOUT_CONFIRM_LINK);
    }

    public function goToPage($page, $title)
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Go to {$title} page --- \n");
        $I->switchToLeftFrame();
        $I->waitForElement("//td[contains(.,'Links to Additional Services')]");
        $I->click("//a[contains(.,'Professional Spam Filter')]");
        $I->switchToWorkFrame();
        $I->waitForText('Professional Spam Filter');
        $I->see('Professional Spam Filter');
        $I->waitForText('Configuration');
        $I->click($page);
        $I->waitForText($title);
    }

    public function checkDomainIsPresentInFilter($domain)
    {
        $this->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $this->searchDomainList($domain);
        $this->checkProtectionStatusIs(DomainListPage::STATUS_DOMAIN_IS_PRESENT_IN_THE_FILTER);
    }

    public function checkDomainIsNotPresentInFilter($domain)
    {
        $this->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
        $this->searchDomainList($domain);
        $this->checkProtectionStatusIs(DomainListPage::STATUS_DOMAIN_IS_NOT_PRESENT_IN_THE_FILTER);
    }

    public function checkProtectionStatusIs($status)
    {
        $this->click('Check status');
        $this->waitForText($status, 60);
    }

    public function searchDomainList($domain)
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Search for {$domain} domain --- \n");
        $I->fillField(DomainListPage::SEARCH_FIELD, $domain);
        $I->click(DomainListPage::SEARCH_BTN);
        $I->waitForText('Page 1 of 1. Total Items: 1');
        $I->see($domain, DomainListPage::DOMAIN_TABLE);
    }

    public function checkDomainList($domainName, $isRoot = false)
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Check Domain list is present --- \n");
        if ($isRoot) {
            $I->goToPage(ProfessionalSpamFilterPage::DOMAIN_LIST_BTN, DomainListPage::TITLE);
            $I->see($domainName, DomainListPage::DOMAIN_TABLE);
        }
        else {
            $I->switchToLeftFrame();
            $I->click("//a[contains(.,'Professional Spam Filter')]");
            $I->switchToWorkFrame();
            $I->amGoingTo("Check domin '{$domainName}' is present on the list");
            $I->see($domainName, DomainListPage::DOMAIN_TABLE);
        }
    }

    public function shareIp($resellerId = null)
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Enable a shared IP --- \n");
        $I->switchToLeftFrame();
        $I->click("//a[contains(.,'Tools & Settings')]");
        $I->switchToWorkFrame();
        $I->click("//a[@href='/admin/ip-address/list/']");
        $I->click("//a[@href='/admin/ip-address/edit/id/1']");
        $I->checkOption("//input[@value='shared']");
        $I->click("//button[@name='send']");
        $I->waitForElement("//div[@class='msg-content']");
        $I->see("The properties of the IP address");
        if ($resellerId) {
            $I->click("//a[@href='/plesk/server/ip-address@1/client@']");
            $I->waitForText("Resellers who use Shared IP address");
            $I->click("//span[@id='spanid-add-client']");
            $I->waitForText("Add IP address to reseller's pool");
            $I->checkOption("//input[@id='del_{$resellerId}']");
            $I->click("//button[@id='buttonid-ok']");
            $I->waitForText("Resellers who use Shared IP address");
            $I->seeElement("//table/tbody/tr/td//a[contains(@href, '/admin/reseller/overview/id/{$resellerId}')]");
        }
    }

    public function checkPsfPresentForRoot($brandname = 'Professional Spam Filter')
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Check PSF is present at root level --- \n");
        $I->switchToLeftFrame();
        $I->waitForElement("//td[contains(.,'Links to Additional Services')]");
        $I->click("//a[contains(.,'{$brandname}')]");
        $I->switchToWorkFrame();
        $I->waitForText($brandname);
        $I->see($brandname);
    }

    public function checkPsfPresentForReseller($brandname = 'Professional Spam Filter')
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Check PSF is present at reseller level --- \n");
        $I->switchToLeftFrame();
        $I->waitForElement("//td[contains(.,'Links to Additional Services')]");
        $I->switchToWorkFrame();
        $I->waitForText($brandname);
        $I->see($brandname);
        $I->see('Home');
        $I->click(ProfessionalSpamFilterPage::PROSPAMFILTER_BTN);
        $I->waitForElement("//h3[contains(.,'List Domains')]");
        $I->see("There are no domains on this server.", "//div[@class='alert alert-info']");
    }

    public function checkPsfPresentForCustomer($brandname = 'Professional Spam Filter')
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Check PSF is present at customer level --- \n");
        $I->click("//span[contains(.,'{$brandname}')]");
        $I->see($brandname);
        $I->switchToIFrame("pageIframe");
        $I->waitForElement("//h3[contains(.,'List Domains')]");
        $I->see($this->domain, "//tbody/tr[1]");
        $I->dontSeeElement("//tbody/tr[2]");
    }

    public function createReseller()
    {
        $this->resellerUsername = uniqid("r");
        $this->resellerPassword = uniqid("xX");
        $I = $this;
        $I->amGoingTo("\n\n --- Create a new reseller '{$this->resellerUsername}' --- \n");
        $I->switchToLeftFrame();
        $I->click("//a[contains(.,'Resellers')]");
        $I->switchToWorkFrame();
        $I->click("//span[contains(.,'Add New Reseller')]");
        $I->fillField("//input[@id='contactInfoSection-contactInfo-contactName']", $this->resellerUsername);
        $I->fillField("//input[@id='contactInfoSection-contactInfo-email']", "test@example.com");
        $I->fillField("//input[@id='contactInfoSection-contactInfo-phone']", "123456789");
        $I->fillField("//input[@id='accessToPanelSection-loginInfo-userName']", $this->resellerUsername);
        $I->fillField("//input[@id='accessToPanelSection-loginInfo-password']", $this->resellerPassword);
        $I->fillField("//input[@id='accessToPanelSection-loginInfo-passwordConfirmation']", $this->resellerPassword);
        $I->click("//button[@name='send']");
        $I->waitForElement("//div[@class='msg-box msg-info']", 200);
        $I->see("Reseller {$this->resellerUsername} was created.", "//div[@class='msg-box msg-info']");
        $href = $I->grabAttributeFrom("//table[@id='resellers-list-table']/tbody/tr/td//a[text()='{$this->resellerUsername}']", 'href');
        $resellerId = array_pop(explode('/', $href));
        return [$this->resellerUsername, $this->resellerPassword, $resellerId];

    }

    public function createCustomer()
    {
        $this->customerUsername = uniqid("c");
        $this->customerPassword = uniqid("xX");
        $this->domain           = uniqid("domain") . ".example.com";
        $I = $this;
        $I->amGoingTo("\n\n --- Create a new customer '{$this->customerUsername}' --- \n");
        $I->switchToLeftFrame();
        $I->click("//a[contains(.,'Customers')]");
        $I->switchToWorkFrame();
        $I->click("//a[@id='buttonAddNewCustomer']");
        $I->fillField("//input[@id='contactInfoSection-contactInfo-contactName']", $this->customerUsername);
        $I->fillField("//input[@id='contactInfoSection-contactInfo-email']", "test@example.com");
        $I->fillField("//input[@id='contactInfoSection-contactInfo-phone']", "123456789");
        $I->fillField("//input[@id='accessToPanelSection-loginInfo-userName']", $this->customerUsername);
        $I->fillField("//input[@id='accessToPanelSection-loginInfo-password']", $this->customerPassword);
        $I->fillField("//input[@id='accessToPanelSection-loginInfo-passwordConfirmation']", $this->customerPassword);
        $I->fillField("//input[@id='subscription-domainInfo-domainName']", $this->domain);
        $I->fillField("//input[@id='subscription-domainInfo-userName']", $this->customerUsername);
        $I->fillField("//input[@id='subscription-domainInfo-password']", $this->customerPassword);
        $I->fillField("//input[@id='subscription-domainInfo-passwordConfirmation']", $this->customerPassword);
        $I->selectOption("//select[@id='subscription-subscriptionInfo-servicePlan']", "Default Domain");

        $I->click("//button[@name='send']");
        $I->waitForElement("//div[@class='msg-content']", 200);
        $I->see("Customer {$this->customerUsername} was created.", "//div[@class='msg-box msg-info']");
        return [$this->customerUsername, $this->customerPassword, $this->domain];
    }

    public function changeCustomerPlan($customerUsername)
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Change the subscription plan for '$this->customerUsername' --- \n");
        $I->switchToLeftFrame();
        $I->click("//a[contains(.,'Customers')]");
        $I->switchToWorkFrame();
        $I->click("//a[contains(.,'$this->customerUsername')]");
        $I->waitForElementVisible("//a[contains(.,'Subscription')]");
        $I->checkOption("//input[contains(@name,'listGlobalCheckbox')]");
        $I->click("//span[contains(.,'Change Plan')]");
        $I->waitForElementVisible("//select[contains(@name,'planSection[servicePlan]')]");
        $I->selectOption("//select[contains(@id,'planSection-servicePlan')]", "Unlimited");
        $I->click("//button[contains(.,'OK')]");
        $I->waitForElementVisible("//a[contains(.,'Subscription')]");
    }

    public function addNewSubscription(array $params = array())
    {
        if (empty($params['domain'])) {
            $params['domain'] = $this->generateRandomDomainName();
        }
        if (empty($params['username'])) {
            $params['username'] = $this->generateRandomUserName();
        }
        if (empty($params['password'])) {
            $params['password'] = uniqid('xX!');
        }
        $I = $this;
        $I->amGoingTo("\n\n --- Add a new subscription for '{$params['domain']}'--- \n");
        $I->switchToLeftFrame();
        $I->click("//a[contains(.,'Subscriptions')]");
        $I->switchToWorkFrame();
        $I->click("//span[contains(.,'Add New Subscription')]");
        $I->waitForElement("//span[contains(.,'Subscriptions')]");
        $I->fillField("//input[@id='subscription-domainInfo-domainName']", $params['domain']);
        $I->fillField("//input[@id='subscription-domainInfo-userName']", $params['username']);
        $I->fillField("//input[@id='subscription-domainInfo-password']", $params['password']);
        $I->fillField("//input[@id='subscription-domainInfo-passwordConfirmation']", $params['password']);
        $I->click("//button[@name='send']");
        $I->waitForElement("//div[@class='msg-content']", 200);
        $I->see("Subscription {$params['domain']} was created.");

        $account = array(
            'domain' => $params['domain'],
            'username' => $params['username'],
            'password' => $params['password']
        );

        self::$accounts[] = $account;

        return $account;
    }

    public function removeCreatedSubscriptions()
    {
        foreach(self::$accounts as $account) {
            $this->removeSubscription($account['domain']);
        }
    }

    public function removeSubscription($domainName)
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Remove subscription for '{$domainName}'--- \n");
        $I->switchToLeftFrame();
        $I->click("//a[contains(.,'Subscriptions')]");
        $I->switchToWorkFrame();
        $I->fillField('#subscriptions-list-search-text-domainName', $domainName);
        $I->click('#subscriptions-list-operations > div.quick-search-box > div > form > em');
        $I->wait(1);
        $I->waitForElement("//div[@class='b-indent status-ok']/a[contains(text(), '{$domainName}')]");
        $value = $I->grabAttributeFrom("//div[@class='b-indent status-ok']/a[contains(text(), '{$domainName}')]", 'href');
        $subscriptionNo = array_pop(explode('/', $value));
        $I->checkOption("//input[@value='{$subscriptionNo}']");
        $I->click("//span[contains(.,'Remove')]");
        $I->waitForElement("//div[@class='confirmation-msg mw-delete']", 200);
        $I->waitForText("Yes");
        $I->click("Yes");
        $I->waitForElement("//div[@class='msg-content']", 200);
        $I->see("Selected subscriptions were removed.");
    }

    public function removeAllDomains()
    {
        $I = $this;
        $I->switchToLeftFrame();
        $I->click(PleskWindowsClientPage::CLIENT_SUBSCRIPTIONS);
        $I->switchToWorkFrame();

        if (!$I->getElementsCount("//td[contains(@class,'select')]")) {
            return;
        }

        $I->waitForElementVisible(PleskWindowsClientPage::CLIENT_ADD_NEW_SUBSCRIPTION);
        $I->click(PleskWindowsClientPage::CLIENT_ALL_ENTRIES_BUTTON);
        $I->waitForElementVisible(PleskWindowsClientPage::CLIENT_SELECT_ALL_SUBSCRIPTIONS);
        $I->wait(3);
        $I->click(PleskWindowsClientPage::CLIENT_SELECT_ALL_SUBSCRIPTIONS);
        $I->click(PleskWindowsClientPage::CLIENT_REMOVE_SUBSCRIPTION_BUTTON);
        $I->waitForElementVisible("//button[contains(.,'Yes')]", 30);
        $I->click("//button[contains(.,'Yes')]");
        $I->waitForText("Information: Selected subscriptions were removed.", 1000);
    }

    public function openSubscription($domainName)
    {
        $I = $this;
        $I->amGoingTo("\n\n --- Open subscription for '{$domainName}'--- \n");
        $I->searchSubscription($domainName);
        $I->click(" //a[@class='s-btn sb-login']");
        $I->waitForText("Websites & Domains");
        $I->click("//span[@class='caption-control-wrap']");
        $I->wait(2);
        $I->click("//span[contains(.,'DNS Settings')]");
        $I->waitForElementVisible("//table[@class='list']");
    }

    public function searchSubscription($domainName)
    {
        $I = $this;

        $I->amGoingTo("\n\n --- Search domain from subscriptions for '{$domainName}'--- \n");
        $I->switchToLeftFrame();
        $I->click("//a[contains(.,'Subscriptions')]");
        $I->switchToWorkFrame();
        $I->fillField("//input[@id='subscriptions-list-search-text-domainName']", $domainName);
        $I->click("//*[@class='search-field']//em");
        $I->waitForText("1 items total");
        $I->see($domainName, "#subscriptions-list-table");
    }

    public function openDomain($domainName)
    {
        $I = $this;

        $I->amGoingTo("\n\n --- Open domain from subscriptions for '{$domainName}'--- \n");
        $I->searchSubscription($domainName);
        $I->click("//a[@class='s-btn sb-login']");
        $I->waitForElement("//span[contains(.,'$domainName')]");
    }

    public function generateRandomDomainName()
    {
        $domain = uniqid("domain") . ".example.com";
        $this->comment("I generated random domain: $domain");

        return $domain;
    }

    public function generateRandomUserName()
    {
        $username = uniqid("u");
        $this->comment("I generated random username: $username");

        return $username;
    }

    public function getEnvHostname()
    {
        $url = getenv($this->getEnvParameter('url'));
        $parsed = parse_url($url);

        if (empty($parsed['host'])) {
            throw new \Exception("Couldnt parse url");
        }

        return $parsed['host'];
    }

    public function switchToLeftFrame()
    {
        $I = $this;
        $I->switchToWindow();
        $I->waitForElement('#leftFrame');
        $I->switchToIFrame('leftFrame');
    }

    public function switchToWorkFrame()
    {
        $I = $this;
        $I->switchToWindow();
        $I->waitForElement('#workFrame');
        $I->switchToIFrame('workFrame');
    }

    public function switchToTopFrame()
    {
        $I = $this;
        $I->switchToWindow();
        $I->waitForElement('#topFrame');
        $I->switchToIFrame('topFrame');
    }

    public function goToConfigurationPageAndSetOptions(array $options)
    {
        $this->goToPage(ProfessionalSpamFilterPage::CONFIGURATION_BTN, ConfigurationPage::TITLE);
        $this->setConfigurationOptions($options);
    }

    public function addAliasAsClient($domain, $alias = null)
    {
        if (! $alias) {
            $alias = 'alias' . $domain;
        }
        $I = $this;
        $I->click("//a[@id='buttonAddDomainAlias']");
        $I->waitForText('Add a Domain Alias');
        $I->fillField("//input[@id='name']", $alias);
        $I->click("//button[@name='send']");
        $I->waitForText("The domain alias $alias was created.", 30);

        return $alias;
    }

    public function removeAliasAsClient($alias)
    {
        $I = $this;
        $I->click($alias);
        $I->click("Remove Domain Alias");
        $I->waitForText("Removing this website will also delete all related files, directories, and web applications from the server.");
        $I->click("Yes");
        $I->waitForText("The alias was removed", 30);
    }

    public function loginAsClient($customerUsername, $customerPassword)
    {
        $this->login($customerUsername, $customerPassword, true);
    }

    public function loginAsRoot()
    {
        $this->login();
    }

    public function addAddonDomainAsClient($domain, $addonDomainName = null)
    {
        if (! $addonDomainName) {
            $addonDomainName = 'addon' . $domain;
        }
        
        $I = $this;
        $I->click('Add New Domain');
        $I->waitForText('Adding New Domain Name');
        $I->fillField("//input[@id='domainName-name']", $addonDomainName);
        $I->click("//button[@name='send']");
        $I->waitForText("The domain $addonDomainName was successfully created.", 1000);
        
        return $addonDomainName;
    }
}
