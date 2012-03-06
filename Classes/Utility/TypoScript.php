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
 * Utilities to manage and convert Typoscript Code
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_Sprite_Utility_TypoScript {
	/**
	 * Removes all trailing dots recursively from TS settings array
	 *
	 * Extbase converts the "classical" TypoScript (with trailing dot) to a format without trailing dot,
	 * to be more future-proof and not to have any conflicts with Fluid object accessor syntax.
	 *
	 * @param array $settings The settings array
	 * @return void
	 *
	 * @see Tx_Extbase_Utility_TypoScript::convertTypoScriptArrayToPlainArray()
	 */
	static public function convertTypoScriptArrayToPlainArray(array $settings) {
		foreach ($settings as $key => &$value) {
			if(substr($key, -1) === '.') {
				$keyWithoutDot = substr($key, 0, -1);
				$hasNodeWithoutDot = array_key_exists($keyWithoutDot, $settings);
				$typoScriptNodeValue = $hasNodeWithoutDot ? $settings[$keyWithoutDot] : NULL;
				if(is_array($value)) {
					$settings[$keyWithoutDot] = self::convertTypoScriptArrayToPlainArray($value);
					if(!is_null($typoScriptNodeValue)) {
						$settings[$keyWithoutDot]['_typoScriptNodeValue']  = $typoScriptNodeValue;
					}
					unset($settings[$key]);
				} else {
					$settings[$keyWithoutDot] = NULL;
				}
			}
		}
		return $settings;
	}

	/**
	 * Formats a given array with typoscript syntax, recursively. After the
	 * transformation it can be rendered with cObjGetSingle.
	 *
	 * Example:
	 * Before: $array['level1']['level2']['finalLevel'] = 'hello kitty'
	 * After:  $array['level1.']['level2.']['finalLevel'] = 'hello kitty'
	 *		   $array['level1'] = 'TEXT'
	 *
	 * @param array $configuration Array to make renderable
	 * @return array The renderable settings
	 */
	static public function makeConfigurationArrayRenderable(array $configuration) {
		$dottedConfiguration = array();
		foreach ($configuration as $key => $value) {
			if (is_array($value)) {
				if (array_key_exists('_typoScriptNodeValue', $value)) {
					$dottedConfiguration[$key] = $value['_typoScriptNodeValue'];
				}
				$dottedConfiguration[$key . '.'] = self::makeConfigurationArrayRenderable($value);
			} else {
				$dottedConfiguration[$key] = $value;
			}
		}
		return $dottedConfiguration;
	}

	/**
	 * Renders a given typoscript configuration and returns the whole array with calculated values.
	 *
	 * @param array $typoscript
	 * @return array The configuration array with the rendered typoscript
	 */
	static public function renderTypoScript(array $typoscript) {
		$typoscript = self::convertTypoScriptArrayToPlainArray($typoscript);
		$typoscript = self::makeConfigurationArrayRenderable($typoscript);

		/** @var $contentObject tslib_cObj */
		$contentObject = t3lib_div::makeInstance('tslib_cObj');

		$result = array();
		foreach ($typoscript as $key => $value) {
			if (substr($key, -1) === '.') {
				$keyWithoutDot = substr($key, 0, -1);
				if (array_key_exists($keyWithoutDot, $typoscript)) {
					$result[$keyWithoutDot] = $contentObject->cObjGetSingle(
						$typoscript[$keyWithoutDot],
						$value
					);
				} else {
					$result[$keyWithoutDot] = self::renderTypoScript($value);
				}
			} else {
				if (!array_key_exists($key . '.', $typoscript)) {
					$result[$key] = $value;
				}
			}
		}
		return $result;
	}



//	/**
//	 * Returns an array with Typoscript the old way (with dot).
//	 *
//	 * Extbase converts the "classical" TypoScript (with trailing dot) to a format without trailing dot,
//	 * to be more future-proof and not to have any conflicts with Fluid object accessor syntax.
//	 * However, if you want to call legacy TypoScript objects, you somehow need the "old" syntax (because this is what TYPO3 is used to).
//	 * With this method, you can convert the extbase TypoScript to classical TYPO3 TypoScript which is understood by the rest of TYPO3.
//	 *
//	 * @param array $plainArray An Typoscript Array with Extbase Syntax (without dot but with _typoScriptNodeValue)
//	 * @return array array with Typoscript as usual (with dot)
//	 * @api
//	 */
//	static public function convertPlainArrayToTypoScriptArray($plainArray) {
//		$typoScriptArray = array();
//		if (is_array($plainArray)) {
//			foreach ($plainArray as $key => $value) {
//				if (is_array($value)) {
//					if (isset($value['_typoScriptNodeValue'])) {
//						$typoScriptArray[$key] = $value['_typoScriptNodeValue'];
//						unset($value['_typoScriptNodeValue']);
//					}
//					$typoScriptArray[$key.'.'] = self::convertPlainArrayToTypoScriptArray($value);
//				} else {
//					$typoScriptArray[$key] = $value;
//				}
//			}
//		}
//		return $typoScriptArray;
//	}
}
?>