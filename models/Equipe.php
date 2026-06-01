<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Created by PhpStorm.
 * User: robin
 * Date: 06/09/2017
 * Time: 22:00
 */
class Equipe {
	private $_libequipe;
	private $_libdivision;
	private $_liendivision;
	private $_idepr;
	private $_libepr;
	private $_idpoule;
	private $_iddiv;
	private $_type;

	public function __construct($equipe, $type){
		$this->setLibequipe($equipe['libequipe']);
		$this->setLibdivision($equipe['libdivision']);
		$this->setLiendivision($equipe['liendivision']);
		$this->setIdepr($equipe['idepr']);
		$this->setLibepr($equipe['libepr']);
		$this->setIdpoule($equipe['idpoule']);
		$this->setIddiv($equipe['iddiv']);
		$this->setType($type);
	}

	/**
	 * @return mixed
	 */
	public function getLibequipe() {
		return $this->_libequipe;
	}

	/**
	 * @param mixed $libequipe
	 *
	 * @return Equipe
	 */
	public function setLibequipe( $libequipe ) {
		$this->_libequipe = $libequipe;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getLibdivision() {
		return $this->_libdivision;
	}

	/**
	 * @param mixed $libdivision
	 *
	 * @return Equipe
	 */
	public function setLibdivision( $libdivision ) {
		$this->_libdivision = $libdivision;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getLiendivision() {
		return $this->_liendivision;
	}

	/**
	 * @param mixed $liendivision
	 *
	 * @return Equipe
	 */
	public function setLiendivision( $liendivision ) {
		$this->_liendivision = $liendivision;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getIdepr() {
		return $this->_idepr;
	}

	/**
	 * @param mixed $idepr
	 *
	 * @return Equipe
	 */
	public function setIdepr( $idepr ) {
		$this->_idepr = $idepr;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getLibepr() {
		return $this->_libepr;
	}

	/**
	 * @param mixed $libepr
	 *
	 * @return Equipe
	 */
	public function setLibepr( $libepr ) {
		$this->_libepr = $libepr;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getIdpoule() {
		return $this->_idpoule;
	}

	/**
	 * @param mixed $idpoule
	 *
	 * @return Equipe
	 */
	public function setIdpoule( $idpoule ) {
		$this->_idpoule = $idpoule;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getIddiv() {
		return $this->_iddiv;
	}

	/**
	 * @param mixed $iddiv
	 *
	 * @return Equipe
	 */
	public function setIddiv( $iddiv ) {
		$this->_iddiv = $iddiv;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getType() {
		return $this->_type;
	}

	/**
	 * @param mixed $type
	 *
	 * @return Equipe
	 */
	public function setType( $type ) {
		$this->_type = $type;

		return $this;
	}
}