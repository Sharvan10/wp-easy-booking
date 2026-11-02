/**
 * Easy Appointment Booking - frontend form behaviour.
 *
 * Vanilla JS (no jQuery): loads available slots via AJAX when the service or
 * date changes, and submits the booking without a page reload.
 *
 * @package EasyBooking
 */
( function () {
	'use strict';

	var config = window.easyBooking || {};
	var i18n = config.i18n || {};

	/**
	 * POST a FormData payload to admin-ajax.php and return the parsed JSON.
	 *
	 * @param {FormData} data Payload.
	 * @return {Promise<Object>} Response body (WordPress JSON envelope).
	 */
	function post( data ) {
		data.append( 'nonce', config.nonce );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} ).then( function ( response ) {
			return response.json().catch( function () {
				return { success: false, data: { message: i18n.genericError } };
			} );
		} );
	}

	/**
	 * ISO day of week (1 = Monday ... 7 = Sunday) for a Y-m-d string.
	 *
	 * @param {string} ymd Date.
	 * @return {number} Day number.
	 */
	function isoDay( ymd ) {
		var parts = ymd.split( '-' );
		var day = new Date( Date.UTC( +parts[ 0 ], +parts[ 1 ] - 1, +parts[ 2 ] ) ).getUTCDay();

		return 0 === day ? 7 : day;
	}

	/**
	 * Initialise one booking form.
	 *
	 * @param {HTMLFormElement} form Form element.
	 */
	function initForm( form ) {
		var alertBox = form.querySelector( '.eb-alert' );
		var slotsBox = form.querySelector( '[data-slots]' );
		var service = form.elements.service_id;
		var date = form.elements.booking_date;
		var button = form.querySelector( '.eb-button' );
		var buttonText = button.textContent;
		var requestId = 0;

		function showAlert( type, message ) {
			alertBox.className = 'eb-alert eb-alert--' + type;
			alertBox.textContent = message;
			alertBox.hidden = ! message;
		}

		function clearErrors() {
			form.querySelectorAll( '.eb-error' ).forEach( function ( el ) {
				el.textContent = '';
			} );
			form.querySelectorAll( '.eb-field--invalid' ).forEach( function ( el ) {
				el.classList.remove( 'eb-field--invalid' );
			} );
		}

		function showErrors( errors ) {
			var first = null;

			Object.keys( errors || {} ).forEach( function ( field ) {
				var holder = form.querySelector( '[data-error-for="' + field + '"]' );
				if ( ! holder ) {
					return;
				}
				holder.textContent = errors[ field ];
				holder.closest( '.eb-field' ).classList.add( 'eb-field--invalid' );
				first = first || holder.closest( '.eb-field' );
			} );

			if ( first ) {
				var input = first.querySelector( 'input, select, textarea' );
				if ( input ) {
					input.focus();
				}
			}
		}

		function setSlotsMessage( message ) {
			slotsBox.innerHTML = '';
			var p = document.createElement( 'p' );
			p.className = 'eb-slots__hint';
			p.textContent = message;
			slotsBox.appendChild( p );
		}

		function renderSlots( slots ) {
			slotsBox.innerHTML = '';

			if ( ! slots.length ) {
				setSlotsMessage( i18n.noSlots );
				return;
			}

			slots.forEach( function ( slot, index ) {
				var id = form.parentNode.id + '-slot-' + index;
				var input = document.createElement( 'input' );
				var label = document.createElement( 'label' );

				input.type = 'radio';
				input.name = 'start_time';
				input.value = slot.start;
				input.id = id;
				input.className = 'eb-slot__input';

				label.htmlFor = id;
				label.className = 'eb-slot';
				label.textContent = slot.label;

				slotsBox.appendChild( input );
				slotsBox.appendChild( label );
			} );
		}

		function loadSlots() {
			var current = ++requestId;

			if ( ! service.value || ! date.value ) {
				setSlotsMessage( i18n.chooseFirst );
				return;
			}

			if ( config.workingDays && config.workingDays.indexOf( isoDay( date.value ) ) === -1 ) {
				setSlotsMessage( i18n.closedDay );
				return;
			}

			setSlotsMessage( i18n.loading );
			slotsBox.setAttribute( 'aria-busy', 'true' );

			var data = new FormData();
			data.append( 'action', 'easy_booking_get_slots' );
			data.append( 'service_id', service.value );
			data.append( 'booking_date', date.value );

			post( data )
				.then( function ( res ) {
					// Ignore responses for outdated requests.
					if ( current !== requestId ) {
						return;
					}
					if ( res.success ) {
						renderSlots( res.data.slots || [] );
					} else {
						setSlotsMessage( ( res.data && res.data.message ) || i18n.genericError );
					}
				} )
				.catch( function () {
					if ( current === requestId ) {
						setSlotsMessage( i18n.genericError );
					}
				} )
				.finally( function () {
					slotsBox.removeAttribute( 'aria-busy' );
				} );
		}

		// Clear a field's error as soon as the visitor edits it.
		function clearFieldError( event ) {
			var field = event.target.closest( '.eb-field' );
			if ( ! field || ! field.classList.contains( 'eb-field--invalid' ) ) {
				return;
			}
			field.classList.remove( 'eb-field--invalid' );
			field.querySelectorAll( '.eb-error' ).forEach( function ( el ) {
				el.textContent = '';
			} );
		}

		form.addEventListener( 'input', clearFieldError );
		form.addEventListener( 'change', clearFieldError );

		service.addEventListener( 'change', loadSlots );
		date.addEventListener( 'change', loadSlots );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearErrors();
			showAlert( '', '' );

			// All validation happens server-side so the rules live in one place.
			var data = new FormData( form );
			data.append( 'action', 'easy_booking_submit' );

			button.disabled = true;
			button.textContent = i18n.submitting;

			post( data )
				.then( function ( res ) {
					var payload = res.data || {};

					if ( res.success ) {
						form.reset();
						setSlotsMessage( i18n.chooseFirst );
						showAlert( 'success', payload.message );
					} else {
						showAlert( 'error', payload.message || i18n.genericError );
						showErrors( payload.errors );

						// The slot may have been taken meanwhile: refresh the list.
						if ( payload.errors && payload.errors.start_time ) {
							loadSlots();
						}
					}

					alertBox.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
				} )
				.catch( function () {
					showAlert( 'error', i18n.genericError );
				} )
				.finally( function () {
					button.disabled = false;
					button.textContent = buttonText;
				} );
		} );

		// Restore slots if the browser pre-filled the fields (back navigation).
		if ( service.value && date.value ) {
			loadSlots();
		}
	}

	function init() {
		document.querySelectorAll( '.eb-booking .eb-form' ).forEach( initForm );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
