<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_SynchroFFTTWP' ) ) {

/**
 * Synchronisation des données FFTT : joueurs, équipes, classements et
 * rencontres des poules, caches vidés puis rechargés depuis l'API.
 */
class MonClubTT_SynchroFFTTWP {

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
