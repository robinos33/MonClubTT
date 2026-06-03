<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Created by PhpStorm.
 * User: robin
 * Date: 06/09/2017
 * Time: 22:00
 */
class Equipes {
	private $_api;
	private $_equipes = array();

	public function __construct() {
		$this->_api = AccesFFTTApi::getInstance();

		$listeEquipesM = $this->_api->getEquipesByClub( ParametresPlugin::getNumClub(), 'M' );
		$listeEquipesF = $this->_api->getEquipesByClub( ParametresPlugin::getNumClub(), 'F' );
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
		// Utiliser un tableau associatif pour dédupliquer par libequipe
		$equipesUniques = array();

		foreach ( $listeEquipesM as $equipe ) {
			$key = $equipe['libequipe'];
			$equipesUniques[$key] = new Equipe( $equipe, 'M' );
		}

		foreach ( $listeEquipesF as $equipe ) {
			$key = $equipe['libequipe'];
			// Si l'équipe existe déjà (même nom), on ne l'ajoute pas
			if (!isset($equipesUniques[$key])) {
				$equipesUniques[$key] = new Equipe( $equipe, 'F' );
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
	 * @return Equipe[]
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
	 * @return Equipe[]
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