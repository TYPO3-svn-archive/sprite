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
 *        linkCssFiles = 1
 *        useJpg = 0
 *     }
 * }
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class user_Sprite {
	/** @var string */
	protected $spriteName = '';

	/** @var boolean */
	protected $linkCssFiles = TRUE;

	/** @var array */
	protected $files = array();

	/** @var array Key contains filepath, value md5 hash */
	protected $fileHashes = array();

	/** @var Tx_Sprite_SpriteGenerator */
	protected $spriteGenerator = NULL;

	/** @var string Hash of all files and its contents */
	protected $hash = '';


	/**
	 * Constructor
	 *
	 * @return user_Sprite
	 */
	public function __construct() {
		$this->spriteGenerator = t3lib_div::makeInstance('Tx_Sprite_SpriteGenerator');
	}

	/**
	 * Initializes the spriteGenerator and user function settings
	 *
	 * @param array $configuration of user function
	 *
	 * @return void
	 */
	protected function initialize(array $configuration) {
			// Render typoscript in configuration
		$configuration = Tx_Sprite_Utility_TypoScript::renderTypoScript($configuration);

		$this->spriteName = ($configuration['name']) ? $configuration['name'] : 'mysprite';

		$this->getFiles($configuration);
		$this->calcHash($configuration);

		$this->spriteGenerator->setNamespace('tx-sprite');
		$this->spriteGenerator->setSpriteName($this->spriteName);
		$this->spriteGenerator->setIconSpace(intval($configuration['iconSpace']));
		$this->spriteGenerator->setIncludeTimestampInCSS((boolean) $configuration['includeTimestampInCSS']);
		$this->spriteGenerator->setUseJpg((boolean) $configuration['useJpg']);
		$this->spriteGenerator->setHash($this->hash);

		$this->linkCssFiles = (boolean) $configuration['linkCssFiles'];
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
		$directories = array();
		if (is_array($configuration['directories'])) {
			$directories = $configuration['directories'];
		} else {
			if (!empty($configuration['directories'])) {
				$directories = array($configuration['directories']);
			}
		}

			// set get files from directories
		if (count($directories) > 0) {
			foreach ($directories as $directory) {
				if (substr($directory, -1) === '/') {
					$directory = substr($directory, 0, -1);
				}
				$this->files = array_merge($this->files, $this->spriteGenerator->getFolderByDirectory($directory));
			}
		}

			// add files if set
		if (is_array($configuration['files']) && count($configuration['files']) > 0) {
			$this->files = array_merge($this->files, $configuration['files']);
		} elseif ($configuration['files']) {
			$this->files = array_merge($this->files, t3lib_div::trimExplode(',', $configuration['files'], TRUE));
			foreach ($this->files as $index => $filepath) {
				$filename = '-' . preg_replace('/.*\/(.*)\..*/i', '$1', $filepath);
				$this->files[$filename] = $filepath;
				unset($this->files[$index]);
			}
		}

			// clean the files array and discard invalid filenames
		$this->files = array_unique($this->files);
		foreach ($this->files as $index => $file) {
			if (!file_exists($file)) {
				unset($this->files[$index]);
			} else {
				$this->files[$this->spriteName . '-' . $this->spriteName . $index] = $this->files[$index];
				unset($this->files[$index]);
				$this->fileHashes[$file] = md5_file($file);
			}
		}
	}

	/**
	 * Calculates an overall hash for all files and its contents
	 *
	 * @param array $configuration
	 * @return void
	 */
	protected function calcHash(array $configuration) {
		$this->hash = md5(serialize($this->fileHashes) . serialize($configuration));
	}

	/**
	 * Generates the sprite and adds css files to additionalHeaderData
	 *
	 * @param $content
	 * @param array $conf
	 *
	 * @return void
	 */
	public function generate($content, $conf = array()) {
		$this->initialize($conf['userFunc.']);

		if (count($this->files) === 0) {
			return; // cancel if no files are given
		}

		$spriteCssPath = PATH_site . $this->spriteGenerator->getSpriteFolder() . $this->spriteName . '-' . $this->hash . '.css';
		if (file_exists($spriteCssPath)) {
			$result['cssFile'] = $spriteCssPath;
		} else {
			$result = $this->spriteGenerator->generateSpriteFromArray($this->files);
		}

		if ($this->linkCssFiles === TRUE) {
			$cssFile = $this->makePathRelative($result['cssFile']);
			$GLOBALS['TSFE']->additionalHeaderData['tx_sprite_' . $this->hash]
				= '<link rel="stylesheet" type="text/css" href="' . $cssFile . '" />';
		}
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