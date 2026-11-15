<?php
/**
 * API HTTP de WordPress réduite à ce qu'utilise MonClubTT_ClientMeta, pour
 * les tests sans WordPress. Comme dans WP_Http::request(), une réponse
 * fournie par le filtre « pre_http_request » court-circuite l'appel réseau ;
 * ici, aucun appel réseau n'est jamais fait.
 */

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $message;

        public function __construct(string $code = '', string $message = '')
        {
            $this->message = $message;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

/** Remplace le filtre pre_http_request (null : aucun). */
function monclubtt_test_pre_http_request(?callable $filtre): void
{
    $GLOBALS['monclubtt_test_pre_http_request'] = $filtre;
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = array())
    {
        $filtre = $GLOBALS['monclubtt_test_pre_http_request'] ?? null;
        if (!$filtre) {
            return new WP_Error('http_request_failed', 'Aucun appel réseau en test.');
        }
        return $filtre($url, $args + array('method' => 'GET'));
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return is_array($response) ? (string) ($response['body'] ?? '') : '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return is_array($response) ? (int) ($response['response']['code'] ?? 0) : '';
    }
}
