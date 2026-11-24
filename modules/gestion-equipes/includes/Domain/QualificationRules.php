<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

/**
 * Règles de qualification (brûlage) du championnat régional par équipes :
 * ce que l'application contrôle, ce qu'elle se contente de signaler et ce
 * qui reste à la charge du capitaine. Sources : règlements sportifs FFTT
 * (juillet 2025), règlement régional LNATT v1.1 et mémo du championnat
 * régional LNATT 2025-2026.
 *
 * Source unique du récapitulatif affiché dans les réglages et du rappel des
 * règles dans l'application.
 */
final class QualificationRules
{
    /** L'application détecte l'infraction dans les compositions. */
    public const CONTROLLED = 'auto';

    /** L'information est affichée, la vérification reste manuelle. */
    public const SIGNALED = 'signal';

    /** À vérifier par le capitaine. */
    public const NOT_CONTROLLED = 'none';

    /**
     * summary : la règle en une ou deux phrases, pour les joueurs ;
     * detail  : le texte complet, avec ce que fait l'application.
     *
     * @return list<array{status: string, title: string, summary: string, reference: string, detail: string}>
     */
    public static function all(): array
    {
        return [
            [
                'status'    => self::SIGNALED,
                'title'     => __('Une seule rencontre par journée', 'mon-club-tt'),
                'summary'   => __('Un joueur ne dispute qu\'une rencontre par journée, dans une seule équipe.', 'mon-club-tt'),
                'reference' => 'FFTT II.112.1',
                'detail'    => __('Un joueur ne peut disputer qu\'une rencontre, dans une seule équipe, au titre d\'une même journée. L\'application signale un joueur déjà placé dans une autre équipe pour la journée, sans l\'empêcher.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Brûlage après 2 rencontres', 'mon-club-tt'),
                'summary'   => __('Après 2 rencontres dans la phase, un joueur ne peut plus jouer dans une équipe de numéro plus élevé que celles où il a joué.', 'mon-club-tt'),
                'reference' => 'FFTT II.112.1',
                'detail'    => __('Un joueur ayant disputé 2 rencontres d\'une même phase, consécutives ou non, dans une équipe de numéro N (ou dans des équipes différentes) ne peut plus jouer dans une équipe de numéro supérieur à N.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Limite de la 2e journée', 'mon-club-tt'),
                'summary'   => __('À la 2e journée, une équipe ne peut aligner qu\'un seul joueur ayant joué la 1re journée dans une équipe plus forte (de numéro inférieur).', 'mon-club-tt'),
                'reference' => 'FFTT II.112.1',
                'detail'    => __('À la 2e journée d\'une phase, une équipe de 3 ou 4 joueurs ne peut comporter plus d\'un joueur ayant disputé la 1re journée dans une équipe de numéro inférieur.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Joueurs étrangers', 'mon-club-tt'),
                'summary'   => __('Au plus 1 joueur étranger (hors UE, EEE et Suisse) par équipe.', 'mon-club-tt'),
                'reference' => 'FFTT II.609',
                'detail'    => __('Au plus 1 joueur étranger (hors UE, EEE et Suisse) par équipe de 4 joueurs. Un deuxième étranger est signalé « 2e étranger ». Les étrangers de moins de 18 ans nés en France ne comptent pas, mais l\'application ne sait pas les distinguer : à vérifier à la main.', 'mon-club-tt'),
            ],
            [
                'status'    => self::SIGNALED,
                'title'     => __('Joueurs mutés', 'mon-club-tt'),
                'summary'   => __('Pas de limite au nombre de joueurs mutés.', 'mon-club-tt'),
                'reference' => 'FFTT II.610',
                'detail'    => __('Nombre de mutés non limité, quelle que soit la phase ou le type de mutation. Le badge « Mut. » est purement informatif.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Points minimum (Nouvelle-Aquitaine)', 'mon-club-tt'),
                'summary'   => __('Pré-Nationale : 1200 points minimum ; R1 : 1000 points, à l\'un des deux classements officiels de la saison. Pas de minimum en R2 et R3.', 'mon-club-tt'),
                'reference' => 'LNATT 4.1 / FFTT II.112.4',
                'detail'    => __('Pré-Nationale : 1200 points minimum ; Régionale 1 : 1000 points minimum, atteints lors de l\'un des deux classements officiels de la saison. Pas de minimum en R2 et R3. Exception : si l\'équipe monte à l\'issue de la phase 1, les joueurs ayant disputé au moins 3 rencontres avec elle en phase 1 restent qualifiés. L\'application compare au meilleur classement officiel relevé à chaque synchronisation MonClubTT (resynchronisez après la parution de chaque classement) et se fonde sur le niveau saisi pour chaque équipe.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Équipe complète (régional)', 'mon-club-tt'),
                'summary'   => __('En régional (PN à R3), il faut 4 joueurs. À moins de 4, la rencontre est perdue (0 point) et coûte 30 € ; ce n\'est pas un forfait. En départementale, on peut jouer à 3.', 'mon-club-tt'),
                'reference' => 'LNATT 4.6.2',
                'detail'    => __('En Pré-Nationale, R1, R2 et R3, une équipe doit compter 4 joueurs ayant disputé au moins une partie. Une équipe incomplète perd la rencontre (0 point rencontre, 0 point parties) et paie une pénalité de 30 €. Ce n\'est pas un forfait : la caution n\'est pas confisquée, rien ne compte pour le forfait général et le brûlage des joueurs présents se calcule normalement ; ne déclarez donc pas de forfait. Un joueur qui n\'entre pas dans l\'aire de jeu pour sa 1re partie, même blessé, rend l\'équipe incomplète. L\'application signale une équipe régionale de moins de 4 joueurs, en rouge à partir de 6 jours avant la journée, d\'après le niveau saisi pour chaque équipe. En départementale, une équipe peut jouer à 3.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Forfait, exempt et forfait adverse', 'mon-club-tt'),
                'summary'   => __('Quand une équipe est forfait, ses joueurs de la journée précédente ne peuvent pas jouer ce jour-là dans une équipe de numéro supérieur. Forfait à la 1re journée : à la 2e, elle n\'aligne que des joueurs n\'ayant pas joué la 1re ailleurs.', 'mon-club-tt'),
                'reference' => 'FFTT II.112.2',
                'detail'    => __('Forfait de l\'équipe à la 1re journée : à la 2e journée, elle ne peut aligner que des joueurs n\'ayant pas joué la 1re journée dans une autre équipe. Forfait à une autre journée : les joueurs ayant disputé la journée précédente dans l\'équipe forfait ne peuvent pas jouer, ce jour-là, dans une équipe de numéro supérieur. Le forfait se déclare depuis la journée de l\'équipe dans l\'application. Équipe exempte ou bénéficiant d\'un forfait : les joueurs inscrits sur la feuille sont réputés avoir joué ; ces feuilles n\'étant pas publiées par la FFTT, validez la composition à la main.', 'mon-club-tt'),
            ],
        ];
    }
}
