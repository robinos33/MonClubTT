<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_Equipes' ) ) {

/**
 * Created by PhpStorm.
 * User: robin
 * Date: 06/09/2017
 * Time: 22:00
 */
class MonClubTT_Equipes {
	private $_api;
	private $_equipes = array();

	public function __construct() {
		$this->_api = MonClubTT_AccesFFTTApi::getInstance();

		$listeEquipesM = $this->_api->getEquipesByClub( MonClubTT_ParametresPlugin::getNumClub(), 'M' );
		$listeEquipesF = $this->_api->getEquipesByClub( MonClubTT_ParametresPlugin::getNumClub(), 'F' );
		$this->_setEquipesFromApi( $listeEquipesM, $listeEquipesF );
	}

	/**
	 * Construction de la liste des joueurs
	 *
	 * @param $listeEquipesM
	 * @param $listeEquipesF
	 *
	 * @internal param array $listeEquipes
	 */
	private function _setEquipesFromApi( $listeEquipesM, $listeEquipesF ) {
		// Dédupliquer par poule (iddiv + idpoule), et non par nom d'équipe :
		// une même équipe apparaît sur plusieurs lignes (championnat, coupe, etc.),
		// chacune avec un idpoule différent. Une dédup par nom écraserait ces
		// épreuves entre elles et ferait disparaître des équipes de la synchro.
		$equipesUniques = array();

		foreach ( $listeEquipesM as $equipe ) {
			$key = $this->_buildEquipeKey( $equipe );
			if ( ! isset( $equipesUniques[$key] ) ) {
				$equipesUniques[$key] = new MonClubTT_Equipe( $equipe, 'M' );
			}
		}

		foreach ( $listeEquipesF as $equipe ) {
			$key = $this->_buildEquipeKey( $equipe );
			// Ne pas réintroduire une poule déjà présente (équipe renvoyée par
			// les requêtes M et F à la fois).
			if ( ! isset( $equipesUniques[$key] ) ) {
				$equipesUniques[$key] = new MonClubTT_Equipe( $equipe, 'F' );
			}
		}

		// Convertir en tableau indexé
		$this->_equipes = array_values($equipesUniques);

		// Trier les équipes par numéro d'équipe (extrait du libequipe)
		usort($this->_equipes, function($a, $b) {
			// Extraire le numéro de l'équipe depuis le libequipe (ex: "US TALENCE 15 - Phase 1")
			preg_match('/(\d+)/', $a->getLibequipe(), $matchesA);
			preg_match('/(\d+)/', $b->getLibequipe(), $matchesB);

			$numA = isset($matchesA[1]) ? intval($matchesA[1]) : 0;
			$numB = isset($matchesB[1]) ? intval($matchesB[1]) : 0;

			return $numA - $numB;
		});
	}

	/**
	 * Construit une clé unique identifiant une équipe dans une épreuve/poule
	 * donnée. On se base sur iddiv + idpoule (unique par poule). Si ces
	 * informations sont absentes (pas de liendivision), on retombe sur une
	 * combinaison nom + épreuve pour éviter de fusionner des lignes distinctes.
	 *
	 * @param array $equipe
	 * @return string
	 */
	private function _buildEquipeKey( $equipe ) {
		$iddiv   = isset( $equipe['iddiv'] ) ? $equipe['iddiv'] : null;
		$idpoule = isset( $equipe['idpoule'] ) ? $equipe['idpoule'] : null;

		if ( $iddiv !== null && $iddiv !== '' && $idpoule !== null && $idpoule !== '' ) {
			return $iddiv . '|' . $idpoule;
		}

		$libequipe = isset( $equipe['libequipe'] ) ? $equipe['libequipe'] : '';
		$idepr     = isset( $equipe['idepr'] ) ? $equipe['idepr'] : '';
		$libepr    = isset( $equipe['libepr'] ) ? $equipe['libepr'] : '';

		return 'name|' . $libequipe . '|' . $idepr . '|' . $libepr;
	}

	public function getEquipes( $sexe ) {
		$equipes = array();
		switch ( $sexe ) {
			default:
			case 'MF':
				$equipes = $this->_equipes;
				break;
			case 'F':
			case 'M':
				foreach ( $this->_equipes as $equipe ) {
					if($equipe->getType() === $sexe){
						$equipes[] = $equipe;
					}
				}
				break;
		}

		return $equipes;
	}

	/**
	 * Retourne uniquement les équipes de championnat par équipes (exclut les coupes).
	 * Filtre les épreuves dont le libellé contient "coupe" (insensible à la casse).
	 *
	 * @param string $sexe 'MF', 'M' ou 'F'
	 * @return MonClubTT_Equipe[]
	 */
	public function getEquipesChampionnat( $sexe = 'MF' ) {
		return array_values( array_filter(
			$this->getEquipes( $sexe ),
			function ( $equipe ) {
				return stripos( $equipe->getLibepr(), 'coupe' ) === false;
			}
		) );
	}

	/**
	 * Retourne uniquement les équipes du championnat sénior par équipes.
	 * Seules les épreuves dont le libellé contient "par equipes" sont conservées,
	 * ce qui exclut les coupes, championnats jeunes et compétitions vétérans.
	 *
	 * @param string $sexe 'MF', 'M' ou 'F'
	 * @return MonClubTT_Equipe[]
	 */
	public function getEquipesSeniorChampionnat( $sexe = 'MF' ) {
		return array_values( array_filter(
			$this->getEquipes( $sexe ),
			function ( $equipe ) {
				return stripos( $equipe->getLibepr(), 'par equipes' ) !== false;
			}
		) );
	}
}

}
