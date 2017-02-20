<?php
/**
 * @link      https://dukt.net/craft/videos/
 * @copyright Copyright (c) 2017, Dukt
 * @license   https://dukt.net/craft/videos/docs/license
 */

namespace dukt\videos\controllers;

use Craft;
use craft\web\Controller;
use dukt\videos\Plugin as Videos;

/**
 * Explorer controller
 */
class ExplorerController extends Controller
{
	// Properties
	// =========================================================================

	private $explorerNav;

    // Public Methods
    // =========================================================================

    /**
     * Get Explorer Modal
     *
     * @return null
     */
    public function actionGetModal()
    {
	    // $this->requireAjaxRequest();
/*
        try
        {*/
	        $namespaceInputId = Craft::$app->getRequest()->getBodyParam('namespaceInputId');
            $nav = $this->getExplorerNav();

	        $gateways = [];
	        $allGateways = Videos::$plugin->getGateways()->getGateways();

	        foreach($allGateways as $_gateway)
	        {
				$gateway = [
					'name' => $_gateway->getName(),
					'handle' => $_gateway->getHandle(),
					'supportsSearch' => $_gateway->supportsSearch(),
				];

		        array_push($gateways, $gateway);
	        }

            return $this->asJson(array(
                'success' => true,
                'html' => Craft::$app->getView()->renderTemplate('videos/_elements/explorer', [
                    'namespaceInputId' => $namespaceInputId,
                    'nav' => $nav,
	                'gateways' => $gateways
                ])
            ));
/*        }
        catch(\Exception $e)
        {
            // Don't need to log errors again as they are already logged by BaseGateway::api()
            return $this->asErrorJson('Couldn’t load explorer.');
        }*/
    }

    /**
     * Get Videos
     *
     * @return null
     */
    public function actionGetVideos()
    {
        // $this->requireAjaxRequest();

        try
        {
            $gatewayHandle = Craft::$app->getRequest()->getParam('gateway');
            $gatewayHandle = strtolower($gatewayHandle);

            $method = Craft::$app->getRequest()->getParam('method');
            $options = Craft::$app->getRequest()->getParam('options');

            $gateway = Videos::$plugin->getGateways()->getGateway($gatewayHandle);

            if($gateway)
            {
                $videosResponse = $gateway->getVideos($method, $options);

                $html = Craft::$app->getView()->renderTemplate('videos/_elements/videos', array(
                    'videos' => $videosResponse['videos']
                ));

                return $this->asJson(array(
                    'html' => $html,
                    'more' => $videosResponse['more'],
                    'moreToken' => $videosResponse['moreToken']
                ));
            }
            else
            {
                throw new \Exception("Gateway not available");
            }
        }
        catch(\Exception $e)
        {
             // VideosPlugin::log('Couldn’t get videos: '.$e->getMessage(), LogLevel::Error);

            return $this->asErrorJson($e->getMessage());
        }
    }

	// Private Methods
	// =========================================================================


	/**
	 * Get Explorer Nav
	 *
	 * @return array
	 */
	private function getExplorerNav()
	{
		if(!$this->explorerNav)
		{
			$gatewaySections = [];

			$gateways = Videos::$plugin->getGateways()->getGateways();

			foreach ($gateways as $gateway)
			{
				$gatewaySections[] = $gateway->getExplorerSections();
			}

			$this->explorerNav = [
				'gateways' => $gateways,
				'gatewaySections' => $gatewaySections
			];
		}

		return $this->explorerNav;
	}
}