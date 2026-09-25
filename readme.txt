=== Mon Club TT ===
Contributors: robinos33
Tags: table tennis, fftt, club, rankings, results
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.4.0
Requires PHP: 7.4
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your table tennis club's players, teams, and rankings from the official FFTT Smartping API. Not affiliated with or endorsed by the FFTT.

== Description ==

Mon Club TT is an unofficial WordPress plugin that connects to the FFTT (Fédération Française de Tennis de Table) Smartping 2.0 API to display your club's data on your WordPress site.

**Features:**

* **Player list** — sortable table with monthly and annual rating progression badges, filterable by gender
* **Team page** — league standings with your club highlighted, championship results by round
* **Match sheets** — click a result to reveal team composition and set-by-set scores (AJAX, cached 7 days)
* **Top Progression** — animated podium showing the top 3 players by monthly or annual rating gain
* **Manual sync** — synchronise players and teams from the admin panel with real-time API logs
* **Dashboard widget** — quick sync button on the WordPress dashboard
* **Team management** — automatically create or trash WordPress pages per team
* **Developer hooks** — expose cached data to other plugins via `apply_filters`

**Shortcodes:**

* `[monclubtt_joueurs type="MF"]` — display club players (M, F, or MF)
* `[monclubtt_equipe iddiv="198511" idpoule="1140384"]` — display a team's standings and results

**Requirements:**

* Valid FFTT API credentials (App ID + password, obtainable from the FFTT)
* Your club number (8 digits, e.g. `10330011`)

This plugin is not affiliated with or endorsed by the FFTT or the Smartping platform.

== Installation ==

1. Upload the `mon-club-tt` folder to `/wp-content/plugins/`
2. Activate the plugin in *Plugins → Installed Plugins*
3. Go to *Mon Club TT → Settings* and enter your FFTT API credentials and club number
4. Click *Synchronize data* to fetch your club's data for the first time

== Frequently Asked Questions ==

= Where do I get FFTT API credentials? =
Contact the FFTT directly. An App ID and password are provided upon request.

= What is the club number format? =
8 digits, e.g. `10330011`.

= How often is the data updated? =
Player data is refreshed on manual sync only. League standings and results are cached and automatically refreshed at 08:00 and 13:00 each day. Match sheets are cached for 7 days (past results do not change).

= Can I use the data in another plugin? =
Yes. The plugin exposes four filters: `monclubtt_get_joueurs`, `monclubtt_get_equipes`, `monclubtt_get_classement_poule`, `monclubtt_get_rencontres_poule`. See the documentation for usage.

== Changelog ==

= 1.4.0 =
* Added: attach a photo to each player from the admin player list (WordPress media library, keyed by license number), reusable by other components via the `monclubtt_get_joueurs` filter
* Added: the "Top Progression" widget places the player's cut-out photo as a sticker (white outline and drop shadow) instead of the drawn avatar, falling back to the drawn avatar when no photo is set

= 1.3.0 =
* Fixed: the season start was computed from September instead of July 1st, the actual start of the FFTT season
* Added: the "Top Progression" widget and the monthly progression columns are hidden in July, August and September — no competition has taken place yet since the season started, so this data would be meaningless
* Added: setting to manually exclude players (by license number) from the player list — the FFTT API has no reliable way to tell that a player has left the club, so it keeps listing them
* Added: "Remove from list" button on each row of the admin Players page, to exclude a player in one click

= 1.2.4 =
* Fixed: "Array" was displayed instead of the missing opponent when a team had a bye on a given round — the round now shows "Exempt"
* Fixed: an empty team name in the standings highlighted every row as the club's own team

= 1.2.3 =
* Fixed: the plugin menu disappeared from the admin in 1.2.2 — the redeclaration guard added in that version was placed before the class declaration, and since PHP binds top-level classes at compile time the guard always matched, returning before the plugin was ever instantiated

= 1.2.2 =
* Fixed: the "last sync" date (and every "last updated" date) was displayed in UTC instead of the site timezone, showing a 1-2 hour offset — dates are now formatted with the WordPress timezone setting
* Fixed: the "x ago" delay shown next to the last sync was inflated by the same offset
* Hardened: all plugin classes and functions are now guarded against redeclaration if a second copy of the plugin is present

= 1.2.1 =
* Fixed: bulk team page creation/deletion from the admin "Teams" screen silently did nothing — team data was mis-sanitized, causing every row to be skipped without any error being reported

= 1.2.0 =
* Fixed: some teams (e.g. 2, 3, 4) no longer appeared in the sync — teams are now de-duplicated per pool instead of per name
* Added: a clear "pool not available" message (with illustration) when the federation removes the pools from its API
* Added: the player listing is now sorted by descending official ranking points by default

= 1.1.0 =
* Players with zero monthly ranking points are now excluded from both the manual sync and the front/admin listings

= 1.0.1 =
* Plugin header now declares Domain Path so the bundled French translation loads
* Shortcode source strings switched to English with French translations in the .po/.mo
* Removed obsolete text-domain mismatch annotations after the slug rename

= 1.0.0 =
* First stable release
* Player list with monthly and annual progression badges
* Team pages with league standings and match results by round
* Match sheets with AJAX lazy loading and 7-day cache
* Top Progression animated podium (monthly / annual toggle)
* Manual sync with real-time API logs
* Automatic team page generation and deletion

== Upgrade Notice ==

= 1.4.0 =
Attach a photo to each player from the admin; it appears as a cut-out sticker on the Top Progression podium.

= 1.3.0 =
Correct season boundary (July 1st), no more Top Progression / monthly columns during the dead months (Jul-Sep), and a way to manually hide players who left the club.

= 1.2.4 =
Shows "Exempt" instead of "Array" when a team has a bye on a round.

= 1.2.3 =
Fixes the admin menu disappearing in 1.2.2. Upgrade immediately if you installed 1.2.2.

= 1.2.2 =
Displays sync and cache dates in your site timezone instead of UTC.

= 1.2.1 =
Fixes bulk team page creation/deletion from the admin screen, which silently did nothing.

= 1.2.0 =
Restores missing teams in the sync, adds a friendly message when pools are unavailable, and sorts players by points.

= 1.1.0 =
Hides players with no monthly points from sync and listings.

= 1.0.1 =
Loads the bundled French translation and aligns translatable strings with WordPress.org conventions.

= 1.0.0 =
First stable release.
