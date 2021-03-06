<?php
/**
* @author  Joel Wan & Mark Slemko.  Designs by Jonathan Easton
* @link  http://www.phpobjectgenerator.com
* @copyright  Offered under the  BSD license
*
* This upgrade file does the following:
* 1. Checks if there is a new version of POG
* 2. If there is, it reads generates newer versions of all objects in the object directory,
* zip then and present them to the user to 'download'
*/
ini_set("max_execution_time", 0);
include_once "../../configuration.php";
include_once "class.zipfile.php";
include_once "nusoap.php";
include_once "setup_misc.php";

	/**
	 * Connects to POG SOAP server defined in configuration.php and
	 * generates new versions of all objects detected in /objects/ dir.
	 * All upgraded objects are then zipped and presented to user.
	 *
	 * @param string $path
	 */
	function UpdateAllObjects($path)
	{
		$dir = opendir($path);
		$objects = array();
		while(($file = readdir($dir)) !== false)
		{
			if(strlen($file) > 4 && substr(strtolower($file), strlen($file) - 4) === '.php' && !is_dir($file) && $file != "class.database.php" && $file != "configuration.php" && $file != "setup.php" && $file != "class.pog_base.php")
			{
				$objects[] = $file;
			}
		}
		closedir($dir);
		$i = 0;
		foreach($objects as $object)
		{
			$content = file_get_contents($path."/".$object);
			$contentParts = split("<b>",$content);
			if (isset($contentParts[1]))
			{
				$contentParts2 = split("</b>",$contentParts[1]);
			}
			if (isset($contentParts2[0]))
			{
				$className = trim($contentParts2[0]);
			}
			if (isset($className))
			{
				eval ('include_once("../../objects/class.'.strtolower($className).'.php");');
				$instance = new $className();
				if (!TestIsMapping($instance))
				{
					$objectNameList[] = $className;

					$linkParts1 = split("\*\/", $contentParts[1]);
					$linkParts2 = split("\@link", $linkParts1[0]);
					$link = $linkParts2[1];

					$client = new soapclient(
								$GLOBALS['configuration']['soap'],
								true,
								(isset($GLOBALS['configuration']['proxy_host'])?$GLOBALS['configuration']['proxy_host']:false),
								(isset($GLOBALS['configuration']['proxy_port'])?$GLOBALS['configuration']['proxy_port']:false),
								(isset($GLOBALS['configuration']['proxy_username'])?$GLOBALS['configuration']['proxy_username']:false),
								(isset($GLOBALS['configuration']['proxy_password'])?$GLOBALS['configuration']['proxy_password']:false)
								);
					$params = array('link' 	=> $link);
					if ($i == 0)
					{
						$package = unserialize($client->call('GeneratePackageFromLink', $params));
					}
					else
					{
						$objectString = $client->call('GenerateObjectFromLink', $params);
						$package["objects"]["class.".strtolower($className).".php"] = $objectString;
					}
				}
			}
			$i++;
		}

		//upgrade mapping classes if any
		foreach ($objectNameList as $objectName)
		{
			$instance = new $objectName();
			foreach ($instance->pog_attribute_type as $key => $attribute_type)
			{
				if ($attribute_type['db_attributes'][1] == "JOIN")
				{
					$params = array('objectName1' => $objectName, 'objectName2' => $key, 'language' => "php4", 'wrapper' => 'POG', 'pdoDriver' => ' ');
					$mappingString = $client->call('GenerateMapping', $params);
					$package["objects"]['class.'.strtolower(MappingName($objectName, $key)).'.php'] = $mappingString;
				}
			}
		}



		$zipfile = new createZip();
		$zipfile -> addPOGPackage($package);
		$zipfile -> forceDownload("pog.".time().".zip");
	}

	/**
	 * Checks if POG generator has been updated
	 *
	 * @return unknown
	 */
	function UpdateAvailable()
	{
		$client = new soapclient($GLOBALS['configuration']['soap'], true);
		$params = array();
		$generatorVersion = base64_decode($client->call('GetGeneratorVersion', $params));
		if ($generatorVersion != $GLOBALS['configuration']['versionNumber'].$GLOBALS['configuration']['revisionNumber'])
		{
			return true;
		}
		else
		{
			return  false;
		}
	}

	if (UpdateAvailable())
	{
		UpdateAllObjects("../../objects/");
	}
	else
	{
		echo "<script>
			alert('All POG objects are already up to date');
			window.close();
		</script>";
	}
?>