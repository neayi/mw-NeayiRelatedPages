<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace NeayiRelatedPages;

use ApiQueryBase;
use ApiMain;
use ConfigFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Parser\ParserOutput;
use RequestContext;

use WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;

use SMW\DIWikiPage;
use SMW\DIProperty;
use SMW\StoreFactory;
use SMWQuery;
use SMWQueryResult;
use SMW\Query\Language\SomeProperty;
use SMW\Query\Language\ValueDescription;

class APIRelatedPages extends ApiQueryBase {

	/**
	 * Bump when memcache needs clearing
	 */
	private const CACHE_VERSION = 2;

	private const PREFIX = 'rp';

	/**
	 * @var array
	 */
	private $params;

	/**
	 * @var WANObjectCache
	 */
	private $cache;

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @var SMW\Store
	 */
	private $smwStore;

	private $categoriesPlurals = [];

	/**
	 * @param \ApiQuery $query API query module object
	 * @param string $moduleName Name of this query module
	 * @param ConfigFactory $configFactory
	 * @param WANObjectCache $cache
	 * @param LanguageConverterFactory $langConvFactory
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		$query,
		$moduleName,
		WANObjectCache $cache,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $query, $moduleName, self::PREFIX );
		$this->cache = $cache;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->smwStore = StoreFactory::getStore();

	}

	/**
	 * execute the API request
	 */
	public function execute() {
		$titles = $this->getPageSet()->getGoodPages();

        $apiResult = $this->getResult();

		try {
			$this->categoriesPlurals = $this->getCategoriesPlurals();

			foreach ($titles as $titleIdentity) {
				$relatedTitles = [];

				$title = Title::castFromPageIdentity($titleIdentity);

				$relatedTitles = $relatedTitles + $this->getTitlesThatHaveTheSameTags($title);

				$relatedTitles = $relatedTitles + $this->getTitlesWithTag($title);

				// let's add some more articles using direct links
				$relatedTitles = $relatedTitles + $this->getTitlesThatLinkToThis($title);
				$relatedTitles = $relatedTitles + $this->getTitlesThatLinkFromThis($title);

				unset($relatedTitles[$title->getArticleID()]);

				$countsByType = [];
				$sort = 0;

				// Sort the pages per type and make sure we don't have more than 10 of each
				foreach ($relatedTitles as $pageId => $title) {

					$subject = DIWikiPage::newFromTitle($title);

					$pageTypesValues = [];
					$pageTypes = $this->smwStore->getPropertyValues( $subject, DIProperty::newFromUserLabel('A un type de page') );

					if (empty($pageTypes))
						continue;

					foreach ($pageTypes as $valueObject)
						$pageTypesValues[] = $valueObject->getSortKey();

					$maxReached = false;

					foreach ($pageTypesValues as $pageType) {
						if (!isset($countsByType[$pageType]))
							$countsByType[$pageType] = 0;
						$countsByType[$pageType]++;

						if ($countsByType[$pageType] > 15)
							$maxReached = true;
					}

					if ($maxReached)
						continue;

					$r = [
						'Title' => $title->getText(),
						'URL' => $title->getPrefixedURL(),
						'Page types' => array_map(function($element) {
								return [$element, $this->categoriesPlurals[$element] ?? $element];
							}, $pageTypesValues),
						'ImageURL' => $this->getPageImageURL($subject),
						'SortIndex' => $sort++
					];

					$fit = $apiResult->addValue( [ 'query', 'pages', $pageId ], $this->getModuleName(), $r );
				}


				// Todo: manage overflow
				// if ( !$fit ) {
				// 	$this->setContinueEnumParameter( 'continue', $continue + $count - 1 );
				// 	break;
				// }

			}

		} catch (\Exception $e) {
			$this->addError( $e->getMessage() );
		}

	}

	private function getTitlesThatHaveTheSameTags(Title $title) {
		$relatedTitles = [];
		$relatedTitlesScores = [];

		// Start by getting the tags from the current title:
		$subject = DIWikiPage::newFromTitle($title);

		$propertiesToMatch = [  'A un mot-clé' => 6,
								'A comme agriculteur' => 10,
								'A un intervenant' => 10,
								'A un cahier des charges' => 3,
								'A une production' => 1,
								'A une caractéristique' => 3,
								'A un objectif' => 6,
								'A un sol' => 1,
								'Est dans le département' => 4];

		foreach ($propertiesToMatch as $aProperty => $weight) {
			$values = $this->smwStore->getPropertyValues( $subject, DIProperty::newFromUserLabel($aProperty) );
			foreach ($values as $aValue) {
				$valueTitle = $aValue->getTitle();

				$otherTitlesWithThisTag = $this->getTitlesWithProperty( $aProperty, $valueTitle, 300 );

				foreach ($otherTitlesWithThisTag as $pageId => $title) {
					$relatedTitles[$pageId] = $title;

					if (isset($relatedTitlesScores[$pageId]))
						$relatedTitlesScores[$pageId] = $relatedTitlesScores[$pageId] + $weight;
					else
						$relatedTitlesScores[$pageId] = $weight;
				}
			}
		}

		asort($relatedTitlesScores);
		$relatedTitlesScores = array_slice(array_reverse($relatedTitlesScores, true), 0, 50, true);

		foreach ($relatedTitlesScores as $pageId => $score)
			$relatedTitlesScores[$pageId] = $relatedTitles[$pageId];

		return $relatedTitlesScores;
	}

