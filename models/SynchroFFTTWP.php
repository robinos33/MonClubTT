<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_SynchroFFTTWP' ) ) {

/**
 * Synchronisation des données FFTT : joueurs, équipes, classements et
 * rencontres des poules, caches vidés puis rechargés depuis l'API.
 *
 * Lancée à la main (Données FFTT, widget du tableau de bord) et par le cron
 * de WordPress aux créneaux de MonClubTT_SynchroFFTT. Chaque créneau est un
 * événement unique, replanifié après son passage à l'heure locale : une
 * récurrence de 7 × 24 h glisserait d'une heure au changement d'heure.
 */
class MonClubTT_SynchroFFTTWP {

    const CRON_HOOK = 'monclubtt_synchro_auto';

    /** Résultat du dernier passage automatique : quand, ok, message. */
    const OPTION_ETAT = 'monclubtt_synchro_auto';

    public static function enregistrer() {
        add_action('init', array(__CLASS__, 'planifier'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'executer'));
    }

    /** Planifie chaque créneau qui ne l'est pas (première fois, événement perdu). */
    public static function planifier() {
        $maintenant = new DateTimeImmutable('now', wp_timezone());
        foreach (MonClubTT_SynchroFFTT::CRENEAUX as $cle => $creneau) {
            if (wp_next_scheduled(self::CRON_HOOK, array($cle)) !== false) {
                continue;
            }
            $passage = MonClubTT_SynchroFFTT::prochainPassage($creneau[0], $creneau[1], $creneau[2], $maintenant);
            wp_schedule_single_event($passage->getTimestamp(), self::CRON_HOOK, array($cle));
        }
    }

    /**
     * Passage du cron : synchronise, garde le résultat pour l'admin, puis
     * replanifie le créneau. Une erreur est consignée, jamais propagée.
     */
    public static function executer($creneau = '') {
        try {
            $resultats = self::lancer();
            $etat = array(
                'ok'      => true,
                'message' => sprintf('%d joueurs, %d équipes', $resultats['joueurs'], $resultats['equipes']),
            );
        } catch (Throwable $e) {
            $etat = array('ok' => false, 'message' => $e->getMessage());
        }
        update_option(self::OPTION_ETAT, $etat + array('quand' => time(), 'creneau' => (string) $creneau), false);
        self::planifier();
    }

    /** Désactivation du plugin : plus de passage automatique. */
    public static function desplanifier() {
        wp_unschedule_hook(self::CRON_HOOK);
    }

    /**
     * Lance la synchronisation complète.
     *
     * @param list<string> $debugLog Étapes franchies, renseignées même en cas d'échec.
     * @return array<string, int> Nombre de joueurs et d'équipes récupérés.
     * @throws Exception Paramètres manquants, API injoignable ou aucune donnée reçue.
     */
    public static function lancer(&$debugLog = array()) {
        delete_transient(MonClubTT_AccesFFTTApi::TRANSIENT_INIT);
        $debugLog = array();
        $api = MonClubTT_AccesFFTTApi::getInstance();
        if (!is_object($api)) {
            throw new Exception('Erreur de connexion à l\'API FFTT');
        }

        $numClub = MonClubTT_ParametresPlugin::getNumClub();
        $syncResults = array();

        // Vérifier les paramètres de connexion
        if (empty($numClub)) {
            throw new Exception('Numéro de club non configuré');
        }
        $debugLog[] = "Numéro de club: " . $numClub;

        $idApp = MonClubTT_ParametresPlugin::getIdApplication();
        $motDePasse = MonClubTT_ParametresPlugin::getMotDePasse();
        if (empty($idApp) || empty($motDePasse)) {
            throw new Exception('Identifiants API FFTT non configurés');
        }
        $debugLog[] = "ID Application: configuré";

        // Synchronisation des joueurs
        $api->clearJoueursCache($numClub);
        $debugLog[] = "Cache joueurs effacé";

        $joueurs = new MonClubTT_Joueurs();
        $joueursListe = $joueurs->getJoueurs('MF');
        $syncResults['joueurs'] = count($joueursListe);
        $debugLog[] = "Joueurs récupérés: " . $syncResults['joueurs'];

        // Synchronisation des équipes
        $api->clearEquipesCache($numClub);
        $debugLog[] = "Cache équipes effacé";

        $equipesM = $api->getEquipesByClub($numClub, 'M');
        $equipesF = $api->getEquipesByClub($numClub, 'F');
        $syncResults['equipes'] = count($equipesM) + count($equipesF);
        $debugLog[] = "Équipes M récupérées: " . count($equipesM);
        $debugLog[] = "Équipes F récupérées: " . count($equipesF);

        // Vérifier si aucune donnée n'a été récupérée
        if ($syncResults['joueurs'] === 0 && $syncResults['equipes'] === 0) {
            throw new Exception('Aucune donnée récupérée. Vérifiez vos identifiants API et le numéro de club.');
        }

        // Pour chaque équipe, synchroniser classements et rencontres
        $allEquipes = array_merge($equipesM, $equipesF);
        foreach ($allEquipes as $equipe) {
            if (isset($equipe['iddiv']) && isset($equipe['idpoule'])) {
                $api->clearPouleCache($equipe['iddiv'], $equipe['idpoule']);
                $api->getPouleClassement($equipe['iddiv'], $equipe['idpoule']);
                $api->getPouleRencontres($equipe['iddiv'], $equipe['idpoule']);
            }
        }

        // Enregistrer l'horodatage de la dernière synchronisation
        update_option('monclubtt_last_sync', time());

        /** Données FFTT synchronisées : la gestion des équipes met à jour ses joueurs. */
        do_action('monclubtt_apres_synchro');

        return $syncResults;
    }
}

}
