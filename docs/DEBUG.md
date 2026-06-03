# Guide de diagnostic - Problème de synchronisation MonClubTT

## Symptôme
Les listings de joueurs et équipes sont vides après synchronisation.

## Architecture du système

MonClubTT utilise **WordPress Transients** (cache temporaire) au lieu d'une base de données persistante :
- Les données sont stockées dans `wp_options` via `set_transient()`
- Le cache expire toutes les 8 heures (8h00, 13h00, lendemain 8h00)
- La synchronisation est **manuelle** via un bouton dans l'admin WordPress
- Aucune synchronisation automatique (pas de cronjob ni GitHub Actions)

## Étapes de diagnostic

### 1. Vérifier la configuration API FFTT

Dans WordPress Admin > MonClubTT :
- **ID Application** : doit être fourni par la FFTT (format: `AM001`)
- **Mot de passe** : clé API fournie par la FFTT
- **Numéro de club** : numéro du club à synchroniser

⚠️ **Si l'un de ces paramètres est vide, la synchronisation échouera immédiatement**

### 2. Lancer la synchronisation manuelle

1. Aller dans WordPress Admin > MonClubTT
2. Cliquer sur "Synchroniser les données"
3. Observer le message de retour :
   - ✅ **Succès** : affiche le nombre de joueurs et équipes récupérés
   - ❌ **Erreur** : affiche le message d'erreur détaillé

### 3. Vérifier la console du navigateur

Ouvrir la console développeur (F12) et observer :
- `MonClubTT Sync - Résultats:` → montre les nombres de joueurs/équipes
- `MonClubTT Sync - Debug:` → logs détaillés du processus

**Exemples de logs attendus :**
```
MonClubTT Sync - Debug: [
  "Numéro de club: 08350194",
  "ID Application: AM001",
  "Cache joueurs effacé",
  "Joueurs récupérés: 25",
  "Cache équipes effacé",
  "Équipes M récupérées: 3",
  "Équipes F récupérées: 1"
]
```

### 4. Vérifier les logs d'erreur PHP

Les erreurs d'API sont maintenant loggées dans les logs PHP de WordPress :

```bash
# Localiser le fichier de logs WordPress
# Généralement dans wp-content/debug.log
tail -f wp-content/debug.log | grep "MonClubTT"
```

**Types d'erreurs possibles :**
- `MonClubTT - Erreur cURL` : problème de connexion réseau
- `MonClubTT - Code HTTP XXX` : l'API FFTT a retourné une erreur
- `MonClubTT - RÉPONSE VIDE de l'API FFTT` : **l'API retourne du vide (identifiants probablement invalides)**
- `MonClubTT - Erreur parsing XML` : la réponse de l'API est invalide (sauf pour xml_initialisation.php qui est ignorée)

### 5. Cas d'erreur courants

| Erreur | Cause | Solution |
|--------|-------|----------|
| `Numéro de club non configuré` | Paramètre manquant | Remplir le numéro de club dans l'admin |
| `Identifiants API FFTT non configurés` | ID/Mot de passe vide | Demander les identifiants à la FFTT |
| `RÉPONSE VIDE de l'API FFTT` | **Identifiants API invalides** | **Vérifier l'ID application et le mot de passe** auprès de la FFTT |
| `Aucune donnée récupérée` | Identifiants invalides ou club inexistant | Vérifier les identifiants API et le numéro de club |
| `0 joueurs récupérés` | Club inexistant ou API non configurée | Vérifier le numéro de club |
| `Erreur cURL (6): Could not resolve host` | Problème réseau | Vérifier la connexion internet du serveur |
| `Code HTTP 401` | Identifiants invalides | Vérifier l'ID et le mot de passe API |
| `Code HTTP 500` | Erreur serveur FFTT | Réessayer plus tard |
| `Erreur parsing XML - xml_initialisation.php` | L'API retourne une réponse vide | **Normal** - Cette erreur est désormais ignorée automatiquement |

## Flux de synchronisation

```
1. Utilisateur clique sur "Synchroniser"
   ↓
2. Vérification des paramètres (club, ID app, mot de passe)
   ↓
3. Effacement du cache existant (delete_transient)
   ↓
4. Appel API FFTT pour récupérer :
   - Liste des licenciés du club
   - Pour chaque licencié : données licence + classement
   - Équipes masculines et féminines
   - Pour chaque équipe : classement et rencontres
   ↓
5. Stockage dans les transients WordPress (set_transient)
   ↓
6. Enregistrement du timestamp de sync
   ↓
7. Retour JSON avec résultats + logs debug
```

