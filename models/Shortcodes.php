<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_Shortcodes' ) ) {

/**
 * Catalogue des shortcodes du plugin, affiché dans « Mon Club TT › Shortcodes ».
 *
 * Un test vérifie que chaque add_shortcode() du plugin y figure : un nouveau
 * shortcode doit être ajouté ici.
 *
 * Shortcode : tag, titre, description, exemples (shortcode => explication),
 * attributs [ [ nom, valeurs, défaut, rôle ] ], admin (page où se gèrent les
 * données : page du menu ou écran WordPress), slugs (series, categories,
 * commissions : valeurs disponibles sur le site, listées sous le tableau).
 */
class MonClubTT_Shortcodes {

    /**
     * @return array<int, array{titre: string, shortcodes: array<int, array>}>
     */
    public static function catalogue()
    {
        return array(
            array(
                'titre'      => 'Joueurs et équipes',
                'shortcodes' => array(
                    array(
                        'tag'         => 'monclubtt_joueurs',
                        'titre'       => 'Liste des joueurs',
                        'description' => 'Licenciés du club triés par points, avec la progression du mois et de la saison et le podium Top Progression.',
                        'exemples'    => array(
                            '[monclubtt_joueurs]'          => 'Tous les joueurs, filtre Tous / Hommes / Femmes.',
                            '[monclubtt_joueurs type="F"]' => 'Seulement les joueuses.',
                        ),
                        'attributs'   => array(
                            array('type', '<code>M</code>, <code>F</code>, <code>MF</code>', '<code>MF</code>', 'Sexe affiché'),
                        ),
                        'admin'       => array('Données FFTT › Joueurs', 'admin.php?page=monclubtt_donnees&tab=joueurs'),
                    ),
                    array(
                        'tag'         => 'monclubtt_equipe',
                        'titre'       => 'Page d\'équipe',
                        'description' => 'Classement de la poule et résultats par journée, feuilles de match au clic. Sur mobile, propose d\'ajouter l\'équipe à l\'écran d\'accueil.',
                        'exemples'    => array(
                            '[monclubtt_equipe iddiv="198511" idpoule="1140384"]' => 'Les numéros de division et de poule de chaque équipe sont dans Données FFTT › Équipes, shortcode prêt à copier.',
                        ),
                        'attributs'   => array(
                            array('iddiv', 'numéro de division', '—', 'Obligatoire'),
                            array('idpoule', 'numéro de poule', '—', 'Obligatoire'),
                            array('appli', '<code>non</code>', '—', 'Pas d\'appli ni de bandeau « écran d\'accueil » sur cette page'),
                            array('nom_appli', 'texte', 'Équipe 3 · club', 'Nom de l\'appli installée'),
                            array('nom_court', 'texte', 'Équipe 3', 'Nom sous l\'icône'),
                        ),
                        'admin'       => array('Données FFTT › Équipes', 'admin.php?page=monclubtt_donnees&tab=equipes'),
                    ),
                ),
            ),
            array(
                'titre'      => 'Calendrier du club',
                'shortcodes' => array(
                    array(
                        'tag'         => 'monclubtt_calendrier',
                        'titre'       => 'Agenda de la saison',
                        'description' => 'Vue mois ou liste, filtres par catégorie, détail d\'un rendez-vous, téléchargement et abonnement (Apple, Outlook, Google).',
                        'exemples'    => array(
                            '[monclubtt_calendrier]'                       => 'Toute la saison du prochain rendez-vous.',
                            '[monclubtt_calendrier categorie="jeunes"]'    => 'Seulement une catégorie.',
                        ),
                        'attributs'   => array(
                            array('saison', 'année de début (<code>2026</code>)', 'saison du prochain rendez-vous', 'Saison affichée'),
                            array('categorie', 'noms ou slugs, séparés par des virgules', '—', 'Restreindre à certaines catégories'),
                            array('serie', 'slugs, séparés par des virgules', '—', 'Restreindre à certaines séries'),
                        ),
                        'admin'       => array('Calendrier', 'edit.php?post_type=monclubtt_evenement'),
                        'slugs'       => array('series', 'categories'),
                    ),
                    array(
                        'tag'         => 'monclubtt_prochain',
                        'titre'       => 'Prochain rendez-vous',
                        'description' => 'Prochain rendez-vous d\'une série ou d\'une catégorie, qui passe tout seul au suivant. En texte, un seul champ à glisser dans une phrase ou un lien.',
                        'exemples'    => array(
                            '[monclubtt_prochain serie="grand-prix" libelle="Tour %n"]'                 => 'Encadré : date, lieu, note et lien.',
                            '[monclubtt_prochain serie="grand-prix" format="texte"]'                    => 'Texte : « 28 et 29 novembre ».',
                            '[monclubtt_prochain serie="grand-prix" format="texte" champ="ordinal"]'    => 'Texte : « 1er » (rang dans la série).',
                            '[monclubtt_prochain serie="grand-prix" format="texte" champ="lien"]'       => 'Adresse du lien d\'inscription, pour un bouton.',
                        ),
                        'attributs'   => array(
                            array('serie', 'slugs', '—', 'Série(s) concernée(s)'),
                            array('categorie', 'noms ou slugs', '—', 'Ou une catégorie'),
                            array('libelle', 'texte avec <code>%n</code> (rang), <code>%t</code> (titre), <code>%s</code> (série)', 'titre', 'Libellé affiché'),
                            array('format', '<code>bloc</code>, <code>texte</code>', '<code>bloc</code>', 'Encadré ou texte seul'),
                            array('champ', '<code>periode</code>, <code>libelle</code>, <code>titre</code>, <code>numero</code>, <code>ordinal</code>, <code>heure</code>, <code>lieu</code>, <code>lien</code>, <code>note</code>, <code>debut</code>, <code>fin</code>', '<code>periode</code>', 'Champ renvoyé en <code>format="texte"</code>'),
                            array('annee', '<code>0</code>, <code>1</code>', '<code>0</code>', 'Ajouter l\'année à la date'),
                            array('vide', 'texte', 'Dates à venir', 'Texte quand il n\'y a plus de date'),
                        ),
                        'admin'       => array('Calendrier', 'edit.php?post_type=monclubtt_evenement'),
                        'slugs'       => array('series', 'categories'),
                    ),
                    array(
                        'tag'         => 'monclubtt_dates',
                        'titre'       => 'Dates d\'une série',
                        'description' => 'Toutes les dates de la saison d\'une série, la prochaine mise en avant et les passées grisées. Avec plusieurs séries, tableau par mois.',
                        'exemples'    => array(
                            '[monclubtt_dates serie="grand-prix" libelle="Tour %n"]'                              => 'Cartes des tours de la saison.',
                            '[monclubtt_dates serie="stage-multisports,stage-perfectionnement" titres="Multisports,Perfectionnement"]' => 'Tableau par mois, une colonne par série.',
                            '[monclubtt_dates serie="stage-multisports" format="cartes" passes="masquer"]'       => 'Une carte par date à venir, à styler avec le thème.',
                        ),
                        'attributs'   => array(
                            array('serie', 'slugs, séparés par des virgules', '—', 'Série(s)'),
                            array('categorie', 'noms ou slugs', '—', 'Ou une catégorie'),
                            array('saison', 'année de début', 'saison du prochain rendez-vous', 'Saison affichée'),
                            array('libelle', 'comme <code>[monclubtt_prochain]</code>', 'titre', 'Libellé des cartes'),
                            array('titres', 'textes séparés par des virgules', 'noms des séries', 'En-têtes des colonnes du tableau'),
                            array('grouper', '<code>auto</code>, <code>liste</code>, <code>mois</code>', '<code>auto</code>', 'Cartes, ou tableau par mois (auto : tableau dès deux séries)'),
                            array('passes', '<code>afficher</code>, <code>masquer</code>', '<code>afficher</code>', 'Dates passées grisées ou masquées'),
                            array('format', '<code>cartes</code>', '—', 'Une carte par date, la prochaine marquée <code>is-prochain</code>'),
                            array('classe', 'classes CSS', '—', 'Classes ajoutées à la liste en <code>format="cartes"</code>'),
                            array('annee', '<code>0</code>, <code>1</code>', '<code>0</code>', 'Ajouter l\'année aux dates'),
                        ),
                        'admin'       => array('Calendrier', 'edit.php?post_type=monclubtt_evenement'),
                        'slugs'       => array('series', 'categories'),
                    ),
                ),
            ),
            array(
                'titre'      => 'Vie du club',
                'shortcodes' => array(
                    array(
                        'tag'         => 'monclubtt_comite',
                        'titre'       => 'Comité directeur',
                        'description' => 'Le bureau (chacun avec son poste), puis les autres membres du comité (avec leurs commissions), en cartes : nom, photo ou initiales, pastille.',
                        'exemples'    => array(
                            '[monclubtt_comite]'                                     => 'Le bureau puis les autres membres.',
                            '[monclubtt_comite partie="bureau" titre_bureau="Le bureau" surtitre_bureau=""]' => 'Seulement le bureau, avec un titre simple (par exemple sur la page d\'accueil).',
                            '[monclubtt_comite commission="communication"]'          => 'Seulement les membres d\'une commission.',
                        ),
                        'attributs'   => array(
                            array('partie', '<code>tout</code>, <code>bureau</code>, <code>comite</code>', '<code>tout</code>', 'Parties affichées'),
                            array('commission', 'slugs séparés par des virgules', '—', 'Ne garder que les membres de ces commissions'),
                            array('surtitre_bureau', 'texte (vide : aucun)', 'Le bureau', 'Petit texte au-dessus du titre du bureau'),
                            array('titre_bureau', 'texte (vide : aucun)', 'Qui fait quoi ?', 'Titre du bureau'),
                            array('surtitre_comite', 'texte (vide : aucun)', 'Le comité', 'Petit texte au-dessus du titre des autres membres'),
                            array('titre_comite', 'texte (vide : aucun)', 'Les autres membres', 'Titre des autres membres'),
                            array('niveau', '<code>2</code> à <code>5</code>', '<code>2</code>', 'Niveau des titres (<code>h2</code>…), les noms un niveau en dessous'),
                            array('photos', '<code>1</code>, <code>0</code>', '<code>1</code>', 'Photos (ou initiales)'),
                            array('classe', 'classes CSS', '—', 'Classes ajoutées au bloc'),
                            array('vide', 'texte', '—', 'Texte affiché s\'il n\'y a aucun membre'),
                        ),
                        'admin'       => array('Comité directeur', 'edit.php?post_type=monclubtt_membre'),
                        'slugs'       => array('commissions'),
                    ),
                    array(
                        'tag'         => 'monclubtt_pong',
                        'titre'       => 'Jeu de pong',
                        'description' => 'Le visiteur choisit un licencié du club, puis un adversaire du top 10 mondial ou du club. Meilleurs scores et partage du match.',
                        'exemples'    => array(
                            '[monclubtt_pong]'                                                            => 'Jeu complet, choix de l\'adversaire.',
                            '[monclubtt_pong adversaire="Le président" adversaire_sexe="M" manches="3"]' => 'Adversaire imposé, match en 3 manches.',
                        ),
                        'attributs'   => array(
                            array('adversaire', 'texte', '—', 'Adversaire joué directement, sans écran de choix'),
                            array('adversaire_titre', 'texte', '—', 'Sous-titre sur l\'écran de sélection'),
                            array('adversaire_photo', 'ID de média ou URL', '—', 'Photo détourée (PNG transparent) ; à défaut, avatar dessiné'),
                            array('adversaire_sexe', '<code>M</code>, <code>F</code>', '<code>M</code>', 'Avatar utilisé pour le corps'),
                            array('adversaire_pays', 'code pays (<code>FRA</code>, <code>CHN</code>…)', '—', 'Tenue aux couleurs de la sélection'),
                            array('manches', '<code>1</code>, <code>3</code>, <code>5</code>', '<code>1</code>', 'Nombre de manches'),
                        ),
                        'admin'       => array('Pong', 'admin.php?page=monclubtt_pong'),
                    ),
                    array(
                        'tag'         => 'tt_team_planner',
                        'titre'       => 'Application de gestion des équipes',
                        'description' => 'Application du staff (connexion requise) : disponibilités, compositions, brûlage, SMS. À placer seul sur une page dédiée.',
                        'exemples'    => array(
                            '[tt_team_planner]' => 'Sans attribut.',
                        ),
                        'attributs'   => array(),
                        'admin'       => array('Gestion des équipes', 'admin.php?page=monclubtt_gestion_equipes'),
                    ),
                ),
            ),
        );
    }

    /** Tags de tous les shortcodes du catalogue. */
    public static function tags()
    {
        $tags = array();
        foreach (self::catalogue() as $groupe) {
            foreach ($groupe['shortcodes'] as $shortcode) {
                $tags[] = $shortcode['tag'];
            }
        }
        return $tags;
    }
}

}
