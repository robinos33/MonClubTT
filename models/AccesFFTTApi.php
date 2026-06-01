<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('AccesFFTTApi')) {

    /**
     * @author Robin Aldasoro -
     * Source originale : classe AccesFFTTApi VincentBab vincentbab@gmail.com
     */
    class AccesFFTTApi
    {

        private static $_instance = null;

        private $cache;

        /**
         * @var string $appId ID de l'application fourni par la FFTT (ex: AM001)
         */
        protected $appId;

        /**
         * @var string $appKey Mot de passe fourni par la FFTT
         */
        protected $appKey;

        /**
         * @var string $serial Serial de l'utilisateur
         */
        protected $serial;

        /**
         * @var string $ipSource
         */
        protected $ipSource;

        public function __construct()
        {
            if (!is_null(ParametresDataPing::getIdApplication()) && !is_null(ParametresDataPing::getMotDePasse())) {

                $this->appId = ParametresDataPing::getIdApplication();
                $this->appKey = ParametresDataPing::getMotDePasse();

                // Démarre une session si nécessaire (certaines installations WP n'utilisent pas les sessions par défaut)
                if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
                    session_start();
                }

                if (empty($_SESSION['serial'])) {
                    $_SESSION['serial'] = AccesFFTTApi::generateSerial();
                }

                $this->setSerial(!empty($_SESSION['serial']) ? sanitize_text_field(wp_unslash($_SESSION['serial'])) : AccesFFTTApi::generateSerial());
                // Initialise l'application si possible (les éventuelles erreurs XML sont gérées en interne)
                $this->initialization();
            }

            // Assure que les erreurs libxml n'interrompent pas l'exécution
            libxml_use_internal_errors(true);
        }

        public static function getInstance()
        {
            if (is_null(self::$_instance)
                || (is_null(self::$_instance->appId) && !is_null(ParametresDataPing::getIdApplication()))) {
                self::$_instance = new AccesFFTTApi();
            }

            return self::$_instance;
        }

        public function getAppId()
        {
            return $this->appId;
        }

        public function getAppKey()
        {
            return $this->appKey;
        }

        public function setSerial($serial)
        {
            $this->serial = $serial;

            return $this;
        }

        public function getSerial()
        {
            return $this->serial;
        }

        public function setIpSource($ipSource)
        {
            $this->ipSource = $ipSource;

            return $this;
        }

        public function getIpSource()
        {
            return $this->ipSource;
        }

        public function initialization()
        {
            // L'API xml_initialisation.php retourne souvent une réponse vide, on ignore les erreurs de parsing
            return AccesFFTTApi::getObject($this->getData('https://www.fftt.com/mobile/pxml/xml_initialisation.php', array(), true, true));
        }

        public function getClubsByDepartement($departement)
        {
            //return $this->getCachedData("clubs{$departement}", 3600 * 24 * 30, function ($this) use ($departement) {
                return AccesFFTTApi::getCollection($this->getData('https://www.fftt.com/mobile/pxml/xml_club_dep2.php', array('dep' => $departement)), 'club');
            //});
        }

        public function getClub($numero)
        {
            return AccesFFTTApi::getObject($this->getData('https://www.fftt.com/mobile/pxml/xml_club_detail.php', array('club' => $numero)), 'club');
        }

        public function getJoueur($licence)
        {
            $joueur = AccesFFTTApi::getObject($this->getData('https://www.fftt.com/mobile/pxml/xml_joueur.php', array('licence' => $licence, 'auto' => 1)), 'joueur');

            if (!isset($joueur['licence'])) {
                return null;
            }

            if (empty($joueur['natio'])) {
                $joueur['natio'] = 'F';
            }

            $joueur['photo'] = "http://www.fftt.com/espacelicencie/photolicencie/{$joueur['licence']}_.jpg";
            $joueur['progmois'] = round($joueur['point'] - $joueur['apoint'], 2); // Progression mensuelle
            $joueur['progann'] = round($joueur['point'] - $joueur['valinit'], 2); // Progression annuelle

            return $joueur;
        }


        public function getJoueursByName($nom, $prenom = '')
        {
            return AccesFFTTApi::getCollection($this->getData('https://www.fftt.com/mobile/pxml/xml_liste_joueur.php', array('nom' => $nom, 'prenom' => $prenom)), 'joueur');
        }

        public function getJoueursByClub($club)
        {
            return AccesFFTTApi::getCollection($this->getData('https://www.fftt.com/mobile/pxml/xml_liste_joueur.php', array('club' => $club)), 'joueur');
        }

        public function getEquipesByClub($club, $type = null)
        {
            if ($type && !in_array($type, array('M', 'F'))) {
                $type = 'M';
            }

            $key = $this->buildCacheKey('equipes_club', array('numclu' => $club, 'type' => $type));
            $lifeTime = $this->computeHalfDayTtl();
            $teams = $this->getCachedData($key, $lifeTime, function () use ($club, $type) {
                $result = AccesFFTTApi::getCollection(
                    $this->getData('https://www.fftt.com/mobile/pxml/xml_equipe.php', array('numclu' => $club, 'type' => $type)),
                    'equipe'
                );

                foreach ($result as &$team) {
                    $team['idpoule'] = null;
                    $team['iddiv']   = null;
                    if (isset($team['liendivision']) && is_string($team['liendivision']) && $team['liendivision'] !== '') {
                        $qs = strpos($team['liendivision'], '?') !== false
                            ? parse_url($team['liendivision'], PHP_URL_QUERY)
                            : $team['liendivision'];
                        $params = array();
                        parse_str((string) $qs, $params);
                        $team['idpoule'] = isset($params['cx_poule']) ? $params['cx_poule'] : null;
                        $team['iddiv']   = isset($params['D1'])       ? $params['D1']       : null;
                    }
                }

                return $result;
            });

            return $teams;
        }

        public function getPoules($division)
        {
            $poules = AccesFFTTApi::getCollection($this->getData('https://www.fftt.com/mobile/pxml/xml_result_equ.php', array('action' => 'poule', 'D1' => $division)), 'poule');

            foreach ($poules as &$poule) {
                $params = array();
                parse_str($poule['lien'], $params);

                $poule['idpoule'] = isset($params['cx_poule']) ? $params['cx_poule'] : null;
                $poule['iddiv'] = isset($params['D1']) ? $params['D1'] : null;
            }

            return $poules;
        }

        public function getPouleClassement($division, $poule = null)
        {
            $key = $this->buildCacheKey('poule_classement', array('D1' => $division, 'cx_poule' => $poule));
            $lifeTime = $this->computeHalfDayTtl();
            return $this->getCachedData($key, $lifeTime, function () use ($division, $poule) {
                return AccesFFTTApi::getCollection($this->getData('https://www.fftt.com/mobile/pxml/xml_result_equ.php', array('auto' => 1, 'action' => 'classement', 'D1' => $division, 'cx_poule' => $poule)), 'classement');
            });
        }

        public function getPouleRencontres($division, $poule = null)
        {
            $key = $this->buildCacheKey('poule_rencontres', array('D1' => $division, 'cx_poule' => $poule));
            $lifeTime = $this->computeHalfDayTtl();
            return $this->getCachedData($key, $lifeTime, function () use ($division, $poule) {
                return AccesFFTTApi::getCollection($this->getData('https://www.fftt.com/mobile/pxml/xml_result_equ.php', array('auto' => 1, 'D1' => $division, 'cx_poule' => $poule)), 'tour');
            });
        }


        public function getLicencesByName($nom, $prenom = '')
        {
            return AccesFFTTApi::getCollection($this->getData('https://www.fftt.com/mobile/pxml/xml_liste_joueur_o.php', array('nom' => strtoupper($nom), 'prenom' => ucfirst($prenom))), 'joueur');
        }

        public function getLicencesByClub($club)
        {
            return AccesFFTTApi::getCollection(
                $this->getData('https://www.fftt.com/mobile/pxml/xml_liste_joueur.php', array('club' => $club)),
                'joueur'
            );
        }

        public function getLicence($licence)
        {
            return AccesFFTTApi::getObject($this->getData('https://www.fftt.com/mobile/pxml/xml_licence.php', array('licence' => $licence)), 'licence');
        }

        public function getLicencesByClubComplet($club)
        {
            $data = $this->getData('https://www.fftt.com/mobile/pxml/xml_licence_b.php', array('club' => $club));
            return AccesFFTTApi::getCollection($data, 'licence');
        }

        /**
         * Retourne le détail d'une rencontre (feuille de match) :
         * composition des équipes et résultats partie par partie.
         * Cache longue durée (1 semaine) car les résultats passés ne changent pas.
         *
         * @param string $rencId   Identifiant de la rencontre (champ renc_id du lien)
         * @param int    $isRetour Match retour (0 = aller, 1 = retour)
         * @return array|false
         */
        public function getRencontreDetail($rencId, $isRetour = 0)
        {
            $key      = $this->buildCacheKeyPublic('renc_detail', array('renc_id' => $rencId, 'is_retour' => $isRetour));
            $lifeTime = 7 * DAY_IN_SECONDS;

            return $this->getCachedDataPublic($key, $lifeTime, function () use ($rencId, $isRetour) {
                return $this->getData('https://www.fftt.com/mobile/pxml/xml_chp_renc.php', array(
                    'renc_id'   => $rencId,
                    'is_retour' => $isRetour,
                ));
            });
        }

        private function getCachedData($key, $lifeTime, $callback)
        {
            // Use WordPress transients when available; otherwise, bypass cache
            if (!function_exists('get_transient') || !function_exists('set_transient')) {
                return $callback($this);
            }

            $data = get_transient($key);
            if ($data === false) {
                $data = $callback($this);
                // Store data and last update timestamp
                set_transient($key, $data, (int) $lifeTime);
                set_transient($key . '__updated_at', time(), (int) $lifeTime);
            }

            return $data;
        }

        public function getCachedDataPublic($key, $lifeTime, $callback)
        {
            return $this->getCachedData($key, $lifeTime, $callback);
        }

        private function buildCacheKey($prefix, array $params)
        {
            ksort($params);
            $base = $prefix . '|' . http_build_query($params);
            return 'dataping_' . md5($base);
        }

        public function buildCacheKeyPublic($prefix, array $params)
        {
            return $this->buildCacheKey($prefix, $params);
        }

        private function computeHalfDayTtl()
        {
            // Use WP local time if available
            $now = function_exists('current_time') ? (int) current_time('timestamp') : time();

            // Define target times today at 08:00 and 13:00
            $today = getdate($now);
            $mk = function ($hour) use ($today) {
                return mktime($hour, 0, 0, $today['mon'], $today['mday'], $today['year']);
            };
            $t8 = $mk(8);
            $t13 = $mk(13);

            if ($now < $t8) {
                $next = $t8;
            } elseif ($now < $t13) {
                $next = $t13;
            } else {
                // Next day 08:00
                $next = $t8 + 86400;
            }
            $ttl = max(60, $next - $now);
            return $ttl;
        }

        public function computeHalfDayTtlPublic()
        {
            return $this->computeHalfDayTtl();
        }

        public function getCacheUpdatedAt($prefix, array $params)
        {
            if (!function_exists('get_transient')) {
                return false;
            }
            $key = $this->buildCacheKey($prefix, $params) . '__updated_at';
            $ts = get_transient($key);
            return $ts === false ? false : (int) $ts;
        }

        /**
         * Supprime le cache des équipes d'un club
         * @param string $club Numéro du club
         */
        public function clearEquipesCache($club)
        {
            if (!function_exists('delete_transient')) {
                return;
            }

            $types = array('M', 'F');
            foreach ($types as $type) {
                $key = $this->buildCacheKey('equipes_club', array('numclu' => $club, 'type' => $type));
                delete_transient($key);
                delete_transient($key . '__updated_at');
            }
        }

        /**
         * Supprime le cache d'une poule (classement et rencontres)
         * @param string $division Division
         * @param string $poule Poule
         */
        public function clearPouleCache($division, $poule)
        {
            if (!function_exists('delete_transient')) {
                return;
            }

            $keyClassement = $this->buildCacheKey('poule_classement', array('D1' => $division, 'cx_poule' => $poule));
            delete_transient($keyClassement);
            delete_transient($keyClassement . '__updated_at');

            $keyRencontres = $this->buildCacheKey('poule_rencontres', array('D1' => $division, 'cx_poule' => $poule));
            delete_transient($keyRencontres);
            delete_transient($keyRencontres . '__updated_at');
        }

        /**
         * Supprime le cache des joueurs d'un club
         * @param string $club Numéro du club
         */
        public function clearJoueursCache($club)
        {
            if (!function_exists('delete_transient')) {
                return;
            }

            $key = $this->buildCacheKey('joueurs_club', array('numclu' => $club));
            delete_transient($key);
            delete_transient($key . '__updated_at');
        }

        public function getData($url, $params = array(), $generateHash = true, $silentErrors = false)
        {
            if ($generateHash) {
                $params['serie'] = $this->getSerial();
                $params['id'] = $this->getAppId();
                $params['tm'] = gmdate('YmdHis') . substr(microtime(), 2, 3);
                $params['tmc'] = hash_hmac('sha1', $params['tm'], hash('md5', $this->getAppKey(), false));
            }

            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            // Stocker le log pour affichage dans l'interface admin (URL sans credentials)
            $logUrl = preg_replace('/([?&])(id|serie|tm|tmc)=[^&]*/', '$1[REDACTED]', $url);
            $this->addApiLog("Appel API: $logUrl");

            $response = wp_remote_get($url, array(
                'timeout'     => 30,
                'redirection' => 5,
                'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'headers'     => array('Accept' => 'text/xml,application/xml,*/*'),
            ));

            if (is_wp_error($response)) {
                if (!$silentErrors) {
                    $msg = 'Erreur HTTP : ' . $response->get_error_message();
                    $this->addApiLog($msg, 'error');
                }
                return false;
            }

            $httpCode    = wp_remote_retrieve_response_code($response);
            $contentType = wp_remote_retrieve_header($response, 'content-type');
            $data        = wp_remote_retrieve_body($response);
            $dataLength  = strlen($data);

            // Log de diagnostic
            if (!$silentErrors) {
                $this->addApiLog("HTTP $httpCode | Type: $contentType | Taille: $dataLength octets", $httpCode === 200 ? 'info' : 'error');
            }

            if ($httpCode !== 200) {
                if (!$silentErrors) {
                    $this->addApiLog("Code HTTP $httpCode", 'error');
                }
                return false;
            }

            // Vérifier que la réponse n'est pas vide
            if (empty($data)) {
                if (!$silentErrors) {
                    $msg = "RÉPONSE VIDE de l'API FFTT - Vérifiez vos identifiants (ID: '$this->appId')";
                    $this->addApiLog($msg, 'error');
                }
                return false;
            }

            // L'API FFTT peut retourner du XML en ISO-8859-1 ou UTF-8
            // Détecter l'encodage réel pour éviter la double conversion qui corrompt les accents
            if (preg_match('/encoding=["\']ISO-8859-1["\']/i', $data)) {
                $realEncoding = mb_detect_encoding($data, ['UTF-8', 'ISO-8859-1'], true);

                if ($realEncoding === 'ISO-8859-1') {
                    // Contenu réellement en ISO-8859-1, convertir en UTF-8
                    $data = mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
                }

                // Dans tous les cas, mettre à jour la déclaration XML
                $data = preg_replace('/encoding=["\']ISO-8859-1["\']/i', 'encoding="UTF-8"', $data);
            }

            $xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);

            if (!$xml) {
                if (!$silentErrors) {
                    $msg = "Erreur parsing XML - Data: " . substr($data, 0, 200);
                    $this->addApiLog($msg, 'error');
                }
                return false;
            }

            $this->addApiLog("Réponse OK (HTTP 200)", 'success');

            // Petite astuce pour transformer simplement le XML en tableau
            return json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE), true);
        }

        public static function getCollection($data, $key = null)
        {
            if (empty($data)) {
                return array();
            }

            if ($key) {
                if (!array_key_exists($key, $data)) {
                    return array();
                }
                $data = $data[$key];
            }

            return isset($data[0]) ? $data : array($data);
        }

        public static function getObject($data, $key = null)
        {
            if ($key && $data !== false) {
                return array_key_exists($key, $data) ? $data[$key] : null;
            } else {
                return empty($data) ? null : $data;
            }
        }

        /**
         * Ajoute un log d'API pour affichage dans l'interface admin
         */
        private function addApiLog($message, $type = 'info')
        {
            $logs = get_option('dataping_api_logs', array());

            // Ne garder que les 50 derniers logs
            if (count($logs) >= 50) {
                $logs = array_slice($logs, -49);
            }

            $logs[] = array(
                'time' => current_time('mysql'),
                'type' => $type,
                'message' => $message
            );

            update_option('dataping_api_logs', $logs);
        }

        /**
         * Récupère les logs d'API
         */
        public static function getApiLogs()
        {
            return get_option('dataping_api_logs', array());
        }

        /**
         * Efface les logs d'API
         */
        public static function clearApiLogs()
        {
            delete_option('dataping_api_logs');
        }

        public static function generateSerial()
        {
            $serial = '';
            for ($i = 0; $i < 15; $i++) {
                $serial .= chr(wp_rand(65, 90)); //(A-Z)
            }

            return $serial;
        }

    }

}