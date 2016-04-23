# Importer for Gravity Forms and NationBuilder #
**Contributors:**      dabernathy89
**Donate link:**       https://www.paypal.me/DanielAbernathy
**Tags:**              nationbuilder, gravity forms
**Requires at least:** 4.4
**Tested up to:**      4.4
**Stable tag:**        0.2.2
**License:**           GPLv2
**License URI:**       http://www.gnu.org/licenses/gpl-2.0.html

Automatically import entries from Gravity Forms into NationBuilder.

## Description ##

Automatically import entries from Gravity Forms into NationBuilder. For each entry on a configured form, this plugin will create a supporter (or update them, if they already existed) in your nation.

**Note: You should only run this plugin on sites that support HTTPS. It uses the OAuth2 framework, which requires HTTPS, for authenticating with NationBuilder's API.**

Follow these steps to get started:

### Authenticating with the NationBuilder API ###
1. Install Gravity Forms if you haven't already. You should be using version 1.9.16 or later.
2. Navigate to the Gravity Forms settings page and find the "NationBuilder" subpage.
3. Copy the Callback URL provided on this page - you'll need it for the next step.
4. In a separate window, register a new app inside NationBuilder: https://YOUR_NATION_SLUG.nationbuilder.com/admin/apps/new
5. Save your Client ID, Client Secret, and nation slug on the Gravity Forms settings page.
6. When the page reloads after saving, it will display a link. You must click this link to complete the OAuth process; it will redirect you back to the settings page.

### Setting up Gravity Forms feeds ###
1. Navigate to a single form's settings page, and find the "NationBuilder" section. Create a feed if there are none.
2. Give the feed a name - this can be something generic like "NationBuilder feed".
3. Follow the instructions on the page for mapping the form fields to the NationBuilder custom fields.
4. Optionally, set a condition for when this feed should run. For example, you can set it to only run when an opt-in checkbox on the form is checked.

Once the above steps are complete, your form(s) should be mapped to NationBuilder. To check if a form entry was successfully pushed to NationBuilder, check the "notes" section on the individual entry screen in the Gravity Forms admin area.

This plugin was built using the generator-plugin-wp tool built by WebDevStudios.

## Installation ##

### Manual Installation ###

1. Upload the entire `/gf-nb-importer` directory to the `/wp-content/plugins/` directory.
2. Activate Importer for Gravity Forms and NationBuilder through the 'Plugins' menu in WordPress.

## Frequently Asked Questions ##


## Screenshots ##

1. The main plugin settings screen

2. The overview of NationBuilder feeds for a form

3. Editing an individual feed for a form

## Changelog ##

### 0.2.2 ###
* Bug fix - fatal error when Gravity Forms is not installed

### 0.2.1 ###
* Bug fix - fatal error caused by require() statement with lower case class name

### 0.2.0 ###
* Reorganize plugin structure, update readme.

### 0.1.0 ###
* Initial release.