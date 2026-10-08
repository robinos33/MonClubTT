# Changelog

Toutes les modifications notables de MonClubTT sont documentées ici.  
Format : [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/)

---

## [1.18.0] — 2026-10-08

### Ajouté

- Jeu de pong : compteurs « parties jouées » (chaque match lancé, « Rejouer » compris) et « parties terminées » (match allé jusqu'au bout), affichés dans les réglages avec le taux de parties terminées et la date de départ, et remise à zéro possible.

## [1.17.1] — 2026-10-07

### Modifié

- Jeu de pong : l'option visiteur passe en haut de l'écran de choix, en un lien discret sous le titre (« Pas du club ? Joue sous ton prénom », ou « Rejoue en tant que … » quand un prénom est déjà retenu) qui déplie le formulaire.
- Jeu de pong : niveau Normal plus abordable. L'adversaire se déplace moins vite, réagit plus tard, lit moins bien l'effet et vise moins juste ; balle un peu plus lente. Le niveau Expert ne change pas.

## [1.17.0] — 2026-10-07

### Ajouté

- Jeu de pong : un visiteur qui n'est pas du club peut jouer sous son prénom (20 caractères au plus), avec un avatar dessiné de joueur ou de joueuse. Son prénom est retenu sur son appareil. Il peut partager ses matchs, mais ses victoires n'entrent pas au tableau des meilleurs scores, réservé aux joueurs du club.

## [1.16.0] — 2026-10-07

### Ajouté

- Réglage « Photos des joueurs » : décoché, plus aucun visage sur le site (podium, jeu de pong et ses images de partage, visuels réseaux sociaux), avatars dessinés pour tous, adversaires du top 10 compris. La page Joueurs de l'administration montre toujours les photos, pour suivre celles qui manquent.
- Réglage « Top 10 mondial dans le jeu de pong » : décoché, on ne joue que contre les joueurs du club (et l'invité d'un shortcode).

## [1.15.1] — 2026-10-05

### Corrigé

- Visuel « Résultats des équipes » : une équipe exempte apparaissait comme un nul 0-0. Elle n'a plus ni résultat ni score, « EXEMPT » remplace l'adversaire, et elle n'entre plus dans le bilan V / N / D ni dans celui du texte de publication.

## [1.15.0] — 2026-10-05

### Modifié

- Visuels réseaux sociaux : un seul format, portrait 4:5 (1080 × 1350), affiché en entier dans le fil Instagram comme dans celui de Facebook. Les formats carré et story disparaissent ; mises en page (podium, listes) adaptées à la nouvelle hauteur.

### Corrigé

- Visuel « Résultats des équipes » : seules 7 équipes s'affichaient. 10 équipes tiennent désormais sur une colonne ; au-delà, la liste passe sur deux colonnes de cartes (adversaire sur une seconde ligne quand la place le permet), 16 équipes en tout, la dernière case annonçant « + N autres équipes » s'il en reste.

## [1.14.0] — 2026-10-05

### Modifié

- Podium Top Progression (page joueurs) et visuel réseaux sociaux du podium : nouveaux joueurs détaillés du pong, en pose de victoire (debout, poing serré, raquette levée), tête photo agrandie d'environ 25 %.
- Raquette plus ovale, dans le jeu de pong aussi.

### Ajouté

- Podium du site : les têtes suivent le curseur (ou le doigt posé sur le podium), pivotent et tournent légèrement vers lui, les pupilles des têtes dessinées suivent aussi ; retour à la pose de repos après 2,5 s sans mouvement. Têtes fixes si l'appareil demande moins d'animations.

## [1.13.0] — 2026-10-05

### Ajouté

- Jeu de pong : champ « Pays » pour chaque adversaire du top 10 (16 sélections proposées, prérempli pour le classement actuel) ; le joueur porte une tenue aux couleurs de sa sélection, inspirée du drapeau (mini-drapeau sur la poitrine). Attribut `adversaire_pays` pour l'adversaire imposé. Sans pays, couleur secondaire du club.

### Modifié

- Jeu de pong : nouveaux personnages, dessinés en position de match (jambes fléchies, contour, ombrage, maillot à liserés, chaussures) avec la raquette tenue dans le poing (plateau à bande de chant, manche évasé, rouge en coup droit, noir en revers). Pose de coup droit ou de revers à chaque frappe, tête agrandie d'environ 30 %, ombre au sol, adversaire derrière la table. Zone de frappe inchangée.
- Les réglages enregistrés avant l'arrivée du champ pays reprennent le pays du joueur par défaut de même nom.

## [1.12.0] — 2026-10-05

### Ajouté

- Jeu de pong : bouton « Plein écran » (API Fullscreen quand le navigateur la permet, sinon le jeu couvre la fenêtre, iPhone compris ; sortie par le même bouton ou Échap).
- Jeu de pong : musique de fond en boucle, réglage « Musique du jeu de pong » (fichier audio de la médiathèque ou adresse d'un fichier MP3/OGG/M4A/WAV). Elle démarre au premier toucher et se coupe avec un bouton ; le choix du visiteur (musique, effets sonores) est mémorisé sur son appareil.

### Modifié

- Jeu de pong : l'écran de jeu (marqueur, table, boutons) tient toujours dans la hauteur visible de la fenêtre (100vh, barres du navigateur mobile déduites) ; marqueur compact quand la fenêtre est basse (téléphone en paysage) ; plein écran, effets sonores et musique en boutons à pictogramme.

## [1.11.0] — 2026-10-04

### Ajouté

- Shortcode `[monclubtt_pong]` : jeu de ping vu du dessus, aux couleurs du club, jouable au doigt sur mobile, à la souris ou au clavier.
  - Choix du joueur parmi les licenciés (têtes détourées, liseré blanc de 6 px), puis de l'adversaire : top 10 mondial messieurs et dames, joueurs du club ou au hasard ; niveaux Normal et Expert. Attribut `adversaire` pour imposer un invité.
  - Raquette = personnage du podium en pose de jeu ; balle qui accélère à chaque frappe et prend une courbe selon le déplacement de la raquette à l'impact.
  - Marqueur à fiches papier, règles du ping (11 points, 2 d'écart, service tous les 2 points), une manche par défaut (`manches` = 1, 3 ou 5), son à chaque point gagné.
  - Tableau des 10 meilleures victoires commun à tous les visiteurs, vidable dans les réglages.
  - Partage en fin de partie (Facebook, Instagram, X, WhatsApp) avec une image Open Graph générée pour chaque match, qui invite à jouer ; le lien partagé ouvre le jeu sur un défi (même adversaire, même niveau, verdict en fin de partie).
- Réglage « Adversaires du jeu de pong » : top 10 mondial messieurs et dames (noms comme sur le site de la WTT, photo de la médiathèque, légende = crédit affiché dans « Crédit photo »).
- Démo WordPress Playground (`demo/playground/`, hors ZIP).

## [1.10.0] — 2026-10-01

### Modifié

- Les photos des joueurs sont inclinées aléatoirement entre -20° et 20° (podium de la liste des joueurs, podium et listes des visuels réseaux sociaux), au lieu d'angles fixes : les visages n'ont plus tous la même orientation.

## [1.9.2] — 2026-09-30

### Corrigé

- Tous les licenciés validés sont affichés : ceux qui n'ont pas encore de points mensuels (nouveaux licenciés, arrivées au club) étaient masqués alors qu'ils ont des points officiels. Leur progression reste vide tant que la FFTT n'a pas de base de comparaison, pour ne pas fausser le Top Progression.
- La bande de stats de la page joueurs indique le nombre de licenciés au lieu des joueurs classés.

## [1.9.1] — 2026-09-30

### Corrigé

- Les joueurs qui n'ont pas repris leur licence pour la saison en cours ne sont plus affichés (liste des joueurs, podium, top progression, synchronisation). Seules les licences validées sont retenues, comme dans l'appli FFTT.

## [1.9.0] — 2026-09-28

### Ajouté

- Filtre `monclubtt_get_feuille_rencontre` : les plugins tiers peuvent lire la feuille de match d'une rencontre (joueurs et parties) depuis le cache, sans appel API supplémentaire. Utilisé par TT Team Planner 1.6.0 pour importer les rencontres jouées.

## [1.8.0] — 2026-09-26

### Modifié

- Les tableaux du site (équipes, rencontres, feuilles de match, joueurs) utilisent les couleurs primaire et secondaire du club au lieu du bleu marine par défaut : en-têtes, légendes, lignes alternées, survol, équipe du club, vainqueurs et couleurs hommes / femmes.

## [1.7.0] — 2026-09-26

### Ajouté

- Visuels réseaux sociaux « Dernier week-end de championnat », calculés à partir des mêmes feuilles de match que les top perfs (aucun appel API supplémentaire hors classements de poule) :
  - **Résultats des équipes** : score, adversaire, bilan V / N / D, et pastille « Leader » pour une équipe en tête de sa poule
  - **Carton plein** : joueurs ayant gagné toutes leurs parties en simple du week-end (2 au minimum), sur deux colonnes pour que tout le monde apparaisse
  - **Victoires à la belle** : parties gagnées en 5 sets avec le détail des sets, remontadas (menés 0-2) puis belles gagnées aux avantages en tête
- Visuel « Nouveaux paliers » du mois : joueurs dont les points mensuels passent une centaine (1600, 1700…), avec leur nouveau classement ; le plancher de 500 pts est ignoré
- Texte de publication pré-rempli pour chaque nouveau visuel

### Modifié

- Les listes des visuels rétrécissent leurs lignes pour tout faire tenir, et signalent « + N autres » au-delà de la taille lisible

---

## [1.6.5] — 2026-09-25

### Retiré

- Écusson du club sur le maillot des joueurs du podium (site et visuels), trop petit pour être lisible. Le logo du club reste affiché dans l'en-tête des visuels réseaux sociaux ; la couleur de maillot est conservée

---

## [1.6.4] — 2026-09-25

### Ajouté

- Réglage « Logo du club » (médiathèque), avec repli sur l'icône du site : écusson sur le maillot des joueurs du podium (site et visuels réseaux sociaux) et logo de l'en-tête des visuels
- Couleur « Maillot des joueurs » dans les couleurs du club, appliquée aux joueurs du podium

---

## [1.6.3] — 2026-09-25

### Modifié

- Les couleurs primaire, secondaire et de fond deviennent des réglages du plugin (« Couleurs du club », page Mon Club TT) ; la page Réseaux sociaux les affiche avec un lien vers les réglages. Les couleurs choisies en 1.6.2 sont reprises
- Le podium « Top Progression » du site utilise les couleurs du club : blocs en couleur primaire, pastilles de gain, bandeau et prénoms féminins en couleur secondaire (le fond reste réservé aux visuels)

---

## [1.6.2] — 2026-09-25

### Ajouté

- Visuels réseaux sociaux : choix des couleurs primaire, secondaire et de fond, enregistrées pour le club (bouton « Couleurs par défaut ») ; texte et teintes déduits automatiquement pour rester lisibles sur fond clair comme foncé
- Top perfs : numéro de journée en accent (« J1 ») et résumé du week-end (perfs, joueurs, points gagnés)

### Modifié

- Nouvelle mise en page des visuels : fond uni, logo cerclé et nom du club, très gros titre, pastille de date prolongée d'un filet, perfs en cartes teintées avec carré de rang, pied avec filet et nom du club

---

## [1.6.1] — 2026-09-25

### Ajouté

- Page Réseaux sociaux : texte de publication pré-rempli d'après le visuel (podium ou liste des perfs), modifiable, avec un bouton pour le copier

---

## [1.6.0] — 2026-09-25

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
