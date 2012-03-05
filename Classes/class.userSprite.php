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
 * lib.sprite = USER_INT
 * lib.sprite {
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
 *        linkCssFiles = 1
 *    	  supportIE6 = 0
 *        debugListClasses = 0
 *     }
 * }
 */
class user_Sprite extends t3lib_spritemanager_SpriteGenerator {
	/** @var boolean */
	protected $linkCssFiles = TRUE;

	/** @var boolean */
	protected $supportIE6 = FALSE;

	/** @var boolean */
	protected $debugListClasses = FALSE;

	/** @var array */
	protected $files = array();

	/** @var array */
	protected $directories = array();

	/**
	 * Sets name, files and directories by given configuration.
	 *
	 *
	 * @param array $configuration
	 *
	 * @return void
	 */
	protected function setSettings(array $configuration) {
		$this->setNamespace('tx-sprite');
		$this->setSpriteName(($configuration['name']) ? $configuration['name'] : 'mysprite');
		$this->setIconSpace(($configuration['iconSpace']) ? intval($configuration['iconSpace']) : 2);

		$this->linkCssFiles = (boolean) $configuration['linkCssFiles'];
		$this->supportIE6 = (boolean) $configuration['supportIE6'];
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

		$cssFile = $this->makePathRelative($result['cssFile']);
		$cssGif = $this->makePathRelative($result['cssGif']);

		if ($this->linkCssFiles === TRUE) {
			$GLOBALS['TSFE']->additionalHeaderData['tx_sprite_' . $this->getSpriteName()]
				= '<link rel="stylesheet" type="text/css" href="' . $cssFile . '" />';

			if ($this->supportIE6 === TRUE) {
				$GLOBALS['TSFE']->additionalHeaderData['tx_sprite_' . $this->getSpriteName() . '_ie6']
					= '<!--[if lt IE 7]><link rel="stylesheet" type="text/css" href="' . $cssGif . '" /><![endif]-->';
			}
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
			if ($this->supportIE6 === TRUE) {
				$cssGif = $this->makePathRelative($result['cssGif']);
				$generatedFiles .= '<div><b>Sprite IE6 CSS file:</b> <a target="_blank" href="' . $cssGif . '">' . $cssGif . '</a></div><hr />';
			}
		}

		return '<fieldset>' . $generatedFiles . '<b>Available sprite classes:</b><ul>' . $classList . '</ul><hr /><div><b>Example usage:</b><br /><code>&lt;div class=&quot;tx-sprite-' . $this->getSpriteName() . ' ' . $iconName . '&quot;&gt;&lt;/div&gt;</code></div></fieldset>';
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

}
?>