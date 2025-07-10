var NeayiRelatedPages_controller = ( function () {
	'use strict';

	return {

		initialize: function () {

			var pageId = mw.config.get('wgArticleId');
			if (pageId == 0)
				return;


			var api = new mw.Api();

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

				let pageTypes = [];
				let relatedTags = [];

				pages.forEach(aPage => {
					if (!aPage['relatedpages'])
						return;

					let pageType = aPage['relatedpages']['Page types'];

					pageType.forEach(aType => {
						if (pageTypes.indexOf(aType[0]) == -1) {
							pageTypes.push(aType[0]);
							relatedTags.push(`<a data-target="${aType[0]}" class="btn related-tag">${aType[1]}</a>`);
						}
					});
				});

				relatedPagesDiv.append(`<div class="related-tags d-flex">
		<div class="related-tags-scroll-left"><a>&lt;</a></div>
		<div class="related-tags-scrollable">
			<a data-target="" class="btn related-tag active">${mw.msg('neayirelatedpages-all')}</a>${relatedTags.join('')}
		</div>
		<div class="related-tags-scroll-right"><a>&gt;</a></div>
	</div>`);

				$('.related-tag').on('click', function() {
					let aType = $(this).data('target');

					$('.related-tag').removeClass('active');
					$(this).addClass('active');

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

				// Get the height of the .rightSide div
				let rightSideHeight = $('.rightSide').height();


				pages.forEach(aPage => {
					if (!aPage['relatedpages'])
						return;

					let hide = '';
					let newRightSideHeight = $('.rightSide').height();
					if (newRightSideHeight > rightSideHeight) {
						hide = 'style="display: none;"';
					};

					let html = '';

					let title = aPage['relatedpages'].Title;
					let imageURL = aPage['relatedpages'].ImageURL;
					let URL = aPage['relatedpages'].URL;

					let imageNode = '';
					if (imageURL && imageURL.length > 0) {
						imageNode = `<a href="/wiki/${URL}"
							title="${title}"><img src="${imageURL}" decoding="async" class="card-img""></a>`;
					}

					let typeclass = aPage['relatedpages']['Page types'].map(type => {
						return 'related-page-' + type[0].toLowerCase().replace(' ', '-').replace(/[^0-9a-z]/g, '');
					}).join(' ');

					let aType = aPage['relatedpages']['Page types'][0][0];

					html = `<div class="very-small-card card mb-3 ${typeclass}"  ${hide}>
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

					relatedPagesDiv.append(html)

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
