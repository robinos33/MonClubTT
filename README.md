# MonClubTT

Plugin WordPress non-officiel pour afficher les données d'un club issues de l'[API Smartping](https://www.fftt.com) de la Fédération Française de Tennis de Table (FFTT).

> **Non affilié à la FFTT.** Plugin gratuit, maintenu bénévolement.

---

## Fonctionnalités

### Côté public

- **Liste des joueurs** — tableau trié par classement, avec badges de progression mensuelle et annuelle, filtrable par sexe (H / F / mixte)
- **Page d'équipe** — classement de poule mis en évidence + résultats de championnat organisés par journée
- **Feuilles de match** — au clic sur un résultat, la composition des deux équipes et le détail partie par partie s'affichent (chargement AJAX, mis en cache)

### Côté administration

- **Synchronisation manuelle** des données (joueurs + équipes) avec logs d'API en temps réel
- **Widget tableau de bord** avec bouton de synchronisation rapide
- **Gestion des équipes** — liste des équipes de championnat sénior, génération / suppression automatique des pages WordPress correspondantes (corbeille réversible)
- **Vue joueurs** — liste complète des licenciés du club
- **Réseaux sociaux** — visuels prêts à poster (Top Progression, nouveaux paliers de points, et pour le dernier week-end de championnat : résultats des équipes, top perfs, cartons pleins, victoires à la belle), générés dans le navigateur et téléchargeables en PNG carré ou story, avec un texte de publication pré-rempli

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

### Page d'équipe

```
[monclubtt_equipe iddiv="198511" idpoule="1140384"]
```

Les valeurs `iddiv` et `idpoule` sont générées automatiquement dans *MonClubTT → Équipes*.  
Copier le shortcode affiché dans le tableau et le coller dans la page WordPress souhaitée.

### Jeu de pong

> **Démo** : [ouvrir dans WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/robinos33/MonClubTT/claude/pong-game-club-players-kygatf/demo/playground/blueprint.json) — WordPress complet dans le navigateur, club fictif, photos et réglages (dossier `demo/playground/`, exclu du ZIP).

```
[monclubtt_pong]
```

Le visiteur choisit son joueur parmi les licenciés (photo détourée avec liseré blanc, sinon avatar dessiné), puis choisit son adversaire : un joueur du top 10 mondial messieurs ou dames, un autre licencié du club, ou « Au hasard », sur une table aux couleurs du club. La liste des 20 adversaires (nom écrit comme sur le site de la WTT, affiché tel quel, + photo détourée de la médiathèque) se règle dans *Mon Club TT › Réglages* ; elle est pré-remplie avec le classement mondial de la semaine 40 de 2026 et doit être tenue à jour à la main. Score sur un marqueur à fiches, match en une manche de 11 points par défaut, service alterné tous les 2 points. Au doigt sur mobile, à la souris ou aux flèches + espace sur ordinateur.

| Attribut | Valeurs | Défaut | Description |
|----------|---------|--------|-------------|
| `adversaire` | texte | — | Adversaire joué directement après le choix du joueur (sans écran de choix) ; « Changer d'adversaire » donne ensuite accès au top 10 et au club |
| `adversaire_titre` | texte | — | Sous-titre affiché sur l'écran de sélection |
| `adversaire_photo` | ID de média ou URL | — | Photo détourée (PNG transparent) de l'adversaire ; à défaut, avatar dessiné |
| `adversaire_sexe` | `M`, `F` | `M` | Avatar utilisé pour le corps |
| `manches` | `1`, `3`, `5` | `1` | Nombre de manches du match (en une manche, le marqueur n'affiche que les points) |

**Meilleurs scores** : les 10 meilleures victoires (contre le top 10 mondial ou un licencié) sont affichées sous la sélection des joueurs, classées par niveau (Expert d'abord), puis écart de points. Commun à tous les visiteurs ; les scores étant déclarés par le navigateur, une case « Vider le tableau » est disponible dans les réglages.

**Partage** : en fin de match, Facebook, X et WhatsApp partagent un lien vers la page du jeu (`?pong=…`) qui porte les balises Open Graph du match ; l'image (1200×630, joueurs, marqueur, résultat, couleurs du club) est dessinée par le serveur avec GD (police Liberation Sans, licence SIL OFL, dans `assets/fonts/`) et mise en cache dans `wp-content/uploads/monclubtt-pong/` (300 derniers matchs). L'image et la description invitent à jouer, et le lien partagé ouvre le jeu sur un **défi** (bandeau « Relever le défi » : même adversaire, même niveau, verdict en fin de partie). Instagram n'ayant pas de lien de partage web, le bouton utilise le partage natif du téléphone (image en fichier), sinon ouvre l'image à enregistrer. Sans GD, le logo du club sert d'image. Une extension SEO (Yoast, Rank Math…) qui ajoute ses propres balises Open Graph peut entrer en concurrence avec celles du match.

Le plugin ne fournit aucune photo de joueur professionnel : n'utilisez que des images dont le club a les droits, par exemple des photos de Wikimedia Commons sous licence libre. La **légende** de l'image dans la médiathèque sert de crédit, affiché sur l'écran de sélection et sous le jeu (auteur et licence obligatoires pour Commons).

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
```

---

## Prérequis

- WordPress ≥ 5.0
- PHP ≥ 7.4
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
