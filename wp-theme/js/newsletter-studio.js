/**
 * Newsletter Studio — the browser half of the guided composer.
 *
 * The server owns the draft; this file only ever asks it to change something
 * and then redraws from whatever comes back. That means a reload, a second tab,
 * or a mis-click can never leave the screen showing something different from
 * what would actually be sent.
 *
 * No build step and no wp.* dependency, matching the rest of the theme's JS.
 */
( function () {
	'use strict';

	if ( typeof window.danteStudio === 'undefined' ) {
		return;
	}

	var cfg   = window.danteStudio;
	var state = cfg.state || {};

	// Photos attached to the message being typed, cleared once it is sent.
	var attached = [];

	var $ = function ( id ) { return document.getElementById( id ); };

	/* ------------------------------------------------------------------ *
	 * Talking to the server
	 * ------------------------------------------------------------------ */

	/**
	 * POST to a studio route. `body` may be a plain object (sent as JSON) or a
	 * FormData (sent as-is, for file uploads).
	 */
	function call( path, body, method ) {
		var opts = {
			method: method || 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce }
		};

		if ( body instanceof FormData ) {
			opts.body = body;
		} else if ( body ) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify( body );
		}

		return fetch( cfg.root + '/' + path, opts )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( data && data.error ) {
					alert( data.error );
					return null;
				}
				return data;
			} )
			.catch( function () {
				alert( 'Something went wrong talking to the website. Please try again.' );
				return null;
			} );
	}

	/** Apply a state payload from the server and redraw. */
	function adopt( data ) {
		if ( ! data || typeof data.id === 'undefined' ) {
			return false;
		}
		state = data;
		render();
		return true;
	}

	/* ------------------------------------------------------------------ *
	 * Drawing the screen
	 * ------------------------------------------------------------------ */

	function render() {
		renderTypeCards();
		renderFields();
		renderChat();
		renderSteps();
		renderPreview();
	}

	function renderTypeCards() {
		var wrap = $( 'dst-type-cards' );
		var chosen = $( 'dst-chosen' );

		if ( state.type ) {
			wrap.hidden = true;
			chosen.hidden = false;
			$( 'dst-chosen-label' ).textContent = cfg.types[ state.type ]
				? cfg.types[ state.type ].label
				: state.type;
			return;
		}

		chosen.hidden = true;
		wrap.hidden = false;

		if ( wrap.childNodes.length ) {
			return; // built once; the cards never change
		}

		Object.keys( cfg.types ).forEach( function ( key ) {
			var t = cfg.types[ key ];
			var card = document.createElement( 'button' );
			card.type = 'button';
			card.className = 'dst-card';
			card.setAttribute( 'data-type', key );
			card.innerHTML =
				'<span class="dst-card-icon">' + t.icon + '</span>' +
				'<span class="dst-card-label"></span>' +
				'<span class="dst-card-blurb"></span>';
			card.querySelector( '.dst-card-label' ).textContent = t.label;
			card.querySelector( '.dst-card-blurb' ).textContent = t.blurb;
			card.addEventListener( 'click', function () {
				call( 'type', { type: key } ).then( adopt );
			} );
			wrap.appendChild( card );
		} );
	}

	function renderFields() {
		var f = state.fields || {};

		setValue( 'dst-subject', f.subject );
		setValue( 'dst-headline', f.headline );
		setValue( 'dst-intro', f.intro );
		setValue( 'dst-body', f.body );

		// Only the parts this kind of email needs.
		Array.prototype.forEach.call( document.querySelectorAll( '.dst-only' ), function ( el ) {
			var wanted = ( el.getAttribute( 'data-for' ) || '' ).split( ' ' );
			el.hidden = wanted.indexOf( state.type ) === -1;
		} );

		fillOnce( 'dst-event', cfg.events, 'id', 'label', 'Choose an event…' );
		fillOnce( 'dst-design', cfg.designs, 'slug', 'label', 'Choose a design…' );

		var ev = $( 'dst-event' );
		if ( ev && f.event_id ) {
			ev.value = String( f.event_id );
		}
	}

	function setValue( id, value ) {
		var el = $( id );
		if ( el && document.activeElement !== el ) {
			el.value = value || '';
		}
	}

	function fillOnce( id, rows, valueKey, labelKey, placeholder ) {
		var sel = $( id );
		if ( ! sel || sel.childNodes.length ) {
			return;
		}
		var blank = document.createElement( 'option' );
		blank.value = '';
		blank.textContent = placeholder;
		sel.appendChild( blank );

		( rows || [] ).forEach( function ( row ) {
			var opt = document.createElement( 'option' );
			opt.value = row[ valueKey ];
			opt.textContent = row[ labelKey ];
			sel.appendChild( opt );
		} );
	}

	function renderChat() {
		$( 'dst-chat-off' ).hidden = !! state.chat_ready;
		$( 'dst-chat-on' ).hidden = ! state.chat_ready;

		// A field-driven email has to be handed over before it can be edited freely.
		var editable = state.type === 'custom_html';
		$( 'dst-handover' ).hidden = editable;
		$( 'dst-chat-panel' ).hidden = ! editable;

		var log = $( 'dst-log' );
		log.innerHTML = '';

		( state.chat || [] ).forEach( function ( turn ) {
			log.appendChild( bubble( turn ) );
		} );

		if ( ! ( state.chat || [] ).length ) {
			var hi = document.createElement( 'p' );
			hi.className = 'dst-empty-chat';
			hi.textContent = 'Tell me what to change and I will do it. For example: “make the headline bigger”, “change the date to November 12”, or “put the photo I just added under the title”.';
			log.appendChild( hi );
		}

		log.scrollTop = log.scrollHeight;

		var history = $( 'dst-history' );
		history.hidden = ! ( editable && state.edit_count > 0 );
		$( 'dst-undo' ).textContent = 'Undo “' + ( state.undo_label || 'the last change' ) + '”';
	}

	function bubble( turn ) {
		var el = document.createElement( 'div' );
		el.className = 'dst-bubble dst-' + ( turn.role === 'assistant' ? 'bot' : 'me' );

		var p = document.createElement( 'p' );
		p.textContent = turn.text;
		el.appendChild( p );

		( turn.photos || [] ).forEach( function ( src ) {
			var img = document.createElement( 'img' );
			img.src = src;
			img.alt = '';
			img.className = 'dst-bubble-photo';
			el.appendChild( img );
		} );

		( turn.notes || [] ).forEach( function ( note ) {
			var n = document.createElement( 'span' );
			n.className = 'dst-note';
			n.textContent = note;
			el.appendChild( n );
		} );

		return el;
	}

	function renderSteps() {
		var hasType = !! state.type;
		var ready   = hasType && String( state.preview || '' ).trim() !== '';

		$( 'dst-step-details' ).hidden = ! hasType;
		$( 'dst-step-chat' ).hidden = ! ready;
		$( 'dst-step-send' ).hidden = ! ready;

		$( 'dst-unsub-warn' ).hidden = ! state.needs_unsub;

		$( 'dst-send' ).textContent = 'Send to all ' + state.subscribers +
			( state.subscribers === 1 ? ' subscriber' : ' subscribers' );
		$( 'dst-send' ).disabled = state.subscribers < 1;

		var test = $( 'dst-test-email' );
		if ( ! test.value ) {
			test.value = cfg.myEmail;
		}
	}

	function renderPreview() {
		var frame = $( 'dst-preview' );
		var empty = $( 'dst-preview-empty' );
		var html  = state.preview || '';

		if ( ! html.trim() ) {
			frame.removeAttribute( 'srcdoc' );
			frame.hidden = true;
			empty.hidden = false;
			return;
		}

		empty.hidden = true;
		frame.hidden = false;
		frame.srcdoc = html;
	}

	/** A brief "Updated" flash on the preview, so a change is never silent. */
	function flash() {
		var el = $( 'dst-flash' );
		el.hidden = false;
		clearTimeout( flash.timer );
		flash.timer = setTimeout( function () { el.hidden = true; }, 1800 );
	}

	function saved() {
		var el = $( 'dst-saving' );
		el.hidden = false;
		clearTimeout( saved.timer );
		saved.timer = setTimeout( function () { el.hidden = true; }, 1500 );
	}

	/* ------------------------------------------------------------------ *
	 * Step 1 & 2 — type and details
	 * ------------------------------------------------------------------ */

	$( 'dst-change-type' ).addEventListener( 'click', function () {
		if ( state.edit_count > 0 &&
			! confirm( 'Changing the kind of email will discard this draft. Carry on?' ) ) {
			return;
		}
		state.type = '';
		render();
	} );

	// Field edits save when the person leaves the field, not on every keystroke.
	[ 'dst-subject', 'dst-headline', 'dst-intro', 'dst-body' ].forEach( function ( id ) {
		var el = $( id );
		if ( ! el ) {
			return;
		}
		el.addEventListener( 'change', function () {
			var payload = {};
			payload[ id.replace( 'dst-', '' ) ] = el.value;
			call( 'fields', payload ).then( function ( data ) {
				if ( adopt( data ) ) {
					saved();
					flash();
				}
			} );
		} );
	} );

	if ( $( 'dst-event' ) ) {
		$( 'dst-event' ).addEventListener( 'change', function () {
			call( 'fields', { event_id: this.value || 0 } ).then( function ( data ) {
				if ( adopt( data ) ) { flash(); }
			} );
		} );
	}

	if ( $( 'dst-design' ) ) {
		$( 'dst-design' ).addEventListener( 'change', function () {
			var slug = this.value;
			if ( ! slug ) {
				return;
			}
			if ( state.edit_count > 0 &&
				! confirm( 'Loading this design will replace what you have now. Carry on?' ) ) {
				this.value = '';
				return;
			}
			call( 'saved', { slug: slug } ).then( function ( data ) {
				if ( adopt( data ) ) { flash(); }
			} );
		} );
	}

	// Uploading a design: read it in the browser, hand the text to the server,
	// and the preview appears as soon as the file is chosen.
	$( 'dst-file' ).addEventListener( 'change', function () {
		var file = this.files && this.files[0];
		if ( ! file ) {
			return;
		}
		var name = file.name;
		var reader = new FileReader();
		reader.onload = function ( e ) {
			call( 'document', { html: e.target.result, source: name } ).then( function ( data ) {
				if ( adopt( data ) ) { flash(); }
			} );
		};
		reader.readAsText( file );
		this.value = '';
	} );

	$( 'dst-paste-use' ).addEventListener( 'click', function () {
		var html = $( 'dst-paste' ).value;
		if ( ! html.trim() ) {
			alert( 'Paste the email code into the box first.' );
			return;
		}
		call( 'document', { html: html, source: 'Pasted design' } ).then( function ( data ) {
			if ( adopt( data ) ) { flash(); }
		} );
	} );

	/* ------------------------------------------------------------------ *
	 * Step 3 — the chat
	 * ------------------------------------------------------------------ */

	$( 'dst-convert' ).addEventListener( 'click', function () {
		var btn = this;
		btn.disabled = true;
		call( 'convert', {} ).then( function ( data ) {
			btn.disabled = false;
			if ( adopt( data ) ) { flash(); }
		} );
	} );

	$( 'dst-photo' ).addEventListener( 'change', function () {
		var files = Array.prototype.slice.call( this.files || [] );
		this.value = '';
		files.forEach( uploadPhoto );
	} );

	function uploadPhoto( file ) {
		var body = new FormData();
		body.append( 'file', file );

		var placeholder = { name: file.name, pending: true };
		attached.push( placeholder );
		renderAttached();

		call( 'photo', body ).then( function ( data ) {
			var at = attached.indexOf( placeholder );
			if ( ! data ) {
				if ( at !== -1 ) { attached.splice( at, 1 ); }
			} else if ( at !== -1 ) {
				attached[ at ] = data;
			}
			renderAttached();
		} );
	}

	function renderAttached() {
		var wrap = $( 'dst-photos' );
		wrap.innerHTML = '';
		wrap.hidden = attached.length === 0;

		attached.forEach( function ( photo, index ) {
			var chip = document.createElement( 'span' );
			chip.className = 'dst-chip' + ( photo.pending ? ' is-pending' : '' );

			if ( photo.thumb ) {
				var img = document.createElement( 'img' );
				img.src = photo.thumb;
				img.alt = '';
				chip.appendChild( img );
			}

			var label = document.createElement( 'span' );
			label.textContent = photo.pending ? 'Adding ' + photo.name + '…' : photo.name;
			chip.appendChild( label );

			if ( ! photo.pending ) {
				var x = document.createElement( 'button' );
				x.type = 'button';
				x.className = 'dst-chip-x';
				x.setAttribute( 'aria-label', 'Remove ' + photo.name );
				x.textContent = '×';
				x.addEventListener( 'click', function () {
					attached.splice( index, 1 );
					renderAttached();
				} );
				chip.appendChild( x );
			}

			wrap.appendChild( chip );
		} );
	}

	function sendMessage() {
		var box  = $( 'dst-message' );
		var text = box.value.trim();
		var ready = attached.filter( function ( p ) { return ! p.pending; } );

		if ( ! text && ! ready.length ) {
			return;
		}
		if ( attached.length !== ready.length ) {
			alert( 'One moment — a picture is still uploading.' );
			return;
		}

		var btn = $( 'dst-send-msg' );
		btn.disabled = true;
		btn.textContent = 'Thinking…';

		// Show the person's message straight away rather than after the round-trip.
		var log = $( 'dst-log' );
		if ( log.querySelector( '.dst-empty-chat' ) ) {
			log.innerHTML = '';
		}
		log.appendChild( bubble( {
			role: 'user',
			text: text,
			photos: ready.map( function ( p ) { return p.thumb; } )
		} ) );
		log.scrollTop = log.scrollHeight;

		call( 'chat', {
			message: text,
			photos: ready.map( function ( p ) { return { id: p.id }; } )
		} ).then( function ( data ) {
			btn.disabled = false;
			btn.textContent = 'Send';

			if ( ! data ) {
				renderChat(); // drop the optimistic bubble; the draft did not change
				return;
			}

			box.value = '';
			attached = [];
			renderAttached();

			if ( adopt( data ) && data.notes && data.notes.length ) {
				flash();
			}
		} );
	}

	$( 'dst-send-msg' ).addEventListener( 'click', sendMessage );

	// Enter sends; Shift+Enter makes a new line.
	$( 'dst-message' ).addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			sendMessage();
		}
	} );

	$( 'dst-undo' ).addEventListener( 'click', function () {
		call( 'undo', {} ).then( function ( data ) {
			if ( adopt( data ) ) { flash(); }
		} );
	} );

	$( 'dst-revert' ).addEventListener( 'click', function () {
		if ( ! confirm( 'Undo every change the assistant made and go back to the original?' ) ) {
			return;
		}
		call( 'revert', {} ).then( function ( data ) {
			if ( adopt( data ) ) { flash(); }
		} );
	} );

	/* ------------------------------------------------------------------ *
	 * Step 4 — sending
	 * ------------------------------------------------------------------ */

	$( 'dst-test' ).addEventListener( 'click', function () {
		var btn = this;
		btn.disabled = true;
		btn.textContent = 'Sending…';
		call( 'test', { email: $( 'dst-test-email' ).value } ).then( function ( data ) {
			btn.disabled = false;
			btn.textContent = 'Send test';
			if ( data && data.message ) {
				alert( data.message );
			}
		} );
	} );

	$( 'dst-send' ).addEventListener( 'click', function () {
		var count = state.subscribers;

		if ( state.needs_unsub &&
			! confirm( 'This email has no unsubscribe link, which a newsletter is required to have. Send it anyway?' ) ) {
			return;
		}
		if ( ! confirm( 'Send this to all ' + count + ' subscribers? This cannot be undone.' ) ) {
			return;
		}

		var btn = this;
		btn.disabled = true;
		btn.textContent = 'Sending…';

		call( 'send', {} ).then( function ( data ) {
			btn.disabled = false;
			if ( data ) {
				alert( data.message );
				adopt( data );
			} else {
				render();
			}
		} );
	} );

	// Reuses the classic composer's download handler so the file you keep is
	// byte-identical to the email that goes out.
	$( 'dst-download' ).addEventListener( 'click', function () {
		$( 'dst-dl-subject' ).value = ( state.fields && state.fields.subject ) || 'dante-newsletter';
		$( 'dst-dl-html' ).value = state.preview || '';
		$( 'dst-download-form' ).submit();
	} );

	$( 'dst-reset' ).addEventListener( 'click', function () {
		if ( ! confirm( 'Throw this newsletter away and start again from the beginning?' ) ) {
			return;
		}
		call( 'reset', {} ).then( function ( data ) {
			attached = [];
			renderAttached();
			adopt( data );
		} );
	} );

	render();
}() );
