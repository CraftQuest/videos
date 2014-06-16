<?php

/**
 * Videos plugin for Craft CMS
 *
 * @package   Videos
 * @author    Benjamin David
 * @copyright Copyright (c) 2014, Dukt
 * @link      https://dukt.net/craft/videos/
 * @license   https://dukt.net/craft/videos/docs/license
 */

namespace Craft;

require(CRAFT_PLUGINS_PATH.'videos/vendor/autoload.php');

use Symfony\Component\Finder\Finder;

class VideosService extends BaseApplicationComponent
{
    private $_gateways = array();
    private $_allGateways = array();
    private $_gatewaysLoaded = false;

    private $videoGateways = array(
        'youtube' => array(
            'name' => "YouTube",
            'handle' => 'youtube',
            'oauth' => array(
                'name' => "Google",
                'handle' => 'google',
                'scopes' => array(
                    'https://www.googleapis.com/auth/userinfo.profile',
                    'https://www.googleapis.com/auth/userinfo.email',
                    'https://www.googleapis.com/auth/youtube',
                    'https://www.googleapis.com/auth/youtube.readonly'
                ),
                'params' => array(
                    'access_type' => 'offline',
                    'approval_prompt' => 'force'
                )
            )
        ),

        'vimeo' => array(
            'name' => "Vimeo",
            'handle' => 'vimeo',
            'oauth' => array(
                'name' => "Vimeo",
                'handle' => 'vimeo'
            )
        )
    );

    public function getGatewayOpts($handle)
    {
        return $this->videoGateways[$handle];
    }

    public function getScopes($handle)
    {
        foreach($this->videoGateways as $gateway)
        {
            if($gateway['oauth']['handle'] == $handle)
            {
                if(!empty($gateway['oauth']['scopes']))
                {
                    return $gateway['oauth']['scopes'];
                }

                break;
            }
        }

        return array();
    }

    public function getParams($handle)
    {
        foreach($this->videoGateways as $gateway)
        {
            if($gateway['oauth']['handle'] == $handle)
            {
                if(!empty($gateway['oauth']['params']))
                {
                    return $gateway['oauth']['params'];
                }

                break;
            }
        }

        return array();
    }

    public function saveToken($providerHandle, $token)
    {
        // token
        $token = craft()->oauth->encodeToken($token);

        // get plugin settings
        $plugin = craft()->plugins->getPlugin('videos');
        $settings = $plugin->getSettings();
        $tokens = $settings->tokens;

        $tokens[$providerHandle] = $token;

        // save token to plugin settings
        $settings->tokens = $tokens;
        craft()->plugins->savePluginSettings($plugin, $settings);

    }

    public function getToken($handle)
    {
        try
        {
            $plugin = craft()->plugins->getPlugin('videos');
            $settings = $plugin->getSettings();

            if(!empty($settings['tokens'][$handle]))
            {
                // get token from settings
                $token = craft()->oauth->decodeToken($settings['tokens'][$handle]);

                // refresh token if needed
                if(craft()->oauth->refreshToken($handle, $token))
                {
                    // save token
                    $this->saveToken($token);
                }

                // return token
                return $token;
            }
        }
        catch(\Exception $e)
        {
            // todo
            throw new Exception($e, 1);
        }
    }

    public function getVideoThumbnail($gateway, $id, $w = 340, $h = null)
    {
        $uri = 'videosthumbnails/'.$gateway.'/'.$id.'/';

        $uri .= $w;

        if(!$h)
        {
            // calculate hd ratio (1,280×720)
            $h = floor($w * 720 / 1280);
        }

        $uri .= '/'.$h;


        return UrlHelper::getResourceUrl($uri);
    }

    public function getVideoByUrl($videoUrl, $enableCache = true, $cacheExpiry = 3600)
    {
        $video = $this->_getVideoObjectByUrl($videoUrl, $enableCache, $cacheExpiry);

        if($video)
        {

            $attributes = (array) $video;

            $response = Videos_VideoModel::populateModel($attributes);

            // $response['thumbnail'] = $response->getThumbnail();

            return $response;
        }
    }

    public function getVideoById($gateway, $id)
    {
        $video = $this->_getVideoObjectById($gateway, $id);

        if($video)
        {

            $video = (array) $video;

            $response = Videos_VideoModel::populateModel($video);

            // $response['thumbnail'] = $response->getThumbnail();

            return $response;
        }
    }

    public function getEmbed($videoUrl, $opts)
    {
        $video = $this->_getVideoObjectByUrl($videoUrl);

        return $video->getEmbed($opts);
    }

