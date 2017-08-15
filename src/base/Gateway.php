<?php
/**
 * @link      https://dukt.net/craft/videos/
 * @copyright Copyright (c) 2017, Dukt
 * @license   https://dukt.net/craft/videos/docs/license
 */

namespace dukt\videos\base;

use Craft;
use craft\helpers\UrlHelper;
use dukt\videos\errors\JsonParsingException;
use dukt\videos\Plugin as Videos;
use GuzzleHttp\Exception\BadResponseException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Grant\RefreshToken;
use Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Gateway class
 *
 * @author Dukt <support@dukt.net>
 * @since  2.0
 */
abstract class Gateway implements GatewayInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Return the handle of the gateway based on its class name
     *
     * @return string
     */
    public function getHandle()
    {
        $handle = get_class($this);
        $handle = substr($handle, strrpos($handle, "\\") + 1);
        $handle = strtolower($handle);

        return $handle;
    }

    /**
     * Returns the icon URL.
     *
     * @return string|false|null
     */
    public function getIconUrl()
    {
        $iconAlias = $this->getIconAlias();

        if (file_exists(Craft::getAlias($iconAlias))) {
            return Craft::$app->assetManager->getPublishedUrl($iconAlias, true);
        }
    }

    /**
     * OAuth Connect
     *
     * @return null
     */
    public function oauthConnect()
    {
        $provider = $this->getOauthProvider();

        Craft::$app->getSession()->set('videos.oauthState', $provider->getState());

        $scope = $this->getOauthScope();
        $options = $this->getOauthAuthorizationOptions();

        if (!is_array($options)) {
            $options = [];
        }

        $options['scope'] = $scope;

        $authorizationUrl = $provider->getAuthorizationUrl($options);

        return Craft::$app->getResponse()->redirect($authorizationUrl);
    }

    /**
     * Returns the gateway's OAuth provider
     *
     * @return mixed
     */
    public function getOauthProvider()
    {
        $oauthProviderOptions = Videos::$plugin->getSettings()->oauthProviderOptions;

        $options = [];

        if (isset($oauthProviderOptions[$this->getHandle()])) {
            $options = $oauthProviderOptions[$this->getHandle()];
        }

        if (!isset($options['redirectUri'])) {
            $options['redirectUri'] = $this->getRedirectUri();
        }

        return $this->createOauthProvider($options);
    }

    /**
     * Returns the OAuth provider’s name.
     *
     * @return string
     */
    public function getOauthProviderName()
    {
        return $this->getName();
    }

    /**
     * Returns the redirect URI.
     *
     * @return string
     */
    public function getRedirectUri()
    {
        return UrlHelper::actionUrl('videos/oauth/callback');
    }

    /**
     * OAuth Scope
     *
     * @return array|null
     */
    public function getOauthScope()
    {
        return null;
    }

    /**
     * OAuth Authorization Options
     *
     * @return array|null
     */
    public function getOauthAuthorizationOptions()
    {
        return null;
    }

    /**
     * OAuth Callback
     *
     * @return null
     */
    public function oauthCallback()
    {
        $provider = $this->getOauthProvider();

        $code = Craft::$app->getRequest()->getParam('code');

        try {
            // Try to get an access token (using the authorization code grant)
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);

            // Save token
            Videos::$plugin->getOauth()->saveToken($this->getHandle(), $token);

            // Reset session variables

            // Redirect
            Craft::$app->getSession()->setNotice(Craft::t('videos', "Connected to {gateway}.", ['gateway' => $this->getName()]));
        } catch (Exception $e) {
            // Failed to get the token credentials or user details.
            Craft::$app->getSession()->setError($e->getMessage());
        }

        $redirectUrl = UrlHelper::cpUrl('videos/settings');

        return Craft::$app->getResponse()->redirect($redirectUrl);
    }

    /**
     * Create token from data (array)
     *
     * @param array $tokenData
     *
     * @return AccessToken
     */
    public function createTokenFromData(array $tokenData)
    {
        if (isset($tokenData['accessToken'])) {
            $token = new AccessToken([
                'access_token' => (isset($tokenData['accessToken']) ? $tokenData['accessToken'] : null),
                'expires' => (isset($tokenData['expires']) ? $tokenData['expires'] : null),
                'refresh_token' => (isset($tokenData['refreshToken']) ? $tokenData['refreshToken'] : null),
                'resource_owner_id' => (isset($tokenData['resourceOwnerId']) ? $tokenData['resourceOwnerId'] : null),
                'values' => (isset($tokenData['values']) ? $tokenData['values'] : null),
            ]);

            if (!empty($token->getRefreshToken()) && $token->hasExpired()) {
                $provider = $this->getOauthProvider();

                $grant = new RefreshToken();

                $newToken = $provider->getAccessToken($grant, ['refresh_token' => $token->getRefreshToken()]);

                $token = new AccessToken([
                    'access_token' => $newToken->getToken(),
                    'expires' => $newToken->getExpires(),
                    'refresh_token' => $token->getRefreshToken(),
                    'resource_owner_id' => $newToken->getResourceOwnerId(),
                    'values' => $newToken->getValues(),
                ]);

                Videos::$plugin->getOauth()->saveToken($this->getHandle(), $token);
            }

            return $token;
        }
    }

    /**
     * Has Token
     *
     * @return bool
     */
    public function hasToken()
    {
        try {
            $token = $this->getToken();

            if ($token) {
                return true;
            }
        } catch (\Exception $e) {
            // Todo: log error
        }

        return false;
    }

    /**
     * Get Token
     *
     * @return mixed
     */
    public function getToken()
    {
        return Videos::$plugin->getOauth()->getToken($this->getHandle());
    }

    /**
     * Sets the token for authenticating with the gateway’s API
     *
     * @param $token
     */
    public function setAuthenticationToken($token)
    {
        $this->token = $token;
    }

    /**
     * Where the OAuth flow should be enable or not for this gateway
     *
     * @return bool
     */
    public function enableOauthFlow()
    {
        return true;
    }

    /**
     * Whether the gateway supports search or not
     *
     * @return bool
     */
    public function supportsSearch()
    {
        // Deprecated in 2.0: Each gateway will need to specify its support for search
        return true;
    }

    /**
     * Returns the HTML of the embed from a video ID
     *
     * @param       $videoId
     * @param array $options
     *
     * @return string
     */
    public function getEmbedHtml($videoId, $options = [])
    {
        $embedAttributes = [
            'frameborder' => "0",
            'allowfullscreen' => "true",
            'allowscriptaccess' => "true"
        ];

        $disableSize = false;

        if (isset($options['disable_size'])) {
            $disableSize = $options['disable_size'];
        }

        if (!$disableSize) {
            if (isset($options['width'])) {
                $embedAttributes['width'] = $options['width'];
                unset($options['width']);
            }

            if (isset($options['height'])) {
                $embedAttributes['height'] = $options['height'];
                unset($options['height']);
            }
        }

        if (!empty($options['iframeClass'])) {
            $embedAttributes['class'] = $options['iframeClass'];
            unset($options['iframeClass']);
        }

        $embedUrl = $this->getEmbedUrl($videoId, $options);

        $embedAttributesString = '';

        foreach ($embedAttributes as $key => $value) {
            $embedAttributesString .= ' '.$key.'="'.$value.'"';
        }

        return '<iframe src="'.$embedUrl.'"'.$embedAttributesString.'></iframe>';
    }

    /**
     * Returns the URL of the embed from a video ID
     *
     * @param       $videoId
     * @param array $options
     *
     * @return string
     */
    public function getEmbedUrl($videoId, $options = [])
    {
        $format = $this->getEmbedFormat();

        if (count($options) > 0) {
            $queryMark = '?';

            if (strpos($this->getEmbedFormat(), "?") !== false) {
                $queryMark = "&";
            }

            $options = http_build_query($options);

            $format .= $queryMark.$options;
        }

        return sprintf($format, $videoId);
    }

    /**
     * Returns the javascript origin URL.
     *
     * @return string
     */
    public function getJavascriptOrigin()
    {
        return UrlHelper::baseUrl();
    }

    /**
     * Returns the account.
     *
     * @return mixed
     * @throws Exception
     */
    public function getAccount()
    {
        $token = $this->getToken();

        if ($token) {
            try {
                $account = Videos::$plugin->getCache()->get(['getAccount', $token]);

                if (!$account) {
                    $oauthProvider = $this->getOauthProvider();

                    if (method_exists($oauthProvider, 'getResourceOwner')) {
                        $account = $oauthProvider->getResourceOwner($token);
                    } elseif (method_exists($oauthProvider, 'getAccount')) {
                        // Todo: Remove in OAuth 3.0
                        $account = $oauthProvider->getAccount($token);
                    }

                    Videos::$plugin->getCache()->set(['getAccount', $token], $account);
                }

                if ($account) {
                    return $account;
                }
            } catch (Exception $e) {
                Craft::info('Couldn’t get account. '.$e->getMessage(), __METHOD__);

                throw $e;
            }
        }
    }

    /**
     * Return a video from its public URL
     *
     * @param $url
     *
     * @return mixed
     * @throws Exception
     */
    public function getVideoByUrl($url)
    {
        $url = $url['url'];

        $videoId = $this->extractVideoIdFromUrl($url);

        if (!$videoId) {
            throw new Exception('Video not found with url given');
        }

        return $this->getVideoById($videoId);
    }

    /**
     * @inheritDoc GatewayInterface::getVideos()
     *
     * @param $method
     * @param $options
     *
     * @return mixed
     * @throws Exception
     */
    public function getVideos($method, $options)
    {
        $realMethod = 'getVideos'.ucwords($method);

        if (method_exists($this, $realMethod)) {
            return $this->{$realMethod}($options);
        } else {
            throw new Exception("Method ".$realMethod." not found");
        }
    }

    public function getVideosPerPage()
    {
        return Videos::$plugin->getSettings()->videosPerPage;
    }

    public function getOauthProviderOptions()
    {
        $allOauthProviderOptions = Videos::$plugin->getSettings()->oauthProviderOptions;

        if (isset($allOauthProviderOptions[$this->getHandle()])) {
            return $allOauthProviderOptions[$this->getHandle()];
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * Performs a GET request on the API
     *
     * @param string $uri
     * @param array $query
     *
     * @return mixed
     * @throws Exception
     */
    protected function get($uri, array $options = [])
    {
        $client = $this->createClient();

        try {
            $response = $client->request('GET', $uri, $options);
            $body = (string) $response->getBody();
            $data = $this->parseJson($body);
        } catch (BadResponseException $badResponseException) {
            $response = $badResponseException->getResponse();
            $body = (string) $response->getBody();

            try {
                $data = $this->parseJson($body);
            } catch (JsonParsingException $e) {
                throw $badResponseException;
            }
        }

        $this->checkResponse($response, $data);

        return $data;
    }

    /**
     * Checks a provider response for errors.
     *
     * @throws Exception
     * @param  ResponseInterface $response
     * @param  array|string $data Parsed response data
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (!empty($data['error'])) {
            $code  = 0;
            $error = $data['error'];

            if (is_array($error)) {
                $code  = $error['code'];
                $error = $error['message'];
            }

            throw new \Exception($error, $code);
        }
    }

    /**
     * Attempts to parse a JSON response.
     *
     * @param  string $content JSON content from response body
     * @return array Parsed JSON data
     * @throws Exception if the content could not be parsed
     */
    protected function parseJson($content)
    {
        $content = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new JsonParsingException(sprintf(
                "Failed to parse JSON response: %s",
                json_last_error_msg()
            ));
        }

        return $content;
    }
}
