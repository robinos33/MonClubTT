# Référence API FFTT Smartping

Endpoints utilisés par MonClubTT. Tous les appels passent par `AccesFFTTApi::getData()` qui gère l'authentification HMAC-SHA1 automatiquement.

Base URL : `https://www.fftt.com/mobile/pxml/`

---

## Authentification

Chaque requête requiert 4 paramètres supplémentaires :

| Paramètre | Description |
|-----------|-------------|
| `id` | Identifiant application (fourni par la FFTT) |
| `serie` | Serial de session (alphanumérique, généré par le plugin) |
| `tm` | Timestamp `YmdHis` + 3 chiffres millisecondes |
| `tmc` | `hash_hmac('sha1', tm, md5(appKey))` |

---

## Endpoints utilisés

### Joueurs

#### `xml_licence_b.php` — Licenciés d'un club
```
GET xml_licence_b.php?numclu={numclub}
```
Retourne la liste complète des licenciés avec classement, progression, catégorie.

Champs utiles : `licence`, `nom`, `prenom`, `sexe`, `cat`, `etranger`, `point`, `apoint`, `valinit`, `natio`

---

#### `xml_joueur.php` — Détail d'un joueur
```
GET xml_joueur.php?licence={licence}&auto=1
```
Retourne les informations détaillées d'un joueur par son numéro de licence.

---

### Équipes

#### `xml_equipe.php` — Équipes d'un club
```
GET xml_equipe.php?numclu={numclub}&type={type}
```
`type` : `M` (masculin) ou `F` (féminin)

Champs utiles : `libequipe`, `libepr`, `libdivision`, `liendivision` (contient `D1` et `cx_poule`)

---

### Compétitions

#### `xml_chp_renc.php` — Classement d'une poule
```
GET xml_chp_renc.php?D1={iddiv}&cx_poule={idpoule}&action=classement
```
Retourne le classement de la poule avec : `clt`, `equipe`, `joue`, `pts`.

---

#### `xml_result_equ.php` — Résultats d'une poule par journée
```
GET xml_result_equ.php?D1={iddiv}&cx_poule={idpoule}&action=res
```
Retourne les rencontres groupées par journée avec : `libelle`, `equa`, `equb`, `scorea`, `scoreb`, `lien`.

Le champ `lien` contient une query string `renc_id=XXX&is_retour=Y`.

---

#### `xml_chp_renc.php` — Feuille de match
```
GET xml_chp_renc.php?renc_id={rencId}&is_retour={0|1}
```
Retourne le détail complet d'une rencontre :

```json
{
  "resultat": { "equa": "...", "equb": "...", "resa": "10", "resb": "4" },
  "joueur": [
    { "xja": "NOM Prénom", "xca": "M 1568pts", "xjb": "NOM Prénom", "xcb": "M 1655pts" }
  ],
  "partie": [
    { "ja": "NOM Prénom", "scorea": "1", "jb": "NOM Prénom", "scoreb": "-", "detail": "11 -08 11 09" }
  ]
}
```

`scorea`/`scoreb` : `"1"` = victoire, `"-"` = défaite  
`detail` : scores des sets séparés par des espaces, négatif = set perdu

---

## Format des réponses

L'API retourne du XML (ISO-8859-1 ou UTF-8). Le plugin convertit automatiquement en tableau PHP via :
```php
json_decode(json_encode(simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA)), true)
```

---

## Ressources officielles

- [Spécifications techniques API Smartping](https://www.fftt.com/site/mediatheque/autres-medias/api)
- [Page API FFTT](https://www.fftt.com/site/medias/shares_files/informatique-specifications-techniques-api-smartping-720.pdf)
