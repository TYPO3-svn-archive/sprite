<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Pagehits class to store calls of pages to db and make them accessible
 *
 * Full configuration example:
 *
 * includeLibs.user_Sprite = EXT:sprite/Classes/class.userSprite.php
 * lib.sprite_generator = USER_INT
 * lib.sprite_generator {
 *     userFunc = user_Sprite->generate
 *     userFunc {
 *        name = mysprite
 *     	  files {
 *     		10 = fileadmin/templates/images/sprite/image1.png
 *   		20 = fileadmin/templates/images/sprite/image2.png
 *    	  }
 *    	  directories {
 *    		10 = fileadmin/templates/images/sprite/
 *    	  }
 *        cacheSpriteImage = 1
 *        linkCssFiles = 1
 *        useJpg = 0
 *        debugListClasses = 0
 *     }
 * }
 */
class user_Sprite extends t3lib_spritemanager_SpriteGenerator {
	/** @var boolean */
	protected $linkCssFiles = TRUE;

	/** @var boolean */
	protected $useJpg = FALSE;

	/** @var boolean not supported in >= 4.6 */
	protected $generateGIFCopy = FALSE;

	/** @var boolean */
	protected $debugListClasses = FALSE;

	/** @var array */
	protected $files = array();

	/** @var array */
	protected $directories = array();

	/**
	 * Sets name, files and directories by given configuration.
	 *
	 * @param array $configuration
	 *
	 * @return void
	 */
	protected function setSettings(array $configuration) {
		$this->setNamespace('tx-sprite');
		$this->setSpriteName(($configuration['name']) ? $configuration['name'] : 'mysprite');
		$this->setIconSpace(($configuration['iconSpace']) ? intval($configuration['iconSpace']) : 2);
		$this->setIncludeTimestampInCSS(!(boolean) $configuration['cacheSpriteImage']);

		$this->linkCssFiles = (boolean) $configuration['linkCssFiles'];
		$this->useJpg = (boolean) $configuration['useJpg'];
		$this->debugListClasses = (boolean) $configuration['debugListClasses'];

		$this->getFiles($configuration);
	}

	/**
	 * Loads images of given directories to $this->files array and checks if the files exist.
	 *
	 * @param $configuration
	 *
	 * @return void
	 */
	protected function getFiles($configuration) {
			// set directories
		if (is_array($configuration['directories.'])) {
			$this->directories = $configuration['directories.'];
		} else {
			if (!empty($configuration['directories'])) {
				$this->directories = array($configuration['directories']);
			}
		}

			// set get files from directories
		if (count($this->directories) > 0) {
			foreach ($this->directories as $directory) {
				if (substr($directory, -1) === '/') {
					$directory = substr($directory, 0, -1);
				}
				$this->files = array_merge($this->files, $this->getFolder($directory));
			}
		}

			// add files if set
		if (is_array($configuration['files.'])) {
			$this->files = array_merge($this->files, $configuration['files.']);
		}

			// clean the files array and discard invalid filenames
		$this->files = array_unique($this->files);
		foreach ($this->files as $index => $file) {
			if (!file_exists($file)) {
				unset($this->files[$index]);
			}
		}
	}

	/**
	 * Returns the setted spriteName
	 * @return string SpriteName
	 */
	protected function getSpriteName() {
		return $this->spriteName;
	}

	/**
	 * Generates the sprite and adds css files to additionalHeaderData
	 *
	 * @param $content
	 * @param array $conf
	 *
	 * @return string Empty string or debug output if set
	 */
	public function generate($content, $conf = array()) {
		$this->setSettings($conf['userFunc.']);

		$result = $this->generateSpriteFromArray($this->files);
		unset($result['spriteGifImage'], $result['cssGif']);

		if ($this->useJpg === TRUE) {
			$result['spriteImage'] = str_replace('.png', '.jpg', $result['spriteImage']);
		}

		if ($this->linkCssFiles === TRUE) {
			$cssFile = $this->makePathRelative($result['cssFile']);
			$GLOBALS['TSFE']->additionalHeaderData['tx_sprite_' . $this->getSpriteName()]
				= '<link rel="stylesheet" type="text/css" href="' . $cssFile . '" />';
		}

		if ($this->debugListClasses === TRUE) {
			return $this->createDebugOutput($result);
		}
		return '';
	}

	/**
	 * Creates and returns debug output, to tell the webdesigner the generated sprite classes.
	 *
	 * @param array $result
	 * @return string Help for webdesigner
	 */
	protected function createDebugOutput(array $result) {
		$classList = '<li><em><b style="color: red;">tx-sprite-' . $this->getSpriteName() . '</b></em></li>';
		foreach ($result['iconNames'] as $iconName) {
			$iconName = str_replace($this->getSpriteName() . '-', 'tx-sprite-', $iconName);
			$classList .= '<li>' . $iconName . '</li>';
		}

		if ($this->linkCssFiles === FALSE) {
			$cssFile = $this->makePathRelative($result['cssFile']);
			$generatedFiles = '<div><b>Sprite CSS file:</b> <a target="_blank" href="' . $cssFile . '">' . $cssFile . '</a></div><hr />';
		}

		return '<fieldset>' . $generatedFiles . '<b>Available sprite classes:</b><ul>' . $classList . '</ul><hr /><div><b>Example usage:</b><br /><code>&lt;img src=&quot;blank.gif&quot; class=&quot;tx-sprite-' . $this->getSpriteName() . ' ' . $iconName . '&quot; alt=&quot;&quot;&gt;&lt;/div&gt;</code></div></fieldset>';
	}

	/**
	 * Makes a absolute server path relative, for usage in frontend
	 *
	 * @param string $absolutePath
	 *
	 * @return string relative path
	 */
	protected function makePathRelative($absolutePath) {
		return substr($absolutePath, strlen(PATH_site));
	}


	protected function generateGraphic() {
		if ($this->useJpg === FALSE) {
			return parent::generateGraphic();
		}
		return $this->generateGraphicJpg();
	}

	protected function generateGraphicJpg() {
		$tempSprite = t3lib_div::tempnam($this->spriteName);
		$filePath = array(
			'mainFile' => PATH_site . $this->spriteFolder . $this->spriteName . '.jpg',
		);
			// create black true color image with given size
		$newSprite = imagecreatetruecolor($this->spriteWidth, $this->spriteHeight);
		imagefill($newSprite, 0, 0, imagecolorallocate($newSprite, 255, 255, 255));
		foreach ($this->iconsData as $icon) {
			$fileExtension = str_replace('jpg', 'jpeg', strtolower($icon['fileExtension']));
			$function = 'imagecreatefrom' . $fileExtension;
			if (function_exists($function)) {
				$currentIcon = $function($icon['fileName']);
				imagecopy($newSprite, $currentIcon, $icon['left'], $icon['top'], 0, 0, $icon['width'], $icon['height']);
			}
		}
		imagejpeg($newSprite, $tempSprite . '.jpg');

		t3lib_div::upload_copy_move($tempSprite . '.jpg', $filePath['mainFile']);
		t3lib_div::unlink_tempfile($tempSprite . '.jpg');
	}

	protected function generateCSS() {
		parent::generateCSS();

		if ($this->useJpg === TRUE) {
			$cssFile = PATH_site . $this->cssFolder . $this->spriteName . '.css';
			$cssContent = file_get_contents($cssFile);
			$cssContent = str_replace('.png', '.jpg', $cssContent);
			file_put_contents($cssFile, $cssContent);
		}
	}


}
?>