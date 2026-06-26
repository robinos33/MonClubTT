=== Mon Club TT ===
Contributors: robinos33
Tags: table tennis, fftt, club, rankings, results
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.0.1
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

= 1.0.1 =
Loads the bundled French translation and aligns translatable strings with WordPress.org conventions.

= 1.0.0 =
First stable release.
