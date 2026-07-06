# Changelog

Toutes les modifications notables de MonClubTT sont documentées ici.  
Format : [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/)

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
