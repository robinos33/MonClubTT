# Changelog

Toutes les modifications notables de MonClubTT sont documentées ici.  
Format : [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/)

---

## [Non publié]

### Ajouté

- Page d'admin « Réseaux sociaux » : génération de visuels prêts à poster (PNG carré 1080 × 1080 ou story 1080 × 1920), dessinés dans le navigateur (canvas, sans GD / Imagick), avec aperçu puis téléchargement
- Visuel « Top Progression » (mois ou saison, filtre Tous / Hommes / Femmes) reprenant le podium du widget, avec les photos détourées des joueurs
- Visuel « Top perfs » du dernier week-end de championnat par équipes : victoires contre mieux classé lues sur les feuilles de match, regroupées par joueur, points gagnés au barème FFTT
- Premiers tests unitaires PHPUnit (`composer test`) sur le calcul des perfs

---

## [1.5.0] — 2026-09-25

### Ajouté

- La page des joueurs est structurée comme un effectif de club : le podium « Top Progression » en tête, puis une bande de stats club (joueurs classés, meilleur classement, progression moyenne, joueurs en hausse sur le mois ; progression moyenne sur la saison hors compétition), puis le tableau triable
- La photo détourée du joueur apparaît en vignette devant son nom dans le tableau (initiales en repli)
- Puces de filtre Tous / Hommes / Femmes sur la liste mixte : elles filtrent le tableau, la bande de stats et le podium

---

## [1.4.0] — 2026-09-25

### Ajouté

- Possibilité d'associer une photo à chaque joueur depuis la liste de l'admin (médiathèque WordPress, indexée par numéro de licence). La photo est réutilisable par les autres composants via le hook `monclubtt_get_joueurs`
- Le widget « Top Progression » pose la photo détourée du joueur en sticker (liseré blanc et ombre portée) à la place de l'avatar dessiné ; repli automatique sur l'avatar dessiné lorsqu'aucune photo n'est associée

---

## [1.3.0] — 2026-09-23

### Corrigé

- Le début de saison était calculé à partir de septembre au lieu du 1er juillet, date réelle de début de saison FFTT

### Ajouté

- Le widget « Top Progression » et les colonnes de progression mensuelle sont masqués en juillet, août et septembre : aucune compétition n'a encore eu lieu depuis le début de saison, ces données n'ont donc pas de sens durant cette période
- Réglage permettant d'exclure manuellement des joueurs (par numéro de licence) de la liste des joueurs : la FFTT ne fournit aucun champ fiable pour détecter qu'un licencié a quitté le club, son API continue de le rattacher au club même après son départ
- Bouton « Retirer de la liste » sur chaque ligne de l'admin Joueurs, pour exclure un joueur en un clic sans éditer les réglages à la main

---

## [1.2.4] — 2026-09-20

### Corrigé

- Lorsqu'une équipe était exempte sur une journée, le nom de l'adversaire manquant s'affichait « Array » (avec un avertissement PHP `Array to string conversion`) au lieu de « Exempt ». L'API FFTT renvoie un élément `<equb/>` vide, que la conversion `json_decode(json_encode($xml), true)` transforme en tableau vide ; `esc_html()` castant son argument en chaîne, ce tableau s'affichait littéralement. Les champs scalaires venant de l'API passent désormais par `monclubtt_api_texte()`, qui les normalise et accepte une valeur de repli
- Dans le classement, un nom d'équipe vide renvoyé par l'API faisait surligner **toutes** les lignes comme étant l'équipe du club : la comparaison `preg_match()` se réduisait à `//`, qui correspond à n'importe quelle chaîne. Elle est remplacée par une recherche de sous-chaîne explicite, sans effet de bord sur un champ vide

---

## [1.2.3] — 2026-09-20

### Corrigé

- Le menu du plugin avait disparu de l'admin en 1.2.2 : la garde anti-redéclaration ajoutée dans cette version était placée *avant* la déclaration de classe. Or PHP lie les classes déclarées au premier niveau d'un fichier dès la compilation, avant d'exécuter la moindre instruction : `class_exists()` était donc toujours vrai et le `return` intervenait avant le `new MonClubTT_Plugin()` de fin de fichier, si bien qu'aucun hook n'était enregistré. Les gardes englobent désormais la déclaration (`if (!class_exists()) { class … }`), seul emplacement qui protège réellement — c'est déjà l'idiome utilisé par `AccesFFTTApi` et `ParametresPlugin`. Même correction sur les six modèles concernés, où la garde était inopérante

