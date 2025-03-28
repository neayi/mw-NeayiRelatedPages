<?php
/**
 * Hooks for NeayiRelatedPages extension
 *
 * @file
 * @ingroup Extensions
 */
	
use Parser;
 
class NeayiRelatedPagesHooks {

	/**
	 * Implements BeforePageDisplay hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Initializes variables to be passed to JavaScript.
	 *
	 * @param OutputPage $output OutputPage object
	 * @return bool continue checking hooks
	 */
	public static function initializeJS(
		OutputPage $output
	) {
		$output->addModules( ['ext.neayiRelatedPages'] );

	}
}
