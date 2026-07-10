/**
 * Dante Assistant — Dashboard chat UI.
 *
 * Talks to the /dante/v1/assistant REST routes. No build step; uses wp.apiFetch.
 * Three zones: the chat log, a "waiting for approval" review panel, and a
 * "recent changes" undo list.
 */
( function () {
	'use strict';

	var cfg = window.danteAssistant || {};
	var root = document.querySelector( '.dante-assistant' );
	if ( ! root ) {
		return;
	}

	var api = window.wp && window.wp.apiFetch;
	if ( api && cfg.nonce ) {
		api.use( api.createNonceMiddleware( cfg.nonce ) );
	}

	// Lift the widget out of the narrow dashboard column into its own full-width
	// row, so previews and the chat have room to breathe.
	( function makeFullWidth() {
		var box = document.getElementById( 'dante_assistant_widget' );
		var wrap = document.getElementById( 'dashboard-widgets-wrap' );
		if ( ! box || ! wrap || box.closest( '.dante-assistant-fullrow' ) ) {
			return;
		}
		var row = document.createElement( 'div' );
		row.className = 'dante-assistant-fullrow';
		wrap.insertBefore( row, wrap.firstChild );
		row.appendChild( box );
	}() );

	var logEl = root.querySelector( '.dante-assistant__log' );
	var newsletterEl = root.querySelector( '.dante-assistant__newsletter' );
	var reviewEl = root.querySelector( '.dante-assistant__review' );
	var historyEl = root.querySelector( '.dante-assistant__history' );
	var formEl = root.querySelector( '.dante-assistant__form' );
	var inputEl = root.querySelector( '.dante-assistant__input' );
	var chips = root.querySelectorAll( '.dante-assistant__chip' );
	var photoBtn = root.querySelector( '.dante-assistant__photo' );
	var fileEl = root.querySelector( '.dante-assistant__file' );
	var thumbEl = root.querySelector( '.dante-assistant__thumb' );

	// Visible conversation turns sent back to the server for context.
	var history = [];
	// A photo the user attached, applied to the next event they create/edit.
	var attachmentId = 0;

	// --- helpers -----------------------------------------------------------

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) { n.className = cls; }
		if ( text != null ) { n.textContent = text; }
		return n;
	}

	function request( path, options ) {
		if ( api ) {
			return api( Object.assign( { url: cfg.root + path }, options || {} ) );
		}
		// Fallback: plain fetch.
		var opts = Object.assign(
			{ headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce } },
			options || {}
		);
		if ( opts.data ) { opts.body = JSON.stringify( opts.data ); delete opts.data; opts.method = opts.method || 'POST'; }
		return fetch( cfg.root + path, opts ).then( function ( r ) { return r.json(); } );
	}

	function bubble( who, text ) {
		var b = el( 'div', 'dante-msg dante-msg--' + who );
		b.appendChild( el( 'div', 'dante-msg__text', text ) );
		logEl.appendChild( b );
		logEl.scrollTop = logEl.scrollHeight;
		return b;
	}

	function actionCard( action ) {
		var card = el( 'div', 'dante-card' );
		card.appendChild( el( 'div', 'dante-card__summary', '✓ ' + ( action.summary || 'Done' ) ) );
		if ( action.preview_url ) {
			var a = el( 'a', 'dante-card__link', 'See preview →' );
			a.href = action.preview_url;
			a.target = '_blank';
			card.appendChild( a );
		}
		logEl.appendChild( card );
		logEl.scrollTop = logEl.scrollHeight;
	}

	// --- review panel (approve/discard all) --------------------------------

	// A rich, in-dashboard preview of one pending event with its own Publish and
	// See-preview controls — so the editor is never required.
	function previewCard( item, changesetId ) {
		var card = el( 'div', 'dante-preview' );

		if ( item.thumb ) {
			var img = el( 'img', 'dante-preview__img' );
			img.src = item.thumb;
			img.alt = '';
			card.appendChild( img );
		}

		var body = el( 'div', 'dante-preview__body' );
		body.appendChild( el( 'div', 'dante-preview__title', item.title ) );

		var meta = [ item.date_label, item.time ].filter( Boolean ).join( '  ·  ' );
		if ( meta ) { body.appendChild( el( 'div', 'dante-preview__meta', meta ) ); }
		if ( item.location ) { body.appendChild( el( 'div', 'dante-preview__loc', '📍 ' + item.location ) ); }
		if ( item.excerpt ) { body.appendChild( el( 'div', 'dante-preview__desc', item.excerpt ) ); }

		var actions = el( 'div', 'dante-preview__actions' );
		var publish = el( 'button', 'button button-primary button-small', 'Publish' );
		actions.appendChild( publish );
		if ( item.preview_url ) {
			var see = el( 'a', 'dante-preview__see', 'See preview →' );
			see.href = item.preview_url;
			see.target = '_blank';
			actions.appendChild( see );
		}
		body.appendChild( actions );
		card.appendChild( body );

		publish.addEventListener( 'click', function () {
			publish.disabled = true;
			publish.textContent = 'Publishing…';
			request( '/publish-item', { method: 'POST', data: { changeset_id: changesetId, post_id: item.post_id } } )
				.then( function ( res ) {
					if ( res.error ) { window.alert( res.error ); publish.disabled = false; publish.textContent = 'Publish'; return; }
					bubble( 'bot', res.message || 'Published.' );
					renderReview( res.pending );
					renderHistory( res.history );
				} );
		} );

		return card;
	}

	function renderReview( pending ) {
		reviewEl.innerHTML = '';
		if ( ! pending || ! pending.items || ! pending.items.length ) {
			reviewEl.hidden = true;
			return;
		}
		reviewEl.hidden = false;

		reviewEl.appendChild( el( 'h4', 'dante-review__title', 'Waiting for your approval' ) );

		pending.items.forEach( function ( item ) {
			reviewEl.appendChild( previewCard( item, pending.changeset_id ) );
		} );

		var actions = el( 'div', 'dante-review__actions' );
		var approve = el( 'button', 'button button-primary', 'Publish all' );
		var discard = el( 'button', 'button', 'Discard all' );
		if ( pending.items.length < 2 ) {
			approve.hidden = true; // one item already has its own Publish button.
		}
		actions.appendChild( approve );
		actions.appendChild( discard );
		reviewEl.appendChild( actions );

		approve.addEventListener( 'click', function () {
			approve.disabled = discard.disabled = true;
			request( '/approve', { method: 'POST', data: { changeset_id: pending.changeset_id } } )
				.then( function ( res ) {
					bubble( 'bot', res.message || 'Published.' );
					renderReview( res.pending );
					renderHistory( res.history );
				} );
		} );

		discard.addEventListener( 'click', function () {
			if ( ! window.confirm( 'Discard these drafts? This cannot be undone.' ) ) { return; }
			approve.disabled = discard.disabled = true;
			request( '/discard', { method: 'POST', data: { changeset_id: pending.changeset_id } } )
				.then( function ( res ) {
					bubble( 'bot', res.message || 'Discarded.' );
					renderReview( res.pending );
				} );
		} );
	}

	// --- history (undo) ----------------------------------------------------

	function renderHistory( items ) {
		historyEl.innerHTML = '';
		if ( ! items || ! items.length ) {
			return;
		}
		historyEl.appendChild( el( 'h4', 'dante-history__title', 'Recent changes' ) );
		items.forEach( function ( h ) {
			var row = el( 'div', 'dante-history__row' );
			row.appendChild( el( 'span', 'dante-history__label', h.label ) );
			var undo = el( 'button', 'button-link dante-history__undo', 'Undo' );
			undo.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Undo: ' + h.label + '?' ) ) { return; }
				undo.disabled = true;
				request( '/undo', { method: 'POST', data: { changeset_id: h.id } } )
					.then( function ( res ) {
						if ( res.error ) { window.alert( res.error ); undo.disabled = false; return; }
						bubble( 'bot', res.message || 'Undone.' );
						renderHistory( res.history );
					} );
			} );
			row.appendChild( undo );
			historyEl.appendChild( row );
		} );
	}

	// --- newsletter card ---------------------------------------------------

	function labeledRow( labelText ) {
		var row = el( 'div', 'dante-nl-card__row' );
		row.appendChild( el( 'label', 'dante-nl-card__label', labelText ) );
		return row;
	}

	function showNewsletter( nl, existingEl, openPreview ) {
		var card = buildNewsletterCard( nl, openPreview );
		if ( existingEl && existingEl.parentNode ) {
			existingEl.parentNode.replaceChild( card, existingEl );
		} else {
			// One active newsletter at a time; replace any prior card.
			newsletterEl.innerHTML = '';
			newsletterEl.appendChild( card );
		}
		card.scrollIntoView( { block: 'nearest' } );
		return card;
	}

	function buildNewsletterCard( nl, openPreview ) {
		var card = el( 'div', 'dante-nl-card' );
		card.appendChild( el( 'div', 'dante-nl-card__title', '✉️ Newsletter' ) );
		card.appendChild( el( 'div', 'dante-nl-card__subject', 'Subject: ' + nl.subject ) );
		card.appendChild( el( 'div', 'dante-nl-card__summary', nl.summary ) );

		// Already sent — status only.
		if ( 'sent' === nl.state ) {
			card.appendChild( el( 'div', 'dante-nl-card__status', '✓ Sent to ' + nl.sent_count + ' subscriber' + ( 1 === nl.sent_count ? '' : 's' ) + '.' ) );
			return card;
		}
		if ( 'scheduled' === nl.state ) {
			card.appendChild( el( 'div', 'dante-nl-card__status', '🕒 Scheduled for ' + nl.send_time + '.' ) );
		}

		// Photo position (only when there's a photo in the email).
		if ( nl.has_image ) {
			var posRow = labeledRow( 'Photo position:' );
			var sel = el( 'select', 'dante-nl-card__pos' );
			[ [ 'top', 'Top' ], [ 'middle', 'Middle of the text' ], [ 'bottom', 'Bottom (after the text)' ] ].forEach( function ( o ) {
				var opt = el( 'option', null, o[1] );
				opt.value = o[0];
				if ( o[0] === nl.image_pos ) { opt.selected = true; }
				sel.appendChild( opt );
			} );
			sel.addEventListener( 'change', function () {
				sel.disabled = true;
				request( '/newsletter/image-pos', { method: 'POST', data: { id: nl.id, pos: sel.value } } )
					.then( function ( res ) {
						if ( res.error ) { window.alert( res.error ); sel.disabled = false; return; }
						showNewsletter( res.newsletter, card, true ); // keep preview open.
					} );
			} );
			posRow.appendChild( sel );
			card.appendChild( posRow );
		}

		// Preview (inline iframe toggle).
		var previewBtn = el( 'button', 'button', openPreview ? 'Hide preview' : 'Preview' );
		var frame = el( 'iframe', 'dante-nl-card__frame' );
		frame.hidden = ! openPreview;
		frame.setAttribute( 'srcdoc', nl.preview_html );
		previewBtn.addEventListener( 'click', function () {
			frame.hidden = ! frame.hidden;
			previewBtn.textContent = frame.hidden ? 'Preview' : 'Hide preview';
		} );
		var previewRow = el( 'div', 'dante-nl-card__actions' );
		previewRow.appendChild( previewBtn );

		// Send a test.
		var testRow = labeledRow( 'Email a test to:' );
		var testInput = el( 'input', 'dante-nl-card__email' );
		testInput.type = 'email';
		testInput.value = nl.default_test_email || '';
		var testBtn = el( 'button', 'button', 'Send test' );
		testBtn.addEventListener( 'click', function () {
			testBtn.disabled = true;
			testBtn.textContent = 'Sending…';
			request( '/newsletter/test', { method: 'POST', data: { id: nl.id, email: testInput.value } } )
				.then( function ( res ) {
					testBtn.disabled = false;
					testBtn.textContent = 'Send test';
					bubble( 'bot', res.message || res.error || 'Done.' );
				} );
		} );
		testRow.appendChild( testInput );
		testRow.appendChild( testBtn );

		// Schedule for later.
		var schedRow = labeledRow( 'Send later on:' );
		var schedInput = el( 'input', 'dante-nl-card__when' );
		schedInput.type = 'datetime-local';
		var schedBtn = el( 'button', 'button', 'scheduled' === nl.state ? 'Reschedule' : 'Schedule' );
		schedBtn.addEventListener( 'click', function () {
			if ( ! schedInput.value ) { window.alert( 'Pick a date and time first.' ); return; }
			schedBtn.disabled = true;
			request( '/newsletter/schedule', { method: 'POST', data: { id: nl.id, datetime: schedInput.value } } )
				.then( function ( res ) {
					if ( res.error ) { window.alert( res.error ); schedBtn.disabled = false; return; }
					bubble( 'bot', res.message );
					showNewsletter( res.newsletter, card );
				} );
		} );
		schedRow.appendChild( schedInput );
		schedRow.appendChild( schedBtn );

		// Send to everyone now (+ cancel schedule if scheduled).
		var sendRow = labeledRow( 'Or send now:' );
		var sendBtn = el( 'button', 'button button-primary', 'Send to all (' + nl.subscriber_count + ')' );
		sendBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( 'Send this newsletter to all ' + nl.subscriber_count + ' subscribers now?' ) ) { return; }
			sendBtn.disabled = true;
			sendBtn.textContent = 'Sending…';
			request( '/newsletter/send', { method: 'POST', data: { id: nl.id } } )
				.then( function ( res ) {
					bubble( 'bot', res.message );
					showNewsletter( res.newsletter, card );
				} );
		} );
		sendRow.appendChild( sendBtn );
		if ( 'scheduled' === nl.state ) {
			var cancelBtn = el( 'button', 'button', 'Cancel schedule' );
			cancelBtn.addEventListener( 'click', function () {
				cancelBtn.disabled = true;
				request( '/newsletter/cancel', { method: 'POST', data: { id: nl.id } } )
					.then( function ( res ) {
						bubble( 'bot', res.message );
						showNewsletter( res.newsletter, card );
					} );
			} );
			sendRow.appendChild( cancelBtn );
		}

		card.appendChild( previewRow );
		card.appendChild( frame );
		card.appendChild( testRow );
		card.appendChild( schedRow );
		card.appendChild( sendRow );
		return card;
	}

	// --- sending -----------------------------------------------------------

	function send( text ) {
		text = ( text || '' ).trim();
		if ( ! text ) { return; }

		if ( ! cfg.configured ) {
			bubble( 'user', text );
			var msg = cfg.canManage
				? 'The assistant needs an API key first. Open Settings → Dante Assistant to add one.'
				: 'The assistant is not set up yet. Please ask your site administrator.';
			bubble( 'bot', msg );
			return;
		}

		bubble( 'user', text );
		history.push( { role: 'user', text: text } );
		inputEl.value = '';

		var thinking = bubble( 'bot', '…' );
		thinking.classList.add( 'dante-msg--thinking' );

		var payload = { message: text, history: history.slice( 0, -1 ) };
		if ( attachmentId ) { payload.attachment_id = attachmentId; }

		request( '/chat', { method: 'POST', data: payload } )
			.then( function ( res ) {
				thinking.remove();
				if ( res.error ) {
					bubble( 'bot', res.error );
					return;
				}
				if ( res.image_used ) {
					clearPhoto(); // only drop it once a tool actually applied it.
				}
				bubble( 'bot', res.reply );
				history.push( { role: 'assistant', text: res.reply } );
				( res.actions || [] ).forEach( actionCard );
				renderReview( res.pending );
				if ( res.history ) { renderHistory( res.history ); }
				if ( res.newsletter ) { showNewsletter( res.newsletter ); }
			} )
			.catch( function () {
				thinking.remove();
				bubble( 'bot', 'Sorry, something went wrong. Please try again.' );
			} );
	}

	// --- wire up -----------------------------------------------------------

	formEl.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		send( inputEl.value );
	} );

	inputEl.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' === e.key && ! e.shiftKey ) {
			e.preventDefault();
			send( inputEl.value );
		}
	} );

	// --- photo upload ------------------------------------------------------

	function clearPhoto() {
		attachmentId = 0;
		thumbEl.hidden = true;
		thumbEl.innerHTML = '';
		fileEl.value = '';
	}

	if ( photoBtn && fileEl ) {
		photoBtn.addEventListener( 'click', function () {
			if ( ! cfg.configured ) {
				bubble( 'bot', 'Add an API key first (Settings → Dante Assistant) to use photos.' );
				return;
			}
			fileEl.click();
		} );

		fileEl.addEventListener( 'change', function () {
			var file = fileEl.files && fileEl.files[0];
			if ( ! file ) { return; }

			photoBtn.disabled = true;
			photoBtn.textContent = 'Uploading…';

			var form = new FormData();
			form.append( 'file', file );

			var headers = { 'X-WP-Nonce': cfg.nonce };
			fetch( cfg.root + '/upload', { method: 'POST', headers: headers, body: form } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					photoBtn.disabled = false;
					photoBtn.textContent = '📷 Add a photo';
					if ( res.error || ! res.attachment_id ) {
						bubble( 'bot', res.error || 'That photo could not be added.' );
						return;
					}
					attachmentId = res.attachment_id;
					thumbEl.hidden = false;
					thumbEl.innerHTML = '';
					if ( res.thumb ) {
						var img = el( 'img' );
						img.src = res.thumb;
						img.alt = '';
						thumbEl.appendChild( img );
					}
					var x = el( 'button', 'dante-assistant__thumb-x', '✕' );
					x.title = 'Remove photo';
					x.addEventListener( 'click', clearPhoto );
					thumbEl.appendChild( x );
					thumbEl.appendChild( el( 'span', 'dante-assistant__thumb-note', 'will be added to your next event or newsletter' ) );
				} )
				.catch( function () {
					photoBtn.disabled = false;
					photoBtn.textContent = '📷 Add a photo';
					bubble( 'bot', 'That photo could not be uploaded.' );
				} );
		} );
	}

	chips.forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			var label = chip.textContent.replace( /^[^A-Za-z]+/, '' ).trim();
			if ( /coming up/i.test( label ) ) {
				send( 'What events are coming up?' );
			} else if ( /newsletter/i.test( label ) ) {
				inputEl.value = 'Send a newsletter about ';
				inputEl.focus();
			} else if ( /page/i.test( label ) ) {
				inputEl.value = 'On the cover page, change the intro text to: ';
				inputEl.focus();
			} else {
				inputEl.value = 'Add an event: ';
				inputEl.focus();
			}
		} );
	} );

	// Greeting + initial state.
	bubble( 'bot', root.getAttribute( 'data-greeting' ) || 'Hello!' );
	request( '/pending', { method: 'GET' } ).then( function ( res ) { renderReview( res ); } ).catch( function () {} );
	request( '/history', { method: 'GET' } ).then( function ( res ) { renderHistory( res.history ); } ).catch( function () {} );
}() );
