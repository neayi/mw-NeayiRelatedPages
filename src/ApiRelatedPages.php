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
use Title;
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
	}

	/**
	 * execute the API request
	 */
	public function execute() {
		$titles = $this->getPageSet()->getGoodPages();

        $apiResult = $this->getResult();
		$redirectStore = MediaWikiServices::getInstance()->getRedirectStore();

		$smwStore = StoreFactory::getStore();
		
		try {
			foreach ($titles as $titleIdentity) {
				$relatedTitles = [];

				$title = Title::castFromPageIdentity($titleIdentity);
				
				$property = DIProperty::newFromUserLabel('A un mot-clé');
				$value = DIWikiPage::newFromTitle( $title );
				$description = new SomeProperty(
					$property,
					new ValueDescription($value)
				);

				$query = new \SMWQuery($description);
				$query->setLimit(100); // Adjust as needed
				$query->sort = true;
				$query->sortkeys['Number of page views'] = 'DESC';

				// Use SMW Query API to execute the query
				$queryResult = $smwStore->getQueryResult( $query );
				
				foreach ($queryResult->getResults() as $result) {
					$relatedTitle = $result->getTitle();
					$relatedTitles[$relatedTitle->getArticleID()] = $relatedTitle;
				}

				if (count($relatedTitles) < 10) {
					// There's less than 10 pages that have the current page as a tag,
					// let's add some more using direct links

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
				}
				
				$countsByType = [];
				$sort = 0;

				foreach ($relatedTitles as $pageId => $title) {
					
					$subject = DIWikiPage::newFromTitle($title);

					$pageTypesValues = [];
					$pageTypes = $smwStore->getPropertyValues( $subject, DIProperty::newFromUserLabel('A un type de page') );

					if (empty($pageTypes))
						continue;

					foreach ($pageTypes as $valueObject) 
						$pageTypesValues[] = $valueObject->getSortKey();

					$maxReached = false;

					foreach ($pageTypesValues as $pageType) {
						if (!isset($countsByType[$pageType]))
							$countsByType[$pageType] = 0;
						$countsByType[$pageType]++;

						if ($countsByType[$pageType] > 10)
							$maxReached = true;
					}
					
					if ($maxReached)
						continue;

					$imageUrl = '';
					$pageImages = $smwStore->getPropertyValues( $subject, DIProperty::newFromUserLabel('Page Image') );
					if (!empty($pageImages)) {
						$pageImage = $pageImages[0]->getSortKey();
						$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile($pageImage);
						$thumb = $file->transform(['width' => 200]);
						$imageUrl = $thumb->getUrl();
					}

					$r = [
						'Title' => $title->getText(),
						'URL' => $title->getPrefixedURL(),
						'A un type de page' => $pageTypesValues,
						'ImageURL' => $imageUrl,
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
}
