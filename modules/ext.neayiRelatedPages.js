var NeayiRelatedPages_controller = ( function () {
	'use strict';

	return {

		initialize: function () {
			
			var pageId = mw.config.get('wgArticleId');
			if (pageId == 0)
				return;

			
			var api = new mw.Api();

			//https://wiki.dev.tripleperformance.fr/api.php?action=expandtemplates&text=%7B%7BTranslationArray%7D%7D&prop=wikitext
			api.get( {
				'action': 'expandtemplates',
				'prop': 'wikitext',
				'text': '{{TranslationArray}}'
			} )
			.done( function ( data ) {
				let categories = JSON.parse(data.expandtemplates.wikitext);

				api.get( {
					'action': 'query',
					'prop': 'relatedpages',
					'titles': mw.config.get('wgPageName')
				} )
				.done( function ( data ) {
	
					let relatedPagesDiv = $(".related-pages");

					let pages = Object.values(data.query.pages);
	
					pages.sort((a, b) => {
						if (a.relatedpages == undefined || b.relatedpages == undefined)
							return 0;
						
						return a.relatedpages.SortIndex < b.relatedpages.SortIndex ? -1 : 1;
					});

					let pageTypes = {};
	
					pages.forEach(aPage => {
						if (!aPage['relatedpages'])
							return;

						let pageType = aPage['relatedpages']['A un type de page'];
	
						pageType.forEach(aType => {
							if (pageTypes[aType] == undefined)
								pageTypes[aType] = new Array();
	
							pageTypes[aType].push(aPage['relatedpages']);
						});
	
					});
	

					let relatedTags = [];

					categories.forEach(aCat => {
						let aType = aCat.singular;
						let aTypePlural = aCat.plural;

						if (pageTypes[aType] == undefined)
							return;

						relatedTags.push(`<a data-target="${aType}" class="btn btn-light-green text-white related-tag">${aTypePlural}</a>`);
					});

					relatedPagesDiv.append(`<div class="related-tags d-flex">
			<div class="related-tags-scroll-left"><a>&lt;</a></div>
			<div class="related-tags-scrollable">
				<a data-target="" class="btn btn-dark-green text-white related-tag">${mw.msg('neayirelatedpages-all')}</a>${relatedTags.join('')}
			</div>
			<div class="related-tags-scroll-right"><a>&gt;</a></div>
		</div>`);

					$('.related-tag').on('click', function() {
						let aType = $(this).data('target');

						if (aType.length > 0) {
							let typeclass = '.related-page-' + aType.toLowerCase().replace(' ', '-').replace(/[^0-9a-z]/g, '');
							$('.very-small-card').hide();
							$('.very-small-card' + typeclass).show();
						}						
						else
							$('.very-small-card').show();
					});

					$('.related-tags-scroll-right').on('click', function() {
						let scrollArea = $(this).siblings(".related-tags-scrollable");
						let clientWidth = scrollArea[0].clientWidth;
						let newScrollLeft = scrollArea.scrollLeft() + clientWidth / 2;
						scrollArea.animate({ scrollLeft: newScrollLeft}, 500);
					});

					$('.related-tags-scroll-left').on('click', function() {
						let scrollArea = $(this).siblings(".related-tags-scrollable");
						let clientWidth = scrollArea[0].clientWidth;

						let newScrollLeft = scrollArea.scrollLeft() - clientWidth / 2;
						scrollArea.animate({ scrollLeft: newScrollLeft}, 500);
					});

					$('.related-tags-scrollable').on('scroll', function() {
						let scrollArea = $(this);
						let newScrollLeft = scrollArea.scrollLeft();

						if (newScrollLeft > 0)
							$(this).siblings('.related-tags-scroll-left').show();
						else
							$(this).siblings('.related-tags-scroll-left').hide();

						if (scrollArea[0].scrollWidth - scrollArea[0].clientWidth <= newScrollLeft + 5)
							$(this).siblings('.related-tags-scroll-right').hide();
						else
							$(this).siblings('.related-tags-scroll-right').show();
					});

					categories.forEach(aCat => {
						let aType = aCat.singular;
						let aTypePlural = aCat.plural;

						if (pageTypes[aType] == undefined)
							return;

						let pages = pageTypes[aType];
						
						let html = '';
	
						pages.slice(0, 6).forEach(aPage => {
							let title = aPage.Title;
							let imageURL = aPage.ImageURL;
							let URL = aPage.URL;
		
							let imageNode = '';
							if (imageURL && imageURL.length > 0) {
								imageNode = `<a href="/wiki/${URL}"
									title="${title}"><img src="${imageURL}" decoding="async" class="card-img""></a>`;
							}
		
							let typeclass = 'related-page-' + aType.toLowerCase().replace(' ', '-').replace(/[^0-9a-z]/g, '');

							html += `<div class="very-small-card card mb-3 ${typeclass}">
						<div class="row no-gutters">
							<div class="col-4 image-col">${imageNode}</div>
							<div class="col-8">
								<div class="card-body px-2 py-1">
									<p class="card-text mb-0"><a class="stretched-link" href="/wiki/${URL}">${title}</a></p>
									<p class="mb-0"><span class="badge badge-light">${aType}</span></p>
								</div>
							</div>
						</div>
					</div>`;
						});		
						
						relatedPagesDiv.append(html)

					});

					Object.keys(pageTypes).forEach(aType => {
						
					});
				});
			});

		}
	};
}());

window.NeayiRelatedPagesController = NeayiRelatedPages_controller;

(function () {
	$(document)
		.ready(function () {
			mw.loader.using('mediawiki.api').then(function() {
				window.NeayiRelatedPagesController.initialize();
			});
		});
}());
