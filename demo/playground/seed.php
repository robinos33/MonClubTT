<?php
/**
 * Démo WordPress Playground du jeu de pong : club fictif, photos, crédits,
 * pages de jeu et quelques scores. Lancé par blueprint.json, jamais par le
 * plugin lui-même (dossier exclu du ZIP de distribution).
 *
 * Les licenciés sont injectés dans le cache du plugin : la démo n'appelle
 * pas l'API FFTT.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$dossier = __DIR__ . '/img/';
$numClub = '99999999';

update_option(MonClubTT_Constantes::MONCLUBTT_ID_APPLICATION, 'DEMO');
update_option(MonClubTT_Constantes::MONCLUBTT_MOT_DE_PASSE, 'demo');
update_option(MonClubTT_Constantes::MONCLUBTT_NUM_CLUB, $numClub);

/** Importe une image du dossier de démo dans la médiathèque. */
$importer = function ($fichier, $legende = '') use ($dossier) {
    // Le dossier img/ contient les images, la musique est à la racine de la démo.
    $source = file_exists($dossier . $fichier) ? $dossier . $fichier : dirname($dossier) . '/' . $fichier;
    $tmp = wp_tempnam($fichier);
    copy($source, $tmp);
    $id = media_handle_sideload(array('name' => $fichier, 'tmp_name' => $tmp), 0, null, array('post_excerpt' => $legende));
    return is_wp_error($id) ? 0 : (int) $id;
};

// Licenciés fictifs (club « US Pongistes »), au format xml_licence_b.php.
$club = array(
    array('nom' => 'BERTIN', 'prenom' => 'Camille', 'sexe' => 'F', 'licence' => '9900101', 'points' => 1184, 'photo' => '9900101.png'),
    array('nom' => 'CARRÈRE', 'prenom' => 'Louis', 'sexe' => 'M', 'licence' => '9900102', 'points' => 1532, 'photo' => '9900102.png'),
    array('nom' => 'DIALLO', 'prenom' => 'Awa', 'sexe' => 'F', 'licence' => '9900103', 'points' => 961, 'photo' => '9900103.png'),
    array('nom' => 'ÉTCHEGARAY', 'prenom' => 'Mattin', 'sexe' => 'M', 'licence' => '9900104', 'points' => 1307, 'photo' => '9900104.png'),
    array('nom' => 'FOURNIER', 'prenom' => 'Jean-Marc', 'sexe' => 'M', 'licence' => '9900105', 'points' => 834, 'photo' => '9900105.png'),
    array('nom' => 'GARCIA', 'prenom' => 'Inès', 'sexe' => 'F', 'licence' => '9900106', 'points' => 1420, 'photo' => '9900106.png'),
    array('nom' => 'LAMBERT', 'prenom' => 'Théo', 'sexe' => 'M', 'licence' => '9900107', 'points' => 702, 'photo' => '9900107.png'),
    array('nom' => 'MOREAU', 'prenom' => 'Hugo', 'sexe' => 'M', 'licence' => '9900108', 'points' => 1096, 'photo' => ''),
    array('nom' => 'NGUYEN', 'prenom' => 'Linh', 'sexe' => 'F', 'licence' => '9900109', 'points' => 1268, 'photo' => '9900109.png'),
    array('nom' => 'PETIT', 'prenom' => 'Lucie', 'sexe' => 'F', 'licence' => '9900110', 'points' => 598, 'photo' => ''),
    array('nom' => 'ROUSSEAU', 'prenom' => 'Nathan', 'sexe' => 'M', 'licence' => '9900111', 'points' => 1655, 'photo' => '9900111.png'),
    array('nom' => 'VIDAL', 'prenom' => 'Élise', 'sexe' => 'F', 'licence' => '9900112', 'points' => 1013, 'photo' => '9900112.png'),
);
$licences = array();
$photos   = array();
foreach ($club as $j) {
    $licences[] = array(
        'licence' => $j['licence'], 'nom' => $j['nom'], 'prenom' => $j['prenom'], 'numclub' => $numClub,
        'sexe' => $j['sexe'], 'cat' => 'S', 'natio' => 'F', 'validation' => '01/09/2026',
        'point' => (string) $j['points'], 'pointm' => (string) ($j['points'] + 12),
        'apointm' => (string) $j['points'], 'initm' => (string) ($j['points'] - 20),
    );
    if ($j['photo'] !== '') {
        $photos[$j['licence']] = $importer($j['photo']);
    }
}
$api = MonClubTT_AccesFFTTApi::getInstance();
$cle = $api->buildCacheKeyPublic('joueurs_club', array('numclu' => $numClub));
set_transient($cle, $licences, 30 * DAY_IN_SECONDS);
set_transient($cle . '__updated_at', time(), 30 * DAY_IN_SECONDS);
update_option(MonClubTT_Constantes::MONCLUBTT_JOUEUR_PHOTOS, $photos);

