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
	 * Register parser hooks to add the piwigo keyword
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @see https://www.mediawiki.org/wiki/Manual:Parser_functions
	 * @param Parser $parser
	 * @throws \MWException
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		// Find <NeayiRelatedPages /> instances on the page
		$parser->setHook( 'NeayiRelatedPages', [ self::class, 'showRelatedPages' ] );
	}

	public static function showRelatedPages( $input, array $args, Parser $parser, PPFrame $frame) {
		$parser->getOutput()->addModules( ['ext.neayiRelatedPages'] );
		return '<div class="neayi-related-pages"></div>';
	}
}
