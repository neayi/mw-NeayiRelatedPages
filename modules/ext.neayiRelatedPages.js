var NeayiRelatedPages_controller = ( function () {
	'use strict';

	function escapeHtml(text) {
		return String(text).replace(/[&<>"']/g, c => ({
			'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
		})[c]);
	}

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

				let pages = Object.values(data.query.pages).filter((page) => page.relatedpages != undefined);

				pages.sort((a, b) => {
					if (a.relatedpages.Priority != b.relatedpages.Priority)
					 	return b.relatedpages.Priority - a.relatedpages.Priority;

					return a.relatedpages.SortIndex - b.relatedpages.SortIndex;
				});

				// Collect the page types (in order of appearance) and count the pages of each type
				let pageTypes = [];
				let typeLabels = {};
				let typeCounts = {};

				pages.forEach(aPage => {
					aPage['relatedpages']['Page types'].forEach(aType => {
						if (typeCounts[aType[0]] === undefined) {
							pageTypes.push(aType[0]);
							typeLabels[aType[0]] = aType[1];
							typeCounts[aType[0]] = 0;
						}
						typeCounts[aType[0]]++;
					});
				});

				// Only keep the 5 types with the most pages (stable sort: ties keep their order of appearance)
				pageTypes = pageTypes
					.slice()
					.sort((a, b) => typeCounts[b] - typeCounts[a])
					.slice(0, 5);

				let relatedTags = pageTypes.map(aType => `<button type="button" class="btn related-tag" aria-pressed="false" data-target="${escapeHtml(aType)}">${escapeHtml(typeLabels[aType])} <span class="related-tag-count">${typeCounts[aType]}</span></button>`);

				relatedPagesDiv.append(`<div class="related-tags">
		<button type="button" class="btn related-tag related-tag-all active" aria-pressed="true" data-target="">${escapeHtml(mw.msg('neayirelatedpages-all'))} <span class="related-tag-count">${pages.length}</span></button>${relatedTags.join('')}
	</div>`);

				// Several types can be selected at once: a card is shown if it has at least one of the selected types.
				// When no type is selected, "All" is active and every card is shown.
				relatedPagesDiv.on('click', '.related-tag', function() {
					let tagsDiv = $(this).closest('.related-tags');
					let aType = $(this).attr('data-target');

					if (aType === '') {
						tagsDiv.find('.related-tag').removeClass('active').attr('aria-pressed', 'false');
					} else {
						let isActive = !$(this).hasClass('active');
						$(this).toggleClass('active', isActive).attr('aria-pressed', isActive ? 'true' : 'false');
					}

					let selectedTypes = tagsDiv.find('.related-tag.active').not('.related-tag-all')
						.map(function() { return $(this).attr('data-target'); }).get();

					tagsDiv.find('.related-tag-all')
						.toggleClass('active', selectedTypes.length === 0)
						.attr('aria-pressed', selectedTypes.length === 0 ? 'true' : 'false');

					tagsDiv.parent().find('.very-small-card').each(function() {
						let cardTypes = $(this).data('types') || [];
						let visible = selectedTypes.length === 0 ||
							cardTypes.some(t => selectedTypes.indexOf(t) !== -1);
						$(this).toggle(visible);
					});
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
					let types = escapeHtml(JSON.stringify(aPage['relatedpages']['Page types'].map(type => type[0])));

					html = `<div class="very-small-card card mb-3 ${typeclass}" data-types="${types}" ${hide}>
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
