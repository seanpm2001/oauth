<?php

/**
 * Craft OAuth by Dukt
 *
 * @package   Craft OAuth
 * @author    Benjamin David
 * @copyright Copyright (c) 2013, Dukt
 * @license   http://dukt.net/craft/oauth/docs#license
 * @link      http://dukt.net/craft/oauth/
 */

namespace Craft;

require_once(CRAFT_PLUGINS_PATH.'oauth/vendor/autoload.php');

use Symfony\Component\Filesystem\Filesystem;

class Oauth_PluginService extends BaseApplicationComponent
{
    // --------------------------------------------------------------------

    public function pluginUpdatePluginsUrl($plugins)
    {
        Craft::log(__METHOD__, LogLevel::Info, true);

        if(is_array($plugins)) {
            $plugins = implode(",", $plugins);
        }

        return UrlHelper::getActionUrl('oauth/plugin/updatePlugins', array('plugins' => $plugins));
    }

    // --------------------------------------------------------------------

    public function checkUpdates($pluginHandle)
    {
        Craft::log(__METHOD__, LogLevel::Info, true);

        if(is_array($pluginHandle)) {

            $updates = array();

            foreach($pluginHandle as $p) {
                if($this->checkUpdates($p)) {
                    array_push($updates, $p);
                }
            }

            return $updates;
        }

        // get remote plugin (xml)

        $remotePlugin = $this->_getRemotePlugin($pluginHandle);

        if(!is_object($remotePlugin['addon'])) {
            return false;
        }


        $remoteVersion = trim((string) $remotePlugin['addon']->version);


        // get current version (object)

        $currentPlugin = craft()->plugins->getPlugin($pluginHandle);


        if(!$currentPlugin) {
            return $remoteVersion;
        }

        $currentVersion = $currentPlugin->getVersion();


        // compare versions

        if($this->_sortableTag($remoteVersion) > $this->_sortableTag($currentVersion)) {

            Craft::log(__METHOD__.' : Update available ', LogLevel::Info, true);

            return true;

        } else {

            Craft::log(__METHOD__.' : No update available ', LogLevel::Info, true);

            return false;
        }
    }

    // --------------------------------------------------------------------

    public function download($pluginHandle)
    {
		Craft::log(__METHOD__, LogLevel::Info, true);

		// -------------------------------
		// Get ready to download & unzip
		// -------------------------------

		$return = array('success' => false);

        $filesystem = new Filesystem();

		$pluginComponent = craft()->plugins->getPlugin($pluginHandle, false);


		// plugin path

		$pluginZipDir = CRAFT_PLUGINS_PATH."_".$pluginHandle."/";
		$pluginZipPath = CRAFT_PLUGINS_PATH."_".$pluginHandle.".zip";


		// remote plugin zip url

		$remotePlugin = $this->_getRemotePlugin($pluginHandle);

		if(!$remotePlugin) {
		    $return['msg'] = "Couldn't get plugin last version";

		    Craft::log(__METHOD__.' : Could not get last version' , LogLevel::Info, true);

		    return $return;
		}

		$remotePluginZipUrl = $remotePlugin['xml']->enclosure['url'];

		// -------------------------------
		// Download & Install
		// -------------------------------

		try {

		    // download remotePluginZipUrl to pluginZipPath

		    $zipContents = file_get_contents($remotePluginZipUrl);

		    file_put_contents($pluginZipPath, $zipContents);


		    // unzip pluginZipPath into pluginZipDir

		    $contents = Zip::unzip($pluginZipPath, $pluginZipDir);


		    // move files we want to keep from their current location to unzipped location
		    // keep : .git

		    // if(file_exists(CRAFT_PLUGINS_PATH.$pluginHandle.'/.git') && !$pluginZipDir.$contents[0].'/.git') {
      //           if($filesystem->rename(CRAFT_PLUGINS_PATH.$pluginHandle.'/.git', $pluginZipDir.$contents[0].'/.git')) {

      //           }
		    // }


		    // remove current files
		    // make a backup of existing plugin (to storage ?) ?

		    if(!$filesystem->rename(CRAFT_PLUGINS_PATH.$pluginHandle, CRAFT_PLUGINS_PATH.'_old_'.$pluginHandle)) {
                die("Could not rename [plugin] to _old_[plugin]");
            }


		    // move new files to final destination

		    if(!$filesystem->rename($pluginZipDir.$contents[0].'/'.$pluginHandle.'/', CRAFT_PLUGINS_PATH.$pluginHandle)) {
                die("Could not move new plugin files to final destination");
            }

		} catch (\Exception $e) {

		    $return['msg'] = $e->getMessage();

		    Craft::log(__METHOD__.' : Crashed : '.$e->getMessage() , LogLevel::Info, true);

		    return $return;
		}


		// remove download files

		try {
		    if(!$filesystem->remove($pluginZipDir)) {
                die("Could not remove plugin zip directory");
            }

            if(!IOHelper::deleteFile($pluginZipPath)) {
                die("Could not remove plugin zip file");
            }
		} catch(\Exception $e) {

		    $return['msg'] = $e->getMessage();

		    Craft::log(__METHOD__.' : Crashed : '.$e->getMessage() , LogLevel::Info, true);

		    return $return;
		}

		Craft::log(__METHOD__.' : Success : ' , LogLevel::Info, true);

		$return['success'] = true;

		return $return;
    }

