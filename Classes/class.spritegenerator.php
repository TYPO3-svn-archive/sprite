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
 * Spritegenerator
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_Sprite_SpriteGenerator extends t3lib_spritemanager_SpriteGenerator {
	/** @var boolean not supported in >= 4.6 */
	protected $generateGIFCopy = FALSE;

	/** @var boolean */
	protected $useJpg = FALSE;

	/** @var string */
	protected $hash = '';

	/**
	 * Returns the setted spriteName
	 * @return string SpriteName
	 */
	public function getSpriteName() {
		return $this->spriteName;
	}

	/**
	 * Returns the setted spriteFolder
	 * @return string SpriteFolder
	 */
	public function getSpriteFolder() {
		return $this->spriteFolder;
	}

	/**
	 * @return boolean $useJpg
	 */
	public function getUseJpg() {
		return $this->useJpg;
	}

	/**
	 * Sets the usage of jpg (instead png)
	 * @param boolean $useJpg
	 */
	public function setUseJpg($useJpg) {
		$this->useJpg = $useJpg;
	}

	/**
	 * @return string
	 */
	public function getHash() {
		return $this->hash;
	}

	/**
	 * @param string $hash
	 */
	public function setHash($hash) {
		$this->hash = $hash;
	}

	/**
	 * Alias for parent::getFolder()
	 *
	 * @param string $directoryPath
	 * @return array returns an array with all files key: iconname, value: fileName
	 */
	public function getFolderByDirectory($directoryPath) {
		return parent::getFolder($directoryPath);
	}

	/**
	 * Generate sprite and css file, and take account of hash
	 *
	 * @param array $files
	 * @return array
	 */
	public function generateSpriteFromArray(array $files) {
		$result = parent::generateSpriteFromArray($files);

		if ($this->getHash()) {
			$result['spriteImage'] = PATH_site . $this->spriteFolder . $this->spriteName . '-' . $this->getHash() . '.png';
			$result['cssFile'] = PATH_site . $this->cssFolder . $this->spriteName . '-' . $this->getHash() . '.css';
		}

		if ($this->getUseJpg()) {
			$result['spriteImage'] = str_replace('.png', '.jpg', $result['spriteImage']);
		}

		unset($result['spriteGifImage'], $result['cssGif']);
		return $result;
	}


	protected function generateGraphic() {
		if ($this->getUseJpg()) {
			$this->generateGraphicJpg();
		} else {
			parent::generateGraphic();
		}

		if ($this->getHash()) {
			$originalImage = PATH_site . $this->spriteFolder . $this->spriteName . (($this->getUseJpg()) ? '.jpg' : '.png');
			$newImage = PATH_site . $this->spriteFolder . $this->spriteName . '-' . $this->getHash() . (($this->getUseJpg()) ? '.jpg' : '.png');
			rename($originalImage, $newImage);
		}
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

		$cssPath = $originalCssPath = PATH_site . $this->cssFolder . $this->spriteName . '.css';
		if ($this->getHash()) {
			$cssPath = PATH_site . $this->cssFolder . $this->spriteName . '-' . $this->getHash() . '.css';
			rename($originalCssPath, $cssPath);
		}

		$cssContent = file_get_contents($cssPath);

		if ($this->getHash()) {
			$cssContent = str_replace($this->spriteName . '.png', $this->spriteName . '-' . $this->getHash() . '.png', $cssContent);
		}
		if ($this->getUseJpg()) {
			$cssContent = str_replace('.png', '.jpg', $cssContent);
		}

		file_put_contents($cssPath, $cssContent);
	}
}
?>