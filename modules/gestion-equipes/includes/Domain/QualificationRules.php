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
 * Source unique du récapitulatif affiché dans les réglages.
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
     * @return list<array{status: string, title: string, reference: string, detail: string}>
     */
    public static function all(): array
    {
        return [
            [
                'status'    => self::SIGNALED,
                'title'     => __('Une seule rencontre par journée', 'mon-club-tt'),
                'reference' => 'FFTT II.112.1',
                'detail'    => __('Un joueur ne peut disputer qu\'une rencontre, dans une seule équipe, au titre d\'une même journée. L\'application signale un joueur déjà placé dans une autre équipe pour la journée, sans l\'empêcher.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Brûlage après 2 rencontres', 'mon-club-tt'),
                'reference' => 'FFTT II.112.1',
                'detail'    => __('Un joueur ayant disputé 2 rencontres d\'une même phase, consécutives ou non, dans une équipe de numéro N (ou dans des équipes différentes) ne peut plus jouer dans une équipe de numéro supérieur à N.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Limite de la 2e journée', 'mon-club-tt'),
                'reference' => 'FFTT II.112.1',
                'detail'    => __('À la 2e journée d\'une phase, une équipe de 3 ou 4 joueurs ne peut comporter plus d\'un joueur ayant disputé la 1re journée dans une équipe de numéro inférieur.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Joueurs étrangers', 'mon-club-tt'),
                'reference' => 'FFTT II.609',
                'detail'    => __('Au plus 1 joueur étranger (hors UE, EEE et Suisse) par équipe de 4 joueurs. Un deuxième étranger est signalé « 2e étranger ». Les étrangers de moins de 18 ans nés en France ne comptent pas, mais l\'application ne sait pas les distinguer : à vérifier à la main.', 'mon-club-tt'),
            ],
            [
                'status'    => self::SIGNALED,
                'title'     => __('Joueurs mutés', 'mon-club-tt'),
                'reference' => 'FFTT II.610',
                'detail'    => __('Nombre de mutés non limité, quelle que soit la phase ou le type de mutation. Le badge « Mut. » est purement informatif.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Points minimum (Nouvelle-Aquitaine)', 'mon-club-tt'),
                'reference' => 'LNATT 4.1 / FFTT II.112.4',
                'detail'    => __('Pré-Nationale : 1200 points minimum ; Régionale 1 : 1000 points minimum, atteints lors de l\'un des deux classements officiels de la saison. Pas de minimum en R2 et R3. Exception : si l\'équipe monte à l\'issue de la phase 1, les joueurs ayant disputé au moins 3 rencontres avec elle en phase 1 restent qualifiés. L\'application compare au meilleur classement officiel relevé à chaque synchronisation MonClubTT (resynchronisez après la parution de chaque classement) et se fonde sur le niveau saisi pour chaque équipe.', 'mon-club-tt'),
            ],
            [
                'status'    => self::CONTROLLED,
                'title'     => __('Forfait, exempt et forfait adverse', 'mon-club-tt'),
                'reference' => 'FFTT II.112.2',
                'detail'    => __('Forfait de l\'équipe à la 1re journée : à la 2e journée, elle ne peut aligner que des joueurs n\'ayant pas joué la 1re journée dans une autre équipe. Forfait à une autre journée : les joueurs ayant disputé la journée précédente dans l\'équipe forfait ne peuvent pas jouer, ce jour-là, dans une équipe de numéro supérieur. Le forfait se déclare depuis la journée de l\'équipe dans l\'application. Équipe exempte ou bénéficiant d\'un forfait : les joueurs inscrits sur la feuille sont réputés avoir joué ; ces feuilles n\'étant pas publiées par la FFTT, validez la composition à la main.', 'mon-club-tt'),
            ],
        ];
    }
}
