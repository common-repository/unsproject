=== UNS Project WordPress Authentication ===

Contributors: unsproject, nicu_m
Tags: qrcode, qr code, jwt, login, secure login, uns, unsproject
Requires at least: 4.4.0
Tested up to: 5.8
Requires PHP: 5.3
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html 

== Description ==

UNS Project offers secure private, passwordless, authentication for your WordPress site. The plugin also supports Secure Remote Password (SRP) Authentication. Protect passwords by not storing them on the server.

Digital Signatures have been around since 1976. They are still the most secure method to claim and verify ownership of Internet accounts, photos, videos, code, and any other digital asset. 45 years later, digital signing is rarely used on consumer-facing products and services. They are still too technical, too complicated, and too difficult for 99% of users.

UNS  Digital Authentication is as easy as 1, 2, 3.
1. Scan a QR code with the camera on your phone and click on the link
2. Confirm you want to login or register
3. Verify your identity using your phone’s built in biometric lock or passcode.

UNS is a human-centered computer security solution — requiring no discipline and no mental gymnastics – just a handy smartphone.
- Designed for simplicity
- Designed for security
- Designed for privacy
- Designed for humans

If users do not want to download the UNS Authenticator or don’t have a smartphone, UNS can be used with email verification to authenticate from any browser.

== Installation ==

Here's how you install and activate the JWT-login plugin:

1. Download this plugin.
2. Upload the .zip file in your WordPress plugin directory or install it from plugin directory.
3. Activate the plugin from the "Plugins" menu in WordPress.
4. Register to UNS Project with your name, email and phone.
5. Scan QR Code from your profile in order to link  your WordPress username with UNS account
6. Now you can log in into WordPress only by using the QR Code

== Plugin Requirements ==

1. HTTPS website
2. php `openssl` extension 
3. php `curl` extension

== Screenshots ==

1. UNS authentication with different types of proof 
2. Plugin main page
3. Profile page QR code
4. Login QR code
6. Register users with QR code

== Changelog ==

= 3.0.0 (09 Aug 2021)
* Implement production flow 

= 2.0.3 (26 Mar 2021)
* Improve UI for login screen

= 2.0.2 (21 Mar 2021)
* Improve plugin UI

= 2.0.1 (18 Mar 2021)
* Fix email issue for SRP

= 2.0.0 (15 Mar 2021)
* Add support for SRP
* Improve UI

= 1.1.0 (14 Dec 2020)
* On plugin cleanup delete data from UNS servers
* Remove prefix from uniqueID

= 1.0.0 (04 Nov 2020) =
* Initial release
