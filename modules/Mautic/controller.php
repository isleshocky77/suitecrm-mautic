<?php

use Mautic\Auth\ApiAuth;
use Mautic\MauticApi;

class MauticController extends SugarController
{
    const MAUTIC_SETTINGS_CATEGORY = 'mautic';

    public function action_Status()
    {
        /** @var Administration $admin */
        $admin = BeanFactory::getBean('Administration');
        $admin->retrieveSettings(self::MAUTIC_SETTINGS_CATEGORY);

        $baseUrl = isset($admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_base_url']) ? $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_base_url'] : '';
        $clientId = isset($admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_client_id']) ? $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_client_id'] : '';
        $oauthAccessTokenDataJson = isset($admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_oauth_access_token']) ? html_entity_decode($admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_oauth_access_token']) : '';
        $oauthAccessTokenData = json_decode($oauthAccessTokenDataJson, true);

        $accessToken = isset($oauthAccessTokenData['access_token']) ? substr($oauthAccessTokenData['access_token'], 0, 10) . '...' : '';
        $refreshToken = isset($oauthAccessTokenData['refresh_token']) ? substr($oauthAccessTokenData['refresh_token'], 0, 10) . '...' : '';
        $expires = isset($oauthAccessTokenData['access_token']) ? $oauthAccessTokenData['expires'] : '';

        $module = $this->module;
        $action = 'OauthSetup';
        $oauthSetupUrl = "index.php?module=" . $module . "&action=" . $action;
        echo <<<HTML
<html>
    <body>
        <h4>API Information</h4>
        <dl>
            <dt>Base Url</dt>
            <dd>{$baseUrl}</dd>
            <dt>Client Id</dt>
            <dd>{$clientId}</dd>
        </dl>
        
        <h4>Connection Information</h4>
        <dl>                      
            <dt>Access Token</dt>
            <dd>{$accessToken}</dd>
            <dt>Expires</dt>
            <dd>{$expires}</dd>
            <dt>Refresh Token</dt>
            <dd>{$refreshToken}</dd>
        </dl>