## Fichiers modifiés pour le debug

### `MonClubTT.php` (lignes 285-343)
- Ajout de vérification des paramètres avant synchronisation
- Logs détaillés à chaque étape
- Retour des informations de debug dans la réponse AJAX

### `models/AccesFFTTApi.php` (lignes 364-398)
- Capture des erreurs cURL (errno, message, HTTP code)
- Logs d'erreurs dans error_log
- Validation du parsing XML avec détails de l'erreur

### `views/admin/admin.php` (lignes 60-84)
- Affichage du nombre de joueurs/équipes dans le message de succès
- Logs console.log pour le debug navigateur
- Logs console.error en cas d'erreur

## Script de test automatique

Un script de test a été créé pour diagnostiquer rapidement les problèmes :

```bash
# Depuis le navigateur, accéder à :
http://votresite.com/wp-content/plugins/MonClubTT/test-api-fftt.php
```

Ce script affichera :
- Les paramètres configurés (club, ID app, mot de passe masqué)
- Le nombre de licenciés récupérés
- Le nombre d'équipes M/F récupérées
- Les détails du premier résultat de chaque catégorie
- Les instructions pour consulter les logs

## Logs PHP détaillés

Maintenant, chaque appel API est loggé avec :
- L'URL complète appelée (avec tous les paramètres)
- Les erreurs cURL éventuelles
- Les codes HTTP retournés
- Le contenu de la réponse (premiers 500 caractères si erreur)
- Le nombre de résultats retournés par chaque méthode

Pour consulter les logs en temps réel :

```bash
# Sur le serveur WordPress
tail -f /var/log/apache2/error.log | grep MonClubTT

# Ou selon la config PHP
tail -f /var/log/php_errors.log | grep MonClubTT

# Dans wp-content
tail -f wp-content/debug.log | grep MonClubTT
```

**Exemple de logs attendus lors d'une synchronisation réussie :**
```
[07-Jan-2026 10:30:15] MonClubTT - Appel API: http://www.fftt.com/mobile/pxml/xml_liste_joueur.php?club=10330011&serie=ABC123&id=SX059&tm=20260107103015123&tmc=a1b2c3d4e5f6...
[07-Jan-2026 10:30:16] MonClubTT - getLicencesByClub(10330011) - getData result: {"joueur":[{"licence":"1234567",...}]}
[07-Jan-2026 10:30:16] MonClubTT - getLicencesByClub(10330011) - getCollection result count: 25
```

**Exemple de logs en cas d'erreur :**
```
[07-Jan-2026 10:30:15] MonClubTT - Appel API: http://www.fftt.com/mobile/pxml/xml_liste_joueur.php?club=10330011&serie=ABC123&id=SX059&tm=20260107103015123&tmc=a1b2c3d4e5f6...
[07-Jan-2026 10:30:16] MonClubTT - Code HTTP 401 - URL: http://www.fftt.com/mobile/pxml/xml_liste_joueur.php?club=...
[07-Jan-2026 10:30:16] MonClubTT - Erreur parsing XML - URL: http://www.fftt.com/mobile/pxml/xml_liste_joueur.php?club=... - Data: <?xml version="1.0"?><error>Identifiants invalides</error>
[07-Jan-2026 10:30:16] MonClubTT - getLicencesByClub(10330011) - getData result: boolean
[07-Jan-2026 10:30:16] MonClubTT - getLicencesByClub(10330011) - getCollection result count: 0
```

## Test rapide manuel

Pour tester l'API FFTT manuellement depuis la ligne de commande :

```bash
# Les paramètres seront visibles dans les logs après une synchro
# Copiez l'URL complète depuis les logs et testez-la :
curl "http://www.fftt.com/mobile/pxml/xml_liste_joueur.php?club=10330011&serie=ABC&id=SX059&tm=20260107103015123&tmc=xxx"
```

Si cela retourne du XML valide avec des joueurs, l'API fonctionne. Sinon :
- `<error>` dans le XML → identifiants invalides
- Timeout → problème réseau
- HTTP 4xx/5xx → erreur serveur FFTT
