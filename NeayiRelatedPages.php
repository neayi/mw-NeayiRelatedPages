<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'NeayiRelatedPages' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['NeayiRelatedPages'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['NeayiRelatedPagesAlias'] = __DIR__ . '/NeayiRelatedPages.i18n.alias.php';
	$wgExtensionMessagesFiles['NeayiRelatedPagesMagic'] = __DIR__ . '/NeayiRelatedPages.i18n.magic.php';
	wfWarn(
		'Deprecated PHP entry point used for NeayiRelatedPages extension. Please use wfLoadExtension ' .
		'instead, see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return true;
} else {
	die( 'This version of the NeayiRelatedPages extension requires MediaWiki 1.25+' );
}
