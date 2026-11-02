/**
 * Easy Appointment Booking - admin behaviour (vanilla JS).
 *
 * - Confirmation before deleting bookings (row links and bulk action).
 * - Add/remove rows in the services repeater on the settings screen.
 *
 * @package EasyBooking
 */
( function () {
	'use strict';

	var i18n = window.easyBookingAdmin || {};

	function onReady( fn ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	onReady( function () {
		// Confirm single delete links.
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '.eb-confirm-delete' );
			if ( link && ! window.confirm( i18n.confirmDelete ) ) {
				event.preventDefault();
			}
		} );

		// Confirm bulk delete.
		var listForm = document.querySelector( '.eb-list-form' );
		if ( listForm ) {
			listForm.addEventListener( 'submit', function ( event ) {
				var submitter = event.submitter || document.activeElement;
				var selector = submitter && 'doaction2' === submitter.id ? '#bulk-action-selector-bottom' : '#bulk-action-selector-top';
				var select = listForm.querySelector( selector );

				if ( select && 'delete' === select.value && ! window.confirm( i18n.confirmDelete ) ) {
					event.preventDefault();
				}
			} );
		}

		// Services repeater.
		var table = document.querySelector( '.eb-services' );
		var template = document.getElementById( 'tmpl-eb-service-row' );
		var addButton = document.querySelector( '.eb-add-service' );

		if ( ! table || ! template || ! addButton ) {
			return;
		}

		var tbody = table.querySelector( 'tbody' );
		var nextIndex = tbody.querySelectorAll( 'tr' ).length;

		addButton.addEventListener( 'click', function () {
			// Use a timestamp-based index so it never clashes with existing rows.
			var index = 'n' + Date.now() + nextIndex++;
			var html = template.innerHTML.split( '__INDEX__' ).join( index );
			var holder = document.createElement( 'tbody' );

			holder.innerHTML = html.trim();
			var row = holder.firstElementChild;
			tbody.appendChild( row );
			row.querySelector( 'input[type="text"]' ).focus();
		} );

		tbody.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.eb-remove-service' );
			if ( ! button ) {
				return;
			}

			var row = button.closest( 'tr' );
			var name = row.querySelector( 'input[type="text"]' );

			if ( name && name.value && ! window.confirm( i18n.confirmRemove ) ) {
				return;
			}

			row.parentNode.removeChild( row );
		} );
	} );
} )();
