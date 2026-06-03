# Architecture de MonClubTT

## Structure réelle du projet

```
MonClubTT/
├── mon-club-tt.php       # Point d'entrée : registration WP, shortcodes, AJAX handlers
├── Utils.php             # Autoloader PSR-like (require_once sur chaque classe)
│
├── models/               # Couche domaine + infrastructure
│   ├── AccesFFTTApi.php  # Client API FFTT (auth HMAC-SHA1, cache transients, logs)
│   ├── Equipes.php       # Collection d'équipes + filtres (senior, championnat…)
│   ├── Equipe.php        # Modèle équipe (getters)
│   ├── Joueurs.php       # Collection de joueurs
│   ├── Joueur.php        # Modèle joueur (getters)
│   ├── Classement.php    # Modèle classement (points, progressions)
│   ├── ParametresPlugin.php  # Accès aux options WP (ID app, mdp, num club)
│   └── ...
│
├── views/
│   ├── admin/
│   │   ├── admin.php     # Page paramètres + sync + logs API
│   │   ├── equipes.php   # Gestion des pages d'équipe (checkboxes + génération)
│   │   ├── joueurs.php   # Liste des licenciés
│   │   └── header.php
│   └── front/
│       ├── equipes.php   # Shortcode [equipe] — classement + résultats + feuilles
│       ├── joueurs.php   # Shortcode [joueurs] — tableau trié
│       └── header.php
│
└── assets/
    ├── mon-club-tt.css      # Styles front + admin
    ├── mon-club-tt.js       # Interactions : tri tableau, feuilles de match AJAX
    └── tablesorter/      # Bibliothèque jQuery tablesorter
```

## Flux de données

### Synchronisation (admin)

```
Clic "Synchroniser"
    → AJAX POST wp_ajax_monclubtt_sync
    → AccesFFTTApi : effacement cache + appels API FFTT
    → Stockage transients WordPress (8h)
    → Réponse JSON (compteurs + logs)
```

### Affichage public (shortcodes)

```
[joueurs] ou [equipe iddiv=X idpoule=Y]
    → MonClubTT::joueurs_front() / equipes_front()
    → AccesFFTTApi : lecture transients (ou appel API si cache expiré)
    → require views/front/joueurs.php | equipes.php
```

### Feuilles de match (AJAX public)

```
Clic sur une ligne de résultat (front)
    → AJAX POST wp_ajax_nopriv_monclubtt_feuille_match
    → AccesFFTTApi::getRencontreDetail() — cache 7 jours
    → Réponse JSON → rendu HTML côté client (mon-club-tt.js)
```

## Couche cache

Toutes les données transitent par les **transients WordPress** (`wp_options`) :

| Clé (préfixe `monclubtt_*`) | Durée | Données |
|----------------------------|-------|---------|
| `joueurs_club` | Jusqu'à sync manuelle | Licenciés du club |
| `poule_classement` | 8 h | Classement d'une poule |
| `poule_rencontres` | 8 h | Résultats par journée |
| `renc_detail` | 7 jours | Feuille de match (résultats passés) |

## Hooks WordPress exposés

Voir [README.md](../README.md#hooks-pour-développeurs) pour les `apply_filters` disponibles.

## Authentification API FFTT

Chaque requête à l'API Smartping requiert :
- `id` : identifiant application
- `serie` : serial de session (généré en début de session)
- `tm` : timestamp au format `YmdHis` + 3ms
- `tmc` : `hash_hmac('sha1', tm, md5(appKey))`

Voir `AccesFFTTApi::getData()` et `AccesFFTTApi::generateSerial()`.

## Pistes d'évolution

L'architecture actuelle (procédurale + MVC basique) est fonctionnelle mais pourrait évoluer vers :
- Séparation Domain / Infrastructure (DDD) — voir [TDD.md](TDD.md)
- Injection de dépendances plutôt que singleton `AccesFFTTApi::getInstance()`
- Tests unitaires sur les modèles (PHPUnit est déjà configuré)