    // --------------------------------------------------------------------

    public function enable($pluginHandle)
    {
        Craft::log(__METHOD__, LogLevel::Info, true);

        $pluginComponent = craft()->plugins->getPlugin($pluginHandle, false);

        try {

            if(!$pluginComponent->isEnabled) {
                if (craft()->plugins->enablePlugin($pluginHandle)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }

        } catch(\Exception $e) {

            Craft::log(__METHOD__.' : Crashed : '.$e->getMessage(), LogLevel::Info, true);

            return false;
        }
    }

    // --------------------------------------------------------------------

    public function install($pluginHandle)
    {
        Craft::log(__METHOD__, LogLevel::Info, true);

        $pluginComponent = craft()->plugins->getPlugin($pluginHandle, false);

        try {
            if(!$pluginComponent)
            {
                Craft::log(__METHOD__.' : '.$pluginHandle.' component not found', LogLevel::Info, true);

                return false;
            }

            if(!$pluginComponent->isInstalled) {
                if (craft()->plugins->installPlugin($pluginHandle)) {
                    return true;
                } else {

                    Craft::log(__METHOD__.' : '.$pluginHandle.' component not installed', LogLevel::Info, true);

                    return false;
                }
            } else {
                return true;
            }
        } catch(\Exception $e) {

            Craft::log(__METHOD__.' : Crashed : '.$e->getMessage(), LogLevel::Info, true);

            return false;
        }
    }

    // --------------------------------------------------------------------

    private function _sortableTag($tag)
    {
        Craft::log(__METHOD__, LogLevel::Info, true);

        $tagExploded = explode(".", $tag);

        $maxLength = 5;

        foreach($tagExploded as $k => $v) {
            $fillLength = $maxLength - strlen($v);

            $fill = "";

            for($i = 0; $i < $fillLength; $i++) {
                $fill .= "0";
            }

            $tagExploded[$k] = $fill.$v;
        }

        $sortableTag = implode(".", $tagExploded);

        return $sortableTag;
    }

    // --------------------------------------------------------------------

    private function _getRemotePlugin($pluginHandle)
    {
        Craft::log(__METHOD__, LogLevel::Info, true);

        $url = 'http://dukt.net/craft/'.$pluginHandle.'/releases.xml';



        // devMode

        $pluginHashes = craft()->config->get('pluginHashes');

        if(isset($pluginHashes[$pluginHandle])) {

            $url = 'http://dukt.net/actions/tracks/updates/'.$pluginHashes[$pluginHandle].'/develop/xml';
        }


        // or refresh cache and get new updates if cache expired or forced update

        $xml = @simplexml_load_file($url);

        if(!$xml) {
            return null;
        }


        // XML from here on

        $namespaces = $xml->getNameSpaces(true);

        $versions = array();
        $zips = array();
        $xml_version = array();

        if (!empty($xml->channel->item)) {
            foreach ($xml->channel->item as $version) {
                $ee_addon       = $version->children($namespaces['ee_addon']);
                $version_number = (string) $ee_addon->version;
                $versions[$version_number] = array('xml' => $version, 'addon' => $ee_addon);
                return $versions[$version_number];
            }
        } else {
            Craft::log(__METHOD__.' : Could not get channel items', LogLevel::Info, true);
        }
    }

    // --------------------------------------------------------------------
}

