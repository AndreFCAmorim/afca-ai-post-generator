/* global aipgData, jQuery */
(function ($) {
	'use strict';

	// ── Provider card switching ───────────────────────────────────────────────
	$('.aipg-provider-card').on('click', function () {
		const $card = $(this);
		const provider = $card.data('provider');

		// Update active card highlight
		$('.aipg-provider-card').removeClass('is-active');
		$card.addClass('is-active');

		// Show matching config panel, hide others
		$('.aipg-provider-config').hide();
		$('.aipg-provider-config[data-provider="' + provider + '"]').show();
	});

	// ── Show/hide API key fields ──────────────────────────────────────────────
	$(document).on('click', '.aipg-toggle-key', function () {
		const $btn = $(this);
		const $input = $btn.closest('.aipg-input-group').find('.aipg-api-key-input');
		const isPass = $input.attr('type') === 'password';
		$input.attr('type', isPass ? 'text' : 'password');
		$btn.text(isPass ? '🙈' : '👁');
	});

	// ── Test API connection (per-provider button) ─────────────────────────────
	$(document).on('click', '.aipg-test-api-btn', function () {
		const $btn = $(this);
		const $panel = $btn.closest('.aipg-provider-config');
		const $result = $panel.find('.aipg-inline-result');

		$btn.prop('disabled', true);
		$result.removeClass('success error').addClass('loading').text(aipgData.strings.testing);

		$.post(aipgData.ajaxUrl, {
			action: 'aipg_test_api',
			nonce: aipgData.nonce,
		})
			.done(function (response) {
				if (response.success) {
					$result.removeClass('loading error').addClass('success')
						.text('✓ ' + response.data.message);
				} else {
					$result.removeClass('loading success').addClass('error')
						.text('✗ ' + (response.data.message || aipgData.strings.error));
				}
			})
			.fail(function () {
				$result.removeClass('loading success').addClass('error').text('✗ ' + aipgData.strings.error);
			})
			.always(function () { $btn.prop('disabled', false); });
	});

	// ── Generate Now ─────────────────────────────────────────────────────────
	$('#aipg-generate-now').on('click', function () {
		const $btn = $(this);
		const $result = $('#aipg-generate-result');

		$btn.prop('disabled', true);
		$result.removeClass('success error').show()
			.html('<em>' + aipgData.strings.generating + '</em>');

		$.post(aipgData.ajaxUrl, {
			action: 'aipg_generate_now',
			nonce: aipgData.nonce,
		})
			.done(function (response) {
				if (response.success) {
					const data = response.data;
					const results = data.results || [];
					let html = '<strong>' + escHtml(data.summary) + '</strong>';
					if (results.length) {
						html += '<ul>';
						results.forEach(function (r) {
							const icon = r.success ? '✓' : '✗';
							html += '<li>' + icon + ' ' + escHtml(r.message);
							if (r.success && r.post_id) {
								html += ' <a href="' + escHtml(getEditUrl(r.post_id)) + '" target="_blank">Edit post →</a>';
							}
							html += '</li>';
						});
						html += '</ul>';
					}
					const anySuccess = results.some(r => r.success);
					$result.removeClass('error').addClass(anySuccess ? 'success' : 'error').html(html);
				} else {
					$result.removeClass('success').addClass('error')
						.html('✗ ' + escHtml(response.data?.message || aipgData.strings.error));
				}
			})
			.fail(function () {
				$result.removeClass('success').addClass('error').html('✗ ' + escHtml(aipgData.strings.error));
			})
			.always(function () { $btn.prop('disabled', false); });
	});

	// ── Helpers ───────────────────────────────────────────────────────────────
	function escHtml(str) {
		return $('<div>').text(str || '').html();
	}
	function getEditUrl(postId) {
		return aipgData.ajaxUrl.replace('admin-ajax.php', 'post.php') + '?post=' + postId + '&action=edit';
	}

})(jQuery);