        <a href="{$oauthSetupUrl}">Setup OAuth</a>
    </body>
</html>
HTML;
        exit;
    }

    public function action_OauthSetup($errors = array())
    {
        /** @var Administration $admin */
        $admin = BeanFactory::getBean('Administration');
        $admin->retrieveSettings(self::MAUTIC_SETTINGS_CATEGORY);

        $topMessage = '';

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['base_url']) && strlen($_POST['base_url'])
            && isset($_POST['client_id']) && strlen($_POST['client_id'])
            && isset($_POST['client_secret']) && strlen($_POST['client_secret'])
        ) {
            $admin->saveSetting(self::MAUTIC_SETTINGS_CATEGORY, 'base_url', $_POST['base_url']);
            $admin->saveSetting(self::MAUTIC_SETTINGS_CATEGORY, 'client_id', $_POST['client_id']);
            $admin->saveSetting(self::MAUTIC_SETTINGS_CATEGORY, 'client_secret', $_POST['client_secret']);

            $topMessage = 'Saved Successfully';
        }

        $module = $this->module;
        $action = $this->action;
        $formUrl = "index.php?module=" . $module . "&action=" . $action;

        $baseUrl = isset($_POST['base_url']) ? $_POST['base_url'] : (
        isset($admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_base_url'])
            ? $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_base_url']
            : ''
        );
        $clientId = isset($_POST['client_id']) ? $_POST['client_id'] : (
        isset($admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_client_id'])
            ? $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_client_id']
            : ''
        );
        $clientSecret = isset($_POST['client_secret']) ? $_POST['client_secret'] : (
        isset($admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_client_secret'])
            ? $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_client_secret']
            : ''
        );


        $authorize = '';
        if (strlen($baseUrl) && strlen($clientId) && strlen($clientSecret)) {
            $module = $this->module;
            $action = 'OauthAuthorize';
            $authorizeUrl = "index.php?module=" . $module . "&action=" . $action;
            $authorize = <<<AUTHORIZE
            <p><a href="{$authorizeUrl}">Click here to authorize your credentials</a>
AUTHORIZE;
        }


        echo <<<FORM
<html xmlns="http://www.w3.org/1999/html">
    <body>
        <p>Enter your Mautic Installation Information Below:</p>
        <p>{$topMessage}</p>
        <form method="post" action="{$formUrl}">
            <label for="base_url">Base Url:</label>
            <input type="text" name="base_url" value="{$baseUrl}">
            <br>
            <label for="client_id">Client Id:</label>
            <input type="text" name="client_id" value="{$clientId}">
            <br>
            <label for="client_secret">Client Secret:</label>
            <input type="text" name="client_secret" value="{$clientSecret}">
            <br>
            <input type="submit" value="Save">
        </form>
        
        {$authorize}
    </body>
</html>
FORM;
        exit;
    }

    public function action_OauthAuthorize($errors = array())
    {
        /** @var Administration $admin */
        $admin = BeanFactory::getBean('Administration');
        $admin->retrieveSettings(self::MAUTIC_SETTINGS_CATEGORY);

        $configurator = new Configurator();

        $settings = array(
            'baseUrl'          => $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_base_url'],
            'version'          => 'OAuth2',
            'clientKey'        => $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_client_id'],
            'clientSecret'     => $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_client_secret'],
            'callback'         => $configurator->config['site_url'] .'/index.php?module=Mautic&action=OauthAuthorize'
        );

        /*
        // If you already have the access token, et al, pass them in as well to prevent the need for reauthorization
        $settings['accessToken']        = $accessToken;
        $settings['accessTokenSecret']  = $accessTokenSecret; //for OAuth1.0a
        $settings['accessTokenExpires'] = $accessTokenExpires; //UNIX timestamp
        $settings['refreshToken']       = $refreshToken;
        */

        $initAuth = new ApiAuth();
        $auth = $initAuth->newAuth($settings);

        try {
            if ($auth->validateAccessToken()) {

                if ($auth->accessTokenUpdated()) {
                    $accessTokenData = $auth->getAccessTokenData();

                    $admin->saveSetting(self::MAUTIC_SETTINGS_CATEGORY, 'oauth_access_token', json_encode($accessTokenData));

                    echo <<<HTML
<html>
    <body>
        <p>Successfully Authorized the OAuth Credentials and received an AccessToken.</p>
        <p><a href="index.php?module=Mautic&action=status">Click Here to go back to Status</a></p>
    </body>
</html>
HTML;
                }
            }
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
        exit;
    }

    public function action_Contacts()
    {
        list($auth, $baseUrl) = $this->setupApi();

        $api = new MauticApi();

        $contactApi = $api->newApi('contacts', $auth, $baseUrl);

        $response = $contactApi->getList(null, null, null, null, null, true, true);

        if (isset($response['error'])) {
            LoggerManager::getLogger()->fatal('Mautic Response Error: ' . $response['error']['code'] . ": " . $response['error']['message']);
            echo $response['error']['code'] . ": " . $response['error']['message'];
            exit;
        }

        $contacts = $response[$contactApi->listName()];

        header('Content-Type: application/json');
        echo json_encode($contacts);
        exit;
    }

    public function action_Campaigns()
    {
        list($auth, $baseUrl) = $this->setupApi();

        $api = new MauticApi();

        $campaignApi = $api->newApi('campaigns', $auth, $baseUrl);

        $response = $campaignApi->getList(null, null, null, null, null, true, true);

        if (isset($response['error'])) {
            LoggerManager::getLogger()->fatal('Mautic Response Error: ' . $response['error']['code'] . ": " . $response['error']['message']);
            echo $response['error']['code'] . ": " . $response['error']['message'];
            exit;
        }

        $campaigns = $response[$campaignApi->listName()];

        header('Content-Type: application/json');
        echo json_encode($campaigns);
        exit;
    }

    /**
     * @return [ApiAuth, string] The Auth for an Api and the BaseUrl for the API
     */
    private function setupApi()
    {
        /** @var Administration $admin */
        $admin = BeanFactory::getBean('Administration');
        $admin->retrieveSettings(self::MAUTIC_SETTINGS_CATEGORY);

        $configurator = new Configurator();

        $baseUrl = $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_base_url'];
        $oauthAccessTokenDataJson = isset($admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_oauth_access_token']) ? html_entity_decode($admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_oauth_access_token']) : '';
        $oauthAccessTokenData = json_decode($oauthAccessTokenDataJson, true);

        $settings = [
            'baseUrl'          => $baseUrl,
            'version'          => 'OAuth2',
            'clientKey'        => $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_client_id'],
            'clientSecret'     => $admin->settings[self::MAUTIC_SETTINGS_CATEGORY . '_client_secret'],
            'callback'         => $configurator->config['site_url'] .'/index.php?module=Mautic&action=OauthAuthorize'
        ];

        $settings['accessToken']        = $oauthAccessTokenData['access_token'];
        $settings['accessTokenExpires'] = $oauthAccessTokenData['expires'];
        $settings['refreshToken']       = $oauthAccessTokenData['refresh_token'];

        $initAuth = new ApiAuth();

        /** @var Mautic\Auth\OAuth $auth */
        $auth = $initAuth->newAuth($settings);

        try {
            if ($auth->validateAccessToken()) {

                if ($auth->accessTokenUpdated()) {
                    $accessTokenData = $auth->getAccessTokenData();

                    $admin->saveSetting(self::MAUTIC_SETTINGS_CATEGORY, 'oauth_access_token', json_encode($accessTokenData));
                }
            }
        } catch (\Exception $e) {
            LoggerManager::getLogger()->warn('Exception validating access token: ' . $e->getMessage());
        }

        return [ $auth, $baseUrl ];
    }
}