    public function _getVideoObjectById($gatewayHandle, $id, $enableCache = true, $cacheExpiry = 3600)
    {
        if($enableCache)
        {
            $key = 'videos.video.'.$gatewayHandle.'.'.md5($id);

            $response = craft()->fileCache->get($key);

            if($response)
            {
                return $response;
            }
        }

        try
        {

            $gateways = $this->getGateways();

            foreach($gateways as $gateway)
            {
                if($gateway->handle == $gatewayHandle)
                {

                    $response = $gateway->getVideo(array('id' => $id));

                    if($response)
                    {
                        if($enableCache)
                        {
                            craft()->fileCache->set($key, $response, $cacheExpiry);
                        }

                        return $response;
                    }
                }
            }

        }
        catch(\Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }

    public function _getVideoObjectByUrl($videoUrl, $enableCache = true, $cacheExpiry = 3600)
    {
        if(craft()->config->get('disableCache', 'videos') == true)
        {
            $enableCache = false;
        }

        if($enableCache)
        {
            $key = 'videos.video.'.md5($videoUrl);

            $response = craft()->fileCache->get($key);

            if($response)
            {
                return $response;
            }
        }

        $gateways = $this->getGateways();

        foreach($gateways as $gateway)
        {
            $params['url'] = $videoUrl;

            try
            {
                $response = $gateway->videoFromUrl($params);

                if($response)
                {
                    if($enableCache)
                    {
                        craft()->fileCache->set($key, $response, $cacheExpiry);
                    }

                    return $response;
                }

            }
            catch(\Exception $e)
            {
                // throw new Exception($e->getMessage());
            }
        }

        return false;
    }

    public function getGatewaysWithSections()
    {
        try
        {
            // get gateways with sections

            $gatewaysWithSections = array();

            $gateways = $this->getGateways();

            foreach($gateways as $gateway)
            {
                if($gateway)
                {
                    $class = '\Dukt\Videos\App\\'.$gateway->providerClass;

                    $sections = $class::getSections($gateway);

                    if($gateway->sections = $class::getSections($gateway))
                    {
                        array_push($gatewaysWithSections, $gateway);
                    }
                }
            }


            // i18n

            foreach($gatewaysWithSections as $k => $g)
            {
                foreach($g->sections as $k2 => $s)
                {
                    $g->sections[$k2]['name'] = Craft::t($s['name']);

                    foreach($s['childs'] as $k3 => $c)
                    {
                        $g->sections[$k2]['childs'][$k3]['name'] = Craft::t($c['name']);
                    }
                }

                $gatewaysWithSections[$k] = $g;
            }

            // return
            return $gatewaysWithSections;
        }
        catch(\Exception $e)
        {
            throw new \Exception($e->getMessage());
        }
    }

    public function getGateways($enabledOnly = true)
    {
        $this->loadGateways();

        if($enabledOnly)
        {
            return $this->_gateways;
        }
        else
        {
            return $this->_allGateways;
        }
    }

    public function getGateway($gatewayHandle, $enabledOnly = true)
    {
        $this->loadGateways();

        if($enabledOnly)
        {
            $gateways = $this->_gateways;
        }
        else
        {
            $gateways = $this->_allGateways;
        }

        foreach($gateways as $g)
        {
            if($g->handle == $gatewayHandle)
            {
                return $g;
            }
        }

        return null;
    }

    public function loadGateways()
    {
        if(!$this->_gatewaysLoaded)
        {
            $this->_gatewaysLoaded = true;

            $finder = new Finder();

            $directories = $finder->directories()->depth(0)->in(CRAFT_PLUGINS_PATH.'videos/vendor/dukt/videos/src/Dukt/Videos/');

            foreach($directories as $directory)
            {
                $pathName = $directory->getRelativePathName();

                if($pathName == 'Common')
                {
                    continue;
                }

                // instantiate videos service

                $nsClass = '\\Dukt\\Videos\\'.$pathName.'\\Service';
                $gateway = new $nsClass;
                $handle = strtolower($gateway->oauthProvider);

                $provider = craft()->oauth->getProvider($gateway->oauthProvider);
                $token = $this->getToken($handle);

                if($token)
                {
                    $provider->source->setToken($token);

                    $gateway->init($provider->providerSource->service);

                    if($provider->providerSource->hasAccessToken())
                    {
                        $this->_gateways[] = $gateway;
                    }
                }

                $this->_allGateways[] = $gateway;
            }
        }
    }
}