---

## [1.2.2] — 2026-09-20

### Corrigé

- La date de « Dernière synchronisation » (widget tableau de bord et page de réglages) ainsi que toutes les dates de « Dernière mise à jour » du cache s'affichaient en UTC et non dans le fuseau horaire du site : décalage de -2h à Paris en été, -1h en hiver. Le formatage passe désormais par `wp_date()`, qui applique l'option « Fuseau horaire » de WordPress (changement d'heure inclus)
- Le délai « il y a X » affiché sous la dernière synchronisation était gonflé du même décalage (`human_time_diff()` comparait un horodatage UTC à un horodatage déjà décalé)

### Modifié

- Toutes les classes et fonctions du plugin sont protégées contre la redéclaration (`class_exists` / `function_exists`) : une seconde copie du plugin présente sur l'installation ne provoque plus d'erreur fatale. Les gardes obsolètes de `MonClubTT_Joueur` et `MonClubTT_Joueurs`, restées sur les anciens noms de classes après le renommage, sont corrigées

---

## [1.2.1] — 2026-09-18

### Corrigé

- La création/suppression en masse des pages d'équipe depuis l'écran admin « Les équipes » ne faisait rien silencieusement : les données d'équipe étaient mal sanitizées (`sanitize_text_field()` appliqué sur des sous-tableaux entiers au lieu de chaque champ), ce qui vidait `iddiv` et faisait ignorer chaque ligne sans remonter d'erreur

---

## [1.2.0] — 2026-07-06

### Corrigé

- Certaines équipes (ex. 2, 3, 4) n'apparaissaient plus dans la synchronisation : les équipes sont désormais dédupliquées par poule (iddiv/idpoule) et non par nom, ce qui évitait qu'une même équipe engagée dans plusieurs épreuves s'écrase elle-même

### Ajouté

- Message clair « Poule indisponible » (avec illustration) affiché lorsque la fédération retire les poules de son API (fin de saison, entre deux phases), au lieu d'un bloc vide
- Le listing des joueurs est trié par défaut par points officiels décroissants

---

## [1.1.0] — 2026-07-03

### Modifié

- Les joueurs sans points mensuels (0) sont désormais exclus de la synchronisation manuelle et des listes front/admin

---

## [1.0.0] — 2026-05-30

Première version stable. Refonte complète du plugin original.

### Ajouté

- **Feuilles de match** — clic sur un résultat pour afficher composition + résultats partie par partie (chargement AJAX, cache 7 jours)
- **Génération automatique de pages WordPress** par équipe depuis l'admin (corbeille réversible, bidirectionnel)
- **Filtre sénior championnat** — seules les équipes de championnat sénior par équipes sont affichées
- **Synchronisation manuelle** avec logs d'API en temps réel dans l'admin
- **Widget tableau de bord** avec bouton de synchronisation rapide
- **Badges de progression** mensuelle et annuelle sur la liste des joueurs
- **Design tableau unifié** — classement, résultats et joueurs partagent le même style
- **Cache transients** avec durées adaptées à chaque type de données
- **Hooks WordPress** pour exposer les données aux autres plugins (`monclubtt_get_joueurs`, `monclubtt_get_equipes`, `monclubtt_get_classement_poule`, `monclubtt_get_rencontres_poule`)
- **AJAX public** pour les feuilles de match (visiteurs non connectés)

### Corrigé

- Shortcodes `[equipe iddiv="" idpoule=""]` vides à cause de sections CDATA non parsées (`LIBXML_NOCDATA`)
- Appels API N+1 remplacés par un seul appel `xml_licence_b.php` par club
- Encodage ISO-8859-1 / UTF-8 des réponses XML FFTT

### Technique

- PHP ≥ 7.4, WordPress ≥ 5.0
- API FFTT Smartping 2.0 (`www.fftt.com`)
- Auth HMAC-SHA1
- jQuery + tablesorter pour le front
