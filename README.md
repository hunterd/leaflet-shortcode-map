# Leaflet Shortcode Map

**Author:** David Mussard
**Version:** 1.5
**Requires WordPress:** 5.0 or higher
**Tested up to:** 6.8 (Update as needed)
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html
**Text Domain:** leaflet-shortcode-map
**Donate link:** https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=mussarddavid@hotmail.com&item_name=Donation+for+Leaflet+Shortcode+Map&currency_code=EUR

Adds a "Map" Custom Post Type (CPT) with an admin UI to manage interactive maps based on Leaflet.js. Allows adding unlimited markers with various options and displaying the map via a shortcode.

## Features

*   **"Map" Custom Post Type:** Manage maps as separate posts in the WordPress admin.
*   **Intuitive Admin Interface:**
    *   Define the map center (by address with geocoding via OpenStreetMap Nominatim, or by Lat/Lon coordinates).
    *   Set the initial zoom level.
*   **Unlimited Markers (Repeater):**
    *   Add as many markers as needed to each map.
    *   Fields for each marker: Name (title), Subtitle (optional), Address (with geocoding) or manual Lat/Lon Coordinates.
    *   Icon choice: Select from a list of WordPress Dashicons or upload a custom image icon.
    *   Color choice for Dashicons.
*   **Bulk Import:** Quickly import markers by pasting a formatted list.
*   **Global Icon & Color Settings:** Define a default Dashicon and color, or a global custom icon, and apply them to all markers on a map with one click.
*   **Simple Shortcode:** Display any published map on your pages or posts with `[ma_carte id="YOUR_MAP_ID"]`.
*   **Lightweight Frontend:** Uses the Leaflet.js library (loaded from a CDN) for map display.
*   **Localization:** Translated into French (fr\_FR), Spanish (es\_ES), German (de\_DE), Portuguese (pt\_PT), Russian (ru\_RU), Ukrainian (uk).

## Installation

1.  Download the plugin `.zip` file.
2.  From your WordPress dashboard, go to `Plugins` > `Add New`.
3.  Click `Upload Plugin`.
4.  Choose the downloaded `.zip` file and click `Install Now`.
5.  Activate the plugin.

Or:

1.  Extract the `.zip` file.
2.  Upload the `leaflet-shortcode-map` folder to the `/wp-content/plugins/` directory of your WordPress installation.
3.  From your WordPress dashboard, go to `Plugins` and activate "Leaflet Shortcode Map".

## Usage

### Creating/Editing a Map

1.  Go to the `Maps` > `Add New` menu in your WordPress admin.
2.  Give your map a title (for internal reference).
3.  **Map Center:**
    *   Enter an address (e.g., "Paris, France") and click `Geocode` to automatically get the Lat/Lon coordinates.
    *   Or manually enter the Latitude and Longitude.
    *   Adjust the `Zoom` level (1 = whole world, 18 = very zoomed in).
4.  **Bulk Import (Optional):**
    *   Paste your marker data into the text area, one line per marker, following the format: `Name|Subtitle|Address|Lat|Lon|DashiconClass|ColorHex`.
    *   Example: `Eiffel Tower|Parisian Landmark|Champ de Mars, 5 Av. Anatole France, 75007 Paris|48.8584|2.2945|dashicons-location|#ff0000`
    *   Click `Import Markers`. **Warning:** This will replace all existing markers on this map.
5.  **Global Icon & Color (Optional):**
    *   Choose a default Dashicon and/or color, or upload a global image.
    *   Click `Apply to All Markers` to update all current markers with these global settings (the custom icon takes priority over the Dashicon).
6.  **Markers:**
    *   Fill in the fields for each marker:
        *   `Name`: Title displayed in the marker popup.
        *   `Subtitle`: Additional text in the popup.
        *   `Address`: Enter an address and click `Geocode`, or leave blank if entering Lat/Lon manually.
        *   `Lat`/`Lon`: Marker coordinates.
        *   `Dashicon`: Choose an icon from the list.
        *   `Color`: Choose a color for the Dashicon.
        *   `Custom Icon`: Click `Choose Image` to use an image from your media library instead of the Dashicon. Use `Remove` to remove the custom image.
    *   Use the `×` button to remove a marker.
    *   Click `Add Marker` to add a new empty marker row.
7.  Click `Publish` (or `Update`) to save the map.

### Displaying the Map

1.  Edit the page or post where you want to display the map.
2.  Insert the following shortcode into the content editor (text or shortcode block):
    `[ma_carte id="123"]`
3.  Replace `123` with the actual ID of the map you created. You can find the ID in the URL when editing the map (e.g., `post=123`).
4.  Save the page/post. The map will be displayed on the frontend.

## Bulk Import Format

Use the following format, with one line per marker. Fields are separated by a pipe (`|`).

`Name|Subtitle|Address|Latitude|Longitude|DashiconClass|ColorHex`

*   **Name:** The marker title (required, or at least an address/lat/lon).
*   **Subtitle:** Additional text (optional).
*   **Address:** Full address for geocoding (optional if Lat/Lon are provided).
*   **Latitude:** Latitude coordinate (required if address is not provided/geocodable).
*   **Longitude:** Longitude coordinate (required if address is not provided/geocodable).
*   **DashiconClass:** The CSS class of the Dashicon (e.g., `dashicons-location-alt`, optional).
*   **ColorHex:** The hex color code for the Dashicon (e.g., `#ff0000`, optional, defaults to `#d00`).

**Note:** Bulk import does not support custom icons (images).

## Dependencies

*   Leaflet.js (v1.9.4) - Loaded from `unpkg.com`.
*   OpenStreetMap - Used for default map tiles and geocoding via Nominatim.

## Support

If you find this plugin useful, consider making a donation to support its development:
[Donate via PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=mussarddavid@hotmail.com&item_name=Donation+for+Leaflet+Shortcode+Map&currency_code=EUR)
