var NeayiRelatedPages_controller = ( function () {
	'use strict';

	return {

		initialize: function () {

			console.log('Neayi Related Pages');
			
			var api = new mw.Api();

			//https://wiki.dev.tripleperformance.fr/api.php?action=expandtemplates&text=%7B%7BTranslationArray%7D%7D&prop=wikitext
			api.get( {
				'action': 'expandtemplates',
				'prop': 'wikitext',
				'text': '{{TranslationArray}}'
			} )
			.done( function ( data ) {
				let categories = JSON.parse(data.expandtemplates.wikitext);
				console.log(categories);

				api.get( {
					'action': 'query',
					'prop': 'relatedpages',
					'titles': mw.config.get('wgPageName')
				} )
				.done( function ( data ) {
	
					console.log(data);
	
					let relatedPagesDiv = $($(".neayi-related-pages")[0]);
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
	
					categories.forEach(aCat => {
						let aType = aCat.singular;
						let aTypePlural = aCat.plural;

						if (pageTypes[aType] == undefined)
							return;

						let pages = pageTypes[aType];
						
						let searchURL = "/index.php?title=Search&filters=A+un+type+de+page%5E%5E"+encodeURIComponent(aType)+"&term=" + encodeURIComponent(mw.config.get("wgTitle"));
						let count = pages.length;
						if (count == 10)
							count = '10+';

						let html = `<div class="row navigation-not-searchable searchaux smw-no-index my-1">
								<div class="col-md-8 text-md-left text-center align-self-center"><h3 class="m-0"><span class="mw-headline">${aTypePlural}</span></h3></div>
								<div class="col-md-4 text-md-right text-center align-self-center"><span class="btn btn-dark-green text-nowrap furtherresults"><a href="${searchURL}" title="Search">${mw.msg('neayirelatedpages-seeall', count)}</a></span></div>
							</div>
							<div class="row portal-store-results searchaux">`
	
						pages.slice(0, 6).forEach(aPage => {
							let title = aPage.Title;
							let imageURL = aPage.ImageURL;
							let URL = aPage.URL;
		
							let imageNode = '';
							if (imageURL && imageURL.length > 0) {
								imageNode = `<a href="/wiki/${URL}"
									title="${title}"><img src="${imageURL}" decoding="async" class="card-img""></a>`;
							}
		
							html += `<div class="col-xl-6 mb-lg-0 mb-3">
					<div class="very-small-card card mb-3">
						<div class="row no-gutters">
							<div class="col-md-3 image-col">${imageNode}</div>
							<div class="col-md-9">
								<div class="card-body px-2 py-1">
									<p class="mb-0"><span class="badge badge-light">${aType}</span></p>
									<p class="card-text"><a class="stretched-link" href="/wiki/${URL}">${title}</a></p>
								</div>
							</div>
						</div>
					</div>
				</div>`;
						});		
						
						html += '</div>';
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
