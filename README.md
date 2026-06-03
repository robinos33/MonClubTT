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
[joueurs type="MF"]
```

| Attribut | Valeurs | Défaut | Description |
|----------|---------|--------|-------------|
| `type` | `M`, `F`, `MF` | `MF` | Sexe affiché |

### Page d'équipe

```
[equipe iddiv="198511" idpoule="1140384"]
```

Les valeurs `iddiv` et `idpoule` sont générées automatiquement dans *MonClubTT → Équipes*.  
Copier le shortcode affiché dans le tableau et le coller dans la page WordPress souhaitée.

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