	private function getTitlesWithTag(Title $tag, $limit = 100) {
		return $this->getTitlesWithProperty('A un mot-clé', $tag, $limit);
	}

	private function getTitlesWithProperty( $aProperty, $valueTitle, $limit = 100 ) {
		$relatedTitles = [];

		$property = DIProperty::newFromUserLabel( $aProperty );
		$value = DIWikiPage::newFromTitle( $valueTitle );
		$description = new SomeProperty(
			$property,
			new ValueDescription($value)
		);

		$query = new \SMWQuery($description);
		$query->setLimit($limit); // Adjust as needed
		$query->sort = true;
		$query->sortkeys['Page ID'] = 'DESC';

		// Use SMW Query API to execute the query
		$queryResult = $this->smwStore->getQueryResult( $query );

		foreach ($queryResult->getResults() as $result) {
			$relatedTitle = $result->getTitle();
			$relatedTitles[$relatedTitle->getArticleID()] = $relatedTitle;
		}

		return $relatedTitles;
	}

	private function getTitlesThatLinkToThis(Title $title) {
		$relatedTitles = [];

		$toTitles = $title->getLinksTo();

		foreach ($toTitles as $relatedTitle) {
			$page = $this->wikiPageFactory->newFromTitle( $relatedTitle );

			if ($relatedTitle->isRedirect()) {
				$moreToTitles = $relatedTitle->getLinksTo(); // there shouldn't be a need to recurse, no double redirections
				foreach ($moreToTitles as $furtherTitle) {
					$relatedTitles[$furtherTitle->getArticleID()] = $furtherTitle;
				}
			} else {
				$relatedTitles[$relatedTitle->getArticleID()] = $relatedTitle;
			}
		}

		return $relatedTitles;
	}

	private function getTitlesThatLinkFromThis(Title $title) {
		$relatedTitles = [];
		$redirectStore = MediaWikiServices::getInstance()->getRedirectStore();

		$fromTitles = $title->getLinksFrom();
		foreach ($fromTitles as $relatedTitle) {
			$page = $this->wikiPageFactory->newFromTitle( $relatedTitle );

			if ($relatedTitle->isRedirect()) {
				$target = $redirectStore->getRedirectTarget($page);
				$targetTitle = Title::newFromLinkTarget($target);
				$relatedTitles[$targetTitle->getArticleID()] = $targetTitle;
			} else {
				$relatedTitles[$relatedTitle->getArticleID()] = $relatedTitle;
			}
		}

		return $relatedTitles;
	}

	private function getPageImageURL(DIWikiPage $subject) {
		$pageImages = $this->smwStore->getPropertyValues( $subject, DIProperty::newFromUserLabel('Page Image') );
		if (empty($pageImages))
			return '';

		$pageImage = $pageImages[0]->getSortKey();
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile($pageImage);
		if (!$file) {
			return '';
		}

		$thumb = $file->transform(['width' => 200]);
		return $thumb->getUrl();
	}

	/**
	 * @return array allowed parameters
	 */
	public function getAllowedParams() {
		return [

		];
	}

	/**
	 * @return array examples of the use of this API module
	 */
	public function getExamplesMessages() {
		return [
			'action=query&prop=' . $this->getModuleName() . '&titles=Agroforesterie' =>
			'neayirelatedpages-' . $this->getModuleName() . '-example'
		];
	}

	/**
	 * @return string indicates that this API module does not require a CSRF token
	 */
	public function needsToken() {
		return false;
	}

	private function getCategoriesPlurals() {
		$plurals = [];

		try {
			// expand the template TranslationArray
			$title = Title::newFromText('TranslationArray', NS_TEMPLATE);

			if ( !$title || !$title->exists() ) {
				die( "Page does not exist." );
			}

			// Step 2: Get the WikiPage object
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );

			// Step 3: Get the ParserOptions for the current user
			$parserOptions = \ParserOptions::newFromUser( RequestContext::getMain()->getUser() );

			// Step 4: Get the parsed output
			$parserOutput = $wikiPage->getParserOutput( $parserOptions );

			// Step 5: Get the HTML text
			$html = $parserOutput->getText();

			// Remove outer wrapper <div class="mw-parser-output">
			$html = preg_replace( '/<div class="mw-parser-output">(.*)<\/div>/s', '$1', $html );

			// Optionally: Remove surrounding <p> tags if present
			$html = preg_replace( '/^<p>(.*)<\/p>$/s', '$1', $html );

			$categories = json_decode($html, true);

			foreach ($categories as $aCat)
				$plurals[$aCat['singular']] = $aCat['plural'];

		} catch (\Throwable $th) {
		}

		return $plurals;
	}
}
