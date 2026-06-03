# Contribuer à MonClubTT

Merci de l'intérêt pour ce plugin ! Les contributions sont les bienvenues.

> **Note** : je suis un développeur débutant en termes de maintenance open source. Je ferai de mon mieux pour répondre aux issues et reviewer les PRs, mais la réactivité peut varier. Soyez indulgents et n'hésitez pas à relancer si vous n'avez pas de retour après quelques jours.

---

## Signaler un bug ou proposer une idée

Ouvrez une [issue GitHub](https://github.com/robinos33/MonClubTT/issues) en précisant :

- **Bug** : étapes pour reproduire, comportement attendu vs observé, version de WordPress et PHP
- **Suggestion** : contexte d'utilisation, cas concret que ça résoudrait

Pas besoin de formalisme excessif — une description claire suffit.

---

## Soumettre une Pull Request

1. **Forker** le dépôt et créer une branche depuis `master`
   ```bash
   git checkout -b fix/mon-correctif
   ```

2. **Faire les modifications** en respectant les conventions du projet :
   - PHP : indentation 4 espaces, style proche du code existant
   - Commits au format [Conventional Commits](https://www.conventionalcommits.org/) :
     `type(scope): description` — ex. `fix(equipes): corriger l'affichage du score`
   - Types : `feat`, `fix`, `style`, `refactor`, `docs`, `chore`

3. **Tester** sur une installation WordPress locale avant de soumettre

4. **Ouvrir la PR** avec une description de ce qui change et pourquoi

---

## Structure du projet

```
MonClubTT/
├── mon-club-tt.php       # Point d'entrée : shortcodes, hooks, AJAX handlers
├── Utils.php             # Autoloader des classes
├── models/
│   ├── AccesFFTTApi.php  # Client API FFTT (auth HMAC-SHA1, cache transients)
│   ├── Equipes.php       # Collection d'équipes + filtres
│   ├── Joueurs.php       # Collection de joueurs
│   ├── Equipe.php        # Modèle équipe
│   ├── Joueur.php        # Modèle joueur
│   └── ...
├── views/
│   ├── admin/            # Templates administration
│   └── front/            # Templates publics (joueurs, équipes)
└── assets/
    ├── mon-club-tt.css       # Styles front et admin
    └── mon-club-tt.js        # Interactions (tri, feuilles de match AJAX)
```

---

## Ce qui serait utile

Quelques idées si vous cherchez par où commencer :

- Tests unitaires (PHPUnit est configuré mais la couverture est faible)
- Support multisite WordPress
- Internationalisation (i18n) des chaînes
- Amélioration de l'accessibilité des tableaux
- Pagination de la liste des joueurs

---

## Questions

Pour toute question, ouvrez une [issue GitHub](https://github.com/robinos33/MonClubTT/issues).
