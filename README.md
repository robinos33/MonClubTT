# MonClubTT

Plugin WordPress non-officiel pour afficher les données d'un club issues de l'[API Smartping](https://www.fftt.com) de la Fédération Française de Tennis de Table (FFTT).

> **Non affilié à la FFTT.** Plugin gratuit, maintenu bénévolement.

---

## Fonctionnalités

### Côté public

- **Liste des joueurs** — tableau trié par classement, avec badges de progression mensuelle et annuelle, filtrable par sexe (H / F / mixte)
- **Page d'équipe** — classement de poule mis en évidence + résultats de championnat organisés par journée
- **Feuilles de match** — au clic sur un résultat, la composition des deux équipes et le détail partie par partie s'affichent (chargement AJAX, mis en cache)
- **Calendrier du club** — agenda de la saison, prochain rendez-vous d'une série (tour du Grand Prix, stage…) qui avance tout seul, listes de dates, abonnement des adhérents dans leur agenda
- **Application de gestion des équipes** (staff, connexion requise, installable sur mobile) — disponibilités des joueurs, compositions par journée, effectifs de phase, contrôle du brûlage, SMS pré-rédigés, liens magiques pour que chaque joueur donne ses disponibilités sans compte

### Côté administration

Menu *Mon Club TT* :

- **Données FFTT** — onglets *Joueurs* (licenciés, photos), *Équipes* (génération / suppression automatique des pages d'équipe, corbeille réversible) et *Connexion* (identifiants de l'API, synchronisation, logs de l'API activables pour diagnostiquer un problème)
- **Gestion des équipes** — réglages de l'application du staff : PWA, SMS, équipes et dates des journées, joueurs et rencontres jouées (importées des feuilles de match FFTT), règles de brûlage, import / export
- **Widget tableau de bord** avec bouton de synchronisation rapide
- **Calendrier** — événements saisis à la main ou importés du tableur de la saison (CSV ou JSON), rangés par série et par catégorie
- **Pong**, **Réglages** (nom, logo et couleurs du club)
- **Réseaux sociaux** — visuels prêts à poster (Top Progression, nouveaux paliers de points, et pour le dernier week-end de championnat : résultats des équipes, top perfs, cartons pleins, victoires à la belle), générés dans le navigateur et téléchargeables en PNG portrait 4:5 (1080 × 1350, affiché en entier dans le fil Instagram et Facebook), avec un texte de publication pré-rempli

---

## Installation

1. Cloner ou télécharger ce dépôt dans `wp-content/plugins/MonClubTT/`
2. Activer le plugin dans *Extensions → Extensions installées*
3. Renseigner les identifiants API dans *MonClubTT → Paramètres* :
   - **ID Application** et **Mot de passe** fournis par la FFTT
   - **Numéro de club** (8 chiffres, ex. `10330011`)
4. Lancer une première synchronisation via le bouton *Synchroniser les données*

---

## Shortcodes

### Liste des joueurs

```
[monclubtt_joueurs type="MF"]
```

| Attribut | Valeurs | Défaut | Description |
|----------|---------|--------|-------------|
| `type` | `M`, `F`, `MF` | `MF` | Sexe affiché |

Au-dessus de la liste, le podium Top Progression montre les 3 plus fortes progressions du mois ou de la saison : joueurs dessinés en pose de victoire, aux couleurs du club, avec leur photo détourée. Les têtes suivent le curseur (ou le doigt posé sur le podium) et reviennent à leur pose après 2,5 s sans mouvement ; elles restent fixes si l'appareil demande de réduire les animations.

### Page d'équipe

```
[monclubtt_equipe iddiv="198511" idpoule="1140384"]
```

Les valeurs `iddiv` et `idpoule` sont générées automatiquement dans *MonClubTT → Équipes*.  
Copier le shortcode affiché dans le tableau et le coller dans la page WordPress souhaitée.

### Calendrier du club

Les dates du club vivent à un seul endroit, *Mon Club TT › Calendrier*. Chaque page qui affiche une date la lit avec un shortcode : quand un tour ou un stage est passé, le suivant s'affiche tout seul.

**Saisie.** Chaque saison, importer le tableur dans *Mon Club TT › — Importer* (CSV exporté du tableur, séparateur `;` ou `,`, ou le JSON de l'ancien calendrier), puis compléter les événements dans l'admin (lieu, lien d'inscription, série). Colonnes reconnues sur la première ligne : `debut` et `titre` obligatoires ; `fin`, `categorie`, `serie`, `heure`, `heure fin`, `lieu`, `lien`, `note`, `source` facultatives. Dates `28/11/2026` ou `2026-11-28`. Un nouvel import met à jour les événements déjà importés (reconnus par `source`, sinon par date et intitulé) sans effacer le lieu, le lien ou la note saisis dans l'admin si le tableur ne les renseigne pas. Les événements ajoutés à la main ne sont jamais touchés par un import.

**Séries.** Une série regroupe les rendez-vous qui reviennent (« Grand Prix du Conseil départemental », « Stage Multisports »…). Son *slug* (*Mon Club TT › — Séries*) sert dans les shortcodes. **Saison** : chaque événement appartient à une saison (choisie à l'import), ce qui permet de ranger les stages d'août dans la saison qui se termine.

```
[monclubtt_calendrier]
```

Agenda de la saison du prochain rendez-vous : vue mois ou liste, filtres par catégorie, choix d'une période, détail d'un rendez-vous, téléchargement `.ics` et **abonnement** (Apple Calendrier, Outlook, Google Agenda) qui suit les changements du club.

| Attribut | Valeurs | Défaut | Description |
|----------|---------|--------|-------------|
| `saison` | année de début (`2026`) | saison du prochain rendez-vous | Saison affichée |
| `categorie` | noms ou slugs, séparés par des virgules | — | Restreindre à certaines catégories |
| `serie` | slugs, séparés par des virgules | — | Restreindre à certaines séries |

```
[monclubtt_prochain serie="grand-prix-du-conseil-departemental" libelle="Tour %n"]
```

Prochain rendez-vous (ou celui en cours) d'une série ou d'une catégorie : encadré avec date, lieu, note et lien. En `format="texte"`, renvoie un seul champ, à glisser dans une phrase ou un bloc HTML :

```
Prochain tour : Tour [monclubtt_prochain serie="grand-prix-du-conseil-departemental" format="texte" champ="numero"] : [monclubtt_prochain serie="grand-prix-du-conseil-departemental" format="texte"]
→ Prochain tour : Tour 1 : 28 et 29 novembre

Horaires du [monclubtt_prochain serie="grand-prix-du-conseil-departemental" format="texte" champ="ordinal"] tour
→ Horaires du 1er tour

<a href="[monclubtt_prochain serie="grand-prix-du-conseil-departemental" format="texte" champ="lien"]">S'inscrire</a>
```

| Attribut | Valeurs | Défaut | Description |
|----------|---------|--------|-------------|
| `serie` | slugs | — | Série(s) concernée(s) |
| `categorie` | noms ou slugs | — | Ou une catégorie |
| `libelle` | texte avec `%n` (rang dans la série), `%t` (titre), `%s` (série) | titre | Libellé affiché |
| `format` | `bloc`, `texte` | `bloc` | Encadré ou texte seul |
| `champ` | `periode`, `libelle`, `titre`, `numero`, `ordinal`, `heure`, `lieu`, `lien`, `note`, `debut`, `fin` | `periode` | Champ renvoyé en `format="texte"` |
| `annee` | `0`, `1` | `0` | Ajouter l'année à la date |
| `vide` | texte | `Dates à venir` | Texte quand il n'y a plus de date |

```
[monclubtt_dates serie="grand-prix-du-conseil-departemental" libelle="Tour %n"]
[monclubtt_dates serie="stage-multisports,stage-perfectionnement" titres="Multisports,Perfectionnements"]
```

Dates de la saison d'une série : cartes avec la prochaine mise en avant et les passées grisées. Avec plusieurs séries, tableau par mois (une colonne par série), le prochain rendez-vous de chaque série en avant.

| Attribut | Valeurs | Défaut | Description |
|----------|---------|--------|-------------|
| `serie` | slugs, séparés par des virgules | — | Série(s) |
| `categorie` | noms ou slugs | — | Ou une catégorie |
| `saison` | année de début | saison du prochain rendez-vous | Saison affichée |
| `libelle` | comme `[monclubtt_prochain]` | titre | Libellé des cartes |
| `titres` | textes séparés par des virgules | noms des séries | En-têtes des colonnes du tableau |
| `grouper` | `auto`, `liste`, `mois` | `auto` | Cartes, ou tableau par mois (auto : tableau dès deux séries) |
| `passes` | `afficher`, `masquer` | `afficher` | Dates passées grisées ou masquées |
| `annee` | `0`, `1` | `0` | Ajouter l'année aux dates |

**Flux d'abonnement** : `https://votre-site/?monclubtt_calendrier=ics` (paramètres facultatifs `saison`, `categorie`, `serie`, `du`, `au`, `evenement`, `telecharger=1`).

### Jeu de pong

> **Démo** : [ouvrir dans WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/robinos33/MonClubTT/master/demo/playground/blueprint.json) — WordPress complet dans le navigateur, club fictif, photos et réglages (dossier `demo/playground/`, exclu du ZIP).

```
[monclubtt_pong]
```

Le visiteur choisit son joueur parmi les licenciés (photo détourée avec liseré blanc, sinon avatar dessiné), puis choisit son adversaire : un joueur du top 10 mondial messieurs ou dames, un autre licencié du club, ou « Au hasard », sur une table aux couleurs du club. Les joueurs sont dessinés en position de match (attente, coup droit, revers à chaque frappe), raquette en main, au maillot du club. La liste des 20 adversaires (nom écrit comme sur le site de la WTT, affiché tel quel, photo détourée de la médiathèque et pays, qui habille le joueur aux couleurs de sa sélection) se règle dans *Mon Club TT › Pong* ; elle est pré-remplie avec le classement mondial de la semaine 40 de 2026 et doit être tenue à jour à la main. Score sur un marqueur à fiches, match en une manche de 11 points par défaut, service alterné tous les 2 points. Au doigt sur mobile, à la souris ou aux flèches + espace sur ordinateur.

| Attribut | Valeurs | Défaut | Description |
|----------|---------|--------|-------------|
| `adversaire` | texte | — | Adversaire joué directement après le choix du joueur (sans écran de choix) ; « Changer d'adversaire » donne ensuite accès au top 10 et au club |
| `adversaire_titre` | texte | — | Sous-titre affiché sur l'écran de sélection |
| `adversaire_photo` | ID de média ou URL | — | Photo détourée (PNG transparent) de l'adversaire ; à défaut, avatar dessiné |
| `adversaire_sexe` | `M`, `F` | `M` | Avatar utilisé pour le corps |
| `adversaire_pays` | code pays (`FRA`, `CHN`, `JPN`…) | — | Tenue aux couleurs de la sélection ; à défaut, couleur secondaire du club |
| `manches` | `1`, `3`, `5` | `1` | Nombre de manches du match (en une manche, le marqueur n'affiche que les points) |

**Plein écran et musique** : boutons sous la table pour passer en plein écran, couper les effets sonores et la musique. La musique de fond (boucle) se règle dans *Mon Club TT › Pong › Musique* : fichier audio de la médiathèque ou adresse directe d'un fichier MP3/OGG/M4A/WAV (pas de lien YouTube/Spotify). Les navigateurs ne la lancent qu'au premier toucher du visiteur.

**Meilleurs scores** : les 10 meilleures victoires (contre le top 10 mondial ou un licencié) sont affichées sous la sélection des joueurs, classées par niveau (Expert d'abord), puis écart de points. Commun à tous les visiteurs ; les scores étant déclarés par le navigateur, une case « Vider le tableau » est disponible dans *Mon Club TT › Pong*.

**Partage** : en fin de match, Facebook, X et WhatsApp partagent un lien vers la page du jeu (`?pong=…`) qui porte les balises Open Graph du match ; l'image (1200×630, joueurs, marqueur, résultat, couleurs du club) est dessinée par le serveur avec GD (police Liberation Sans, licence SIL OFL, dans `assets/fonts/`) et mise en cache dans `wp-content/uploads/monclubtt-pong/` (300 derniers matchs). L'image et la description invitent à jouer, et le lien partagé ouvre le jeu sur un **défi** (bandeau « Relever le défi » : même adversaire, même niveau, verdict en fin de partie). Instagram n'ayant pas de lien de partage web, le bouton utilise le partage natif du téléphone (image en fichier), sinon ouvre l'image à enregistrer. Sans GD, le logo du club sert d'image. Une extension SEO (Yoast, Rank Math…) qui ajoute ses propres balises Open Graph peut entrer en concurrence avec celles du match.

Le plugin ne fournit aucune photo de joueur professionnel : n'utilisez que des images dont le club a les droits, par exemple des photos de Wikimedia Commons sous licence libre. La **légende** de l'image dans la médiathèque sert de crédit, affiché sur l'écran de sélection et sous le jeu (auteur et licence obligatoires pour Commons).

---

## Gestion des équipes

Ancienne extension *TT Team Planner*, intégrée au plugin (`modules/gestion-equipes/`).

**Application du staff.** Une page contenant le shortcode `[tt_team_planner]` (bouton *Créer la page* dans *Gestion des équipes › Général*) est remplacée par l'application, sans le thème. Connexion WordPress requise. Installable sur l'écran d'accueil (PWA) si l'option est cochée.

**Joueurs.** Repris des données FFTT et mis à jour à chaque synchronisation de *Données FFTT*. Les informations saisies dans l'application (téléphone, e-mail, équipe habituelle, capitaine, notes) sont conservées.

**Migration depuis TT Team Planner.**

1. Désactiver TT Team Planner, **sans le supprimer** (sa suppression efface ses tables). Tant qu'il est actif, la gestion des équipes intégrée reste en pause.
2. *Gestion des équipes › Import / export* : *Importer depuis TT Team Planner* (tables encore présentes sur le site) ou importer le fichier JSON de son export (pour une migration d'un site à l'autre).
3. Vérifier dans l'application, puis supprimer TT Team Planner.

L'import remplace les données de la gestion des équipes et conserve les identifiants. Les liens magiques ne sont pas repris : en renvoyer depuis l'application. L'adresse de l'application et son shortcode ne changent pas, les mobiles où elle est installée continuent de l'ouvrir.

**Export.** *Gestion des équipes › Import / export › Exporter en JSON* : sauvegarde complète, réimportable sur ce site ou un autre.

---

## Génération automatique de pages

Dans *MonClubTT → Équipes* :

1. Cocher les équipes à publier, décocher celles à supprimer
2. Cliquer sur **Appliquer la sélection**

Le plugin crée une page parent *Équipes* et une sous-page par équipe cochée, pré-remplie avec le shortcode correct. Les pages décochées sont envoyées à la corbeille (suppression réversible).

---

## Cache

Les données sont mises en cache via les **transients WordPress** :

| Données | Durée |
|---------|-------|
| Joueurs du club | Jusqu'à la prochaine sync |
| Classement de poule | 8 h |
| Résultats par journée | 8 h |
| Feuille de match | 7 jours (résultats passés) |

---

## Hooks pour développeurs

D'autres plugins peuvent consommer les données sans appel API supplémentaire :

```php
// Joueurs (retourne un tableau d'objets Joueur)
$joueurs = apply_filters('monclubtt_get_joueurs', 'MF'); // 'M', 'F' ou 'MF'

// Équipes (retourne un tableau d'objets Equipe)
$equipes = apply_filters('monclubtt_get_equipes', 'MF');

// Classement d'une poule
$classement = apply_filters('monclubtt_get_classement_poule', null, [
    'division' => '198511',
    'poule'    => '1140384',
]);

// Rencontres d'une poule
$rencontres = apply_filters('monclubtt_get_rencontres_poule', null, [
    'division' => '198511',
    'poule'    => '1140384',
]);

// Feuille de match d'une rencontre (renc_id et is_retour se lisent dans le
// champ « lien » des rencontres) : resultat, joueur, partie — ou false
$feuille = apply_filters('monclubtt_get_feuille_rencontre', null, [
    'renc_id'   => '6595431',
    'is_retour' => 0,
]);

// Événements du calendrier (debut, fin, heure, titre, categorie, serie, lieu,
// lien, note, saison, numero…), triés par date
$stages = apply_filters('monclubtt_get_evenements', [], [
    'serie'  => 'stage-multisports', // slugs séparés par des virgules
    'saison' => '2026',              // défaut : toutes
]);
```

---

## Prérequis

- WordPress ≥ 6.4
- PHP ≥ 8.4
- Extension cURL activée
- Identifiants API FFTT valides (à demander auprès de la FFTT)

---

## Documentation

| Document | Description |
|----------|-------------|
| [CHANGELOG.md](CHANGELOG.md) | Historique des versions |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Comment contribuer |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Structure du code |
| [docs/API-FFTT.md](docs/API-FFTT.md) | Référence des endpoints FFTT utilisés |
| [docs/DEBUG.md](docs/DEBUG.md) | Diagnostic des problèmes de synchronisation |
| [docs/TDD.md](docs/TDD.md) | Guide TDD pour contribuer |

---

## Licence

[GPLv2](LICENSE)
