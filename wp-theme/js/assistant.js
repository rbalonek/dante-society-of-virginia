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

	var logEl = root.querySelector( '.dante-assistant__log' );
	var reviewEl = root.querySelector( '.dante-assistant__review' );
	var historyEl = root.querySelector( '.dante-assistant__history' );
	var formEl = root.querySelector( '.dante-assistant__form' );
	var inputEl = root.querySelector( '.dante-assistant__input' );
	var chips = root.querySelectorAll( '.dante-assistant__chip' );

	// Visible conversation turns sent back to the server for context.
	var history = [];

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
		if ( action.edit_url ) {
			var a = el( 'a', 'dante-card__link', 'Review →' );
			a.href = action.edit_url;
			a.target = '_blank';
			card.appendChild( a );
		}
		logEl.appendChild( card );
		logEl.scrollTop = logEl.scrollHeight;
	}

	// --- review panel (approve/discard all) --------------------------------

	function renderReview( pending ) {
		reviewEl.innerHTML = '';
		if ( ! pending || ! pending.items || ! pending.items.length ) {
			reviewEl.hidden = true;
			return;
		}
		reviewEl.hidden = false;

		reviewEl.appendChild( el( 'h4', 'dante-review__title', 'Waiting for your approval' ) );

		var list = el( 'ul', 'dante-review__list' );
		pending.items.forEach( function ( item ) {
			var li = el( 'li' );
			if ( item.edit_url ) {
				var a = el( 'a', null, item.label );
				a.href = item.edit_url;
				a.target = '_blank';
				li.appendChild( a );
			} else {
				li.textContent = item.label;
			}
			list.appendChild( li );
		} );
		reviewEl.appendChild( list );

		var actions = el( 'div', 'dante-review__actions' );
		var approve = el( 'button', 'button button-primary', 'Approve & publish all' );
		var discard = el( 'button', 'button', 'Discard all' );
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

		request( '/chat', { method: 'POST', data: { message: text, history: history.slice( 0, -1 ) } } )
			.then( function ( res ) {
				thinking.remove();
				if ( res.error ) {
					bubble( 'bot', res.error );
					return;
				}
				bubble( 'bot', res.reply );
				history.push( { role: 'assistant', text: res.reply } );
				( res.actions || [] ).forEach( actionCard );
				renderReview( res.pending );
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

	chips.forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			var label = chip.textContent.replace( /^[^A-Za-z]+/, '' ).trim();
			if ( /coming up/i.test( label ) ) {
				send( 'What events are coming up?' );
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