// Top 10 mondial : photos libres de Wikimedia Commons (légende = crédit) ou dessins.
$top = array(
    'WANG Chuqin' => array('top-wang-chuqin.png', '<a href="https://commons.wikimedia.org/wiki/File:Table_tennis_at_the_2018_Summer_Youth_Olympics_%E2%80%93_Men%27s_Singles_Gold_Medal_Match_068_(cropped).jpg">Marcus Cyron</a>, <a href="https://creativecommons.org/licenses/by-sa/3.0/">CC BY-SA 3.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'Felix LEBRUN' => array('top-felix-lebrun.png', '<a href="https://commons.wikimedia.org/wiki/File:20220818_European_Championships_Munich_2022_Felix_Lebrun_850_9644.jpg">Granada</a>, <a href="https://creativecommons.org/licenses/by-sa/4.0/">CC BY-SA 4.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'Tomokazu HARIMOTO' => array('top-tomokazu-harimoto.png', '<a href="https://commons.wikimedia.org/wiki/File:Table_tennis_at_the_2018_Summer_Youth_Olympics_%E2%80%93_Men%27s_Singles_Gold_Medal_Match_020_(cropped).jpg">Marcus Cyron</a>, <a href="https://creativecommons.org/licenses/by-sa/3.0/">CC BY-SA 3.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'Truls MOREGARD' => array('top-truls-moregard.png', '<a href="https://commons.wikimedia.org/wiki/File:Table_tennis_at_the_2018_Summer_Youth_Olympics_%E2%80%93_Mixed_Bronze_Medal_Match_Men_109.jpg">Marcus Cyron</a>, <a href="https://creativecommons.org/licenses/by-sa/3.0/">CC BY-SA 3.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'LIN Yun-Ju' => array('top-lin-yun-ju.png', '<a href="https://commons.wikimedia.org/wiki/File:08.05_%E7%B8%BD%E7%B5%B1%E6%8E%A5%E8%A6%8B%E6%A1%8C%E7%90%83%E9%81%B8%E6%89%8B%E6%9E%97%E6%98%80%E5%84%92_(48460894246).jpg">總統府</a>, <a href="https://creativecommons.org/licenses/by/2.0/">CC BY 2.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'Hugo CALDERANO' => array('top-hugo-calderano.png', '<a href="https://commons.wikimedia.org/wiki/File:Calderano_em_2021_(cropped).jpg">Breno Barros</a>, <a href="https://creativecommons.org/licenses/by/3.0/br/">CC BY 3.0 br</a>, via Wikimedia Commons, recadrée et détourée'),
    'Alexis LEBRUN' => array('top-alexis-lebrun.png', '<a href="https://commons.wikimedia.org/wiki/File:20220818_European_Championships_Munich_2022_Alexis_Lebrun_850_9513.jpg">Granada</a>, <a href="https://creativecommons.org/licenses/by-sa/4.0/">CC BY-SA 4.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'Dang QIU' => array('top-dang-qiu.png', '<a href="https://commons.wikimedia.org/wiki/File:20220814_European_Championships_Munich_2022_Dang_Qiu_850_2751.jpg">Granada</a>, <a href="https://creativecommons.org/licenses/by-sa/4.0/">CC BY-SA 4.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'WANG Manyu' => array('top-wang-manyu.png', '<a href="https://commons.wikimedia.org/wiki/File:Wang_Manyu_ATTC2017_2.jpeg">XIAOYU TANG</a>, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC BY-SA 2.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'SUN Yingsha' => array('top-sun-yingsha.png', '<a href="https://commons.wikimedia.org/wiki/File:Sun_Yingsha.png">China News Service</a>, <a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'Miwa HARIMOTO' => array('top-miwa-harimoto.png', '<a href="https://commons.wikimedia.org/wiki/File:Harimoto_Miwa_2023.png">中国新闻社</a>, <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'Hina HAYATA' => array('top-hina-hayata.png', '<a href="https://commons.wikimedia.org/wiki/File:Hayata_Hina_ATTC2017_25_(cropped).jpeg">XIAOYU TANG</a>, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC BY-SA 2.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'CHEN Xingtong' => array('top-chen-xingtong.png', '<a href="https://commons.wikimedia.org/wiki/File:Chen_Xingtong_on_2018_ITTF_World_Tour_Platinum_China_Open_(in_game).jpg">Second Wing</a>, <a href="https://creativecommons.org/licenses/by/2.5/">CC BY 2.5</a>, via Wikimedia Commons, recadrée et détourée'),
    'ZHU Yuling' => array('top-zhu-yuling.png', '<a href="https://commons.wikimedia.org/wiki/File:Mondial_Ping_-Women%27s_Singles_-_Quarterfinal_-_Zhu_Yuling-Feng_Tianwei_-_18.jpg">Pierre-Yves Beaudouin</a>, <a href="https://creativecommons.org/licenses/by-sa/3.0/">CC BY-SA 3.0</a>, via Wikimedia Commons, recadrée et détourée'),
    'Sabine WINTER' => array('top-sabine-winter.png', '<a href="https://commons.wikimedia.org/wiki/File:2023_DM_Tischtennis_-_Sabine_Winter_-_by_2eight_-_9SC3285.jpg">Stefan Brending (2eight)</a>, <a href="https://creativecommons.org/licenses/by-sa/3.0/de/">CC BY-SA 3.0 de</a>, via Wikimedia Commons, recadrée et détourée'),
    'Sora MATSUSHIMA' => array('top-sora-matsushima.png', ''),
    'LIN Shidong' => array('top-lin-shidong.png', ''),
    'KUAI Man' => array('top-kuai-man.png', ''),
    'WANG Yidi' => array('top-wang-yidi.png', ''),
    'CHEN Yi' => array('top-chen-yi.png', ''),
);
$adversaires = monclubtt_normaliser_adversaires(null);
foreach ($adversaires as $sexe => $liste) {
    foreach ($liste as $i => $adv) {
        if (isset($top[$adv['nom']])) {
            $adversaires[$sexe][$i]['photo'] = $importer($top[$adv['nom']][0], $top[$adv['nom']][1]);
        }
    }
}
update_option(MonClubTT_Constantes::MONCLUBTT_PONG_ADVERSAIRES, $adversaires);

// Musique de fond : boucle originale composée pour la démo.
update_option(MonClubTT_Constantes::MONCLUBTT_PONG_MUSIQUE, (string) $importer('musique-demo.wav'));

// Quelques victoires pour ne pas ouvrir sur un tableau vide.
$scores = array();
foreach (array(
    array('Nathan ROUSSEAU', 'Hugo CALDERANO', 'mondial', 11, 7),
    array('Inès GARCIA', 'Louis CARRÈRE', 'normal', 11, 4),
    array('Camille BERTIN', 'WANG Yidi', 'normal', 12, 10),
) as $i => $s) {
    $scores = MonClubTT_PongScores::ajouter($scores, array('joueur' => $s[0], 'adversaire' => $s[1], 'niveau' => $s[2], 'pj' => $s[3], 'pa' => $s[4], 'date' => time() - 3600 + $i))['tableau'];
}
update_option(MonClubTT_Constantes::MONCLUBTT_PONG_SCORES, $scores, false);

// Pages : le jeu, et le jeu avec un adversaire imposé.
$coach = $importer('coach.png');
$pages = array(
    'jeu-de-pong'      => array('Jeu de pong', '[monclubtt_pong]'),
    'pong-contre-coach' => array('Pong contre le coach', '[monclubtt_pong adversaire="Le coach du club" adversaire_titre="Invité spécial" adversaire_photo="' . $coach . '"]'),
    'joueurs'          => array('Joueurs', '[monclubtt_joueurs type="MF"]'),
);
$ids = array();
foreach ($pages as $slug => $page) {
    $ids[$slug] = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $page[0], 'post_content' => $page[1]));
}

// Un match déjà partagé, pour voir la page d'arrivée d'un lien : /jeu-de-pong/?pong=defidemo0001
update_option(MonClubTT_Constantes::MONCLUBTT_PONG_MATCHS, array(
    'defidemo0001' => array(
        'joueur' => 'Louis CARRÈRE', 'adversaire' => 'Felix LEBRUN', 'photo_j' => $photos['9900102'] ?? 0,
        'photo_a' => (int) $adversaires['M'][1]['photo'], 'pj' => 11, 'pa' => 9, 'victoire' => true, 'niveau' => 'mondial',
        'adv_type' => 'monde', 'adv_nom' => 'Felix LEBRUN', 'adv_prenom' => '', 'post' => (int) $ids['jeu-de-pong'], 'date' => time(),
    ),
), false);
update_option('permalink_structure', '/%postname%/');
flush_rewrite_rules();
