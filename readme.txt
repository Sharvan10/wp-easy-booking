=== Easy Appointment Booking ===
Contributors: sharvan10
Tags: booking, appointments, scheduling, calendar, reservations
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, secure appointment booking form with real-time slot availability, email notifications and a clean admin dashboard.

== Description ==

Easy Appointment Booking lets your visitors book appointments straight from your website. Add the `[easy_booking]` shortcode to any page and customers can pick a service, a date and one of the free time slots, then submit the form without a page reload.

Already booked slots are hidden automatically, and the server double-checks every booking so the same time can never be booked twice, even when two people submit at the same moment.

= Features =

* Booking form via the `[easy_booking]` shortcode: name, email, phone, service, date, time slot and notes.
* Only available time slots are shown, based on your working days, working hours, slot length and existing bookings.
* AJAX submission with inline validation messages. No page reloads, no jQuery.
* Server-side protection against double bookings.
* Admin "Bookings" menu with a sortable list, search, status and date filters, bulk confirm/cancel/delete and pagination.
* Single booking screen to view and edit details and change the status (Pending, Confirmed, Cancelled).
* Settings page: services (name, duration, price), working days, working hours, slot length, booking window, notification email and email templates.
* Dashboard widget with today's and upcoming bookings.
* Emails: confirmation to the customer and a notification to the admin for each new booking, plus an email to the customer when the status changes.
* Export bookings to CSV, using the current filters.
* Translation ready (text domain `easy-booking`).
* Lightweight, responsive styles that are easy to adapt with CSS custom properties.

= Shortcode =

`[easy_booking]`

Optional attributes:

* `title`: heading above the form. Use `title=""` to hide it.
* `service`: ID of a service to pre-select, for example `[easy_booking service="haircut"]`.

= Developer hooks =

* `easy_booking_capability` (filter): capability required to manage bookings. Default `manage_options`.
* `easy_booking_available_slots` (filter): change the slots offered for a date and service.
* `easy_booking_validation_errors` (filter): add your own validation rules.
* `easy_booking_email` (filter): change any outgoing email.
* `easy_booking_send_status_email` (filter): turn off status change emails.
* `easy_booking_rate_limit` (filter): maximum bookings per visitor IP in 10 minutes. Default 5; `0` turns the limit off.
* `easy_booking_created` (action): fires after a booking is stored.
* `easy_booking_status_changed` (action): fires after a booking's status changes.

== Installation ==

1. Upload the `easy-booking` folder to `/wp-content/plugins/`, or install the zip file via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin from the **Plugins** screen.
3. Go to **Bookings > Settings** to add your services and set your working hours.
4. Add the `[easy_booking]` shortcode to a page.

== Frequently Asked Questions ==

= Can two customers book the same time slot? =

No. Booked slots are hidden from the form, and the server checks again inside a short lock just before it saves the booking. If a slot was taken a moment earlier, the customer is asked to pick another time.

= Do cancelled bookings block a time slot? =

No. Only pending and confirmed bookings block a slot. Cancelling a booking frees its time again.

= Can I change the email texts? =

Yes. Go to **Bookings > Settings > Email templates**. Placeholders such as `{customer_name}`, `{service}`, `{date}` and `{time}` are replaced automatically.

= Which timezone is used? =

The timezone set under **Settings > General** in WordPress.

= Does it delete my data when I remove the plugin? =

Deactivating keeps everything. Deleting the plugin from the Plugins screen removes the bookings table and all plugin settings.

== Screenshots ==

1. Booking form with available time slots.
2. Bookings list with filters and bulk actions.
3. Single booking view and edit screen.
4. Settings page.
5. Dashboard widget.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
