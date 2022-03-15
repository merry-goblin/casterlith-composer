<?php

/**
 * This file is part of Casterlith.
 *
 * @link https://github.com/merry-goblin/casterlith
 */

namespace Monolith\Casterlith;

use Doctrine\DBAL\Version;

/**
 * Casterlith configuration
 * 
 * This class allows to change the behavior of Casterlith
 */
class Configuration extends \Doctrine\DBAL\Configuration
{
	//	Monolith\Casterlith\Schema\Builder gets PDOStatement or Result according to DBAL version. 
	//	Methods for those two class are not ISO
	protected $usePDOStatement = false;

	public function initVersion()
	{
		if (Version::compare('3.0.0') === 1) {
			$this->usePDOStatement = true;
		}
	}

	public function doesPDOStatementIsUsed()
	{
		return $this->usePDOStatement;
	}

	/**
	 * Setter for replacer parameter
	 * 
	 * Set the replacer to use when building aliases in selection
	 *
	 * @param  string  $replacer
	 * @return null
	 * @throws Exception
	 */
	public function setSelectionReplacer($replacer = "cl")
	{
		$type = gettype($replacer);
		if ($type !== "string") {
			throw new \Exception('In setSelectionReplacer($replacer), $replacer has to be a string, '.$type.' given.');
		}

		$this->_attributes['replacer'] = $replacer;
	}

	/**
	 * Getter for replacer parameter
	 * 
	 * Get the replacer to use when building aliases in selection
	 *
	 * @return string
	 */
	public function getSelectionReplacer()
	{
		return isset($this->_attributes['replacer']) ? $this->_attributes['replacer'] : "cl";
	}

	/**
	 * Setter for firstAutoSelection parameter
	 * 
	 * When 'first' method is called on a composer automatically force only one result
	 * 2 sql requests will be used to get a result if true but no useless data will be retrieve.
	 * To prevent the 2 sql requests to be called, set to false and make sure that your request 
	 * get only one result to ensure good effeciency.
	 *
	 * @param  string  $firstAutoSelection
	 * @return null
	 */
	public function setFirstAutoSelection($firstAutoSelection = true)
	{
		$this->_attributes['firstAutoSelection'] = $firstAutoSelection;
	}

	/**
	 * Getter for firstAutoSelection parameter
	 * 
	 * Get the replacer to use when building aliases in selection
	 *
	 * @return string
	 */
	public function getFirstAutoSelection()
	{
		return isset($this->_attributes['firstAutoSelection']) ? $this->_attributes['firstAutoSelection'] : true;
	}

	/**
	 * Setter for exceptionMultipleResultOnFirst parameter
	 *
	 * When firstAutoSelection is false you will get an exception is first send back more 
	 * than one result. It is better to not use this configuration at true on a production
	 * environment.
	 * 
	 * @param  string  $exceptionMultipleResultOnFirst
	 * @return null
	 * @throws Exception
	 */
	public function setExceptionMultipleResultOnFirst($exceptionMultipleResultOnFirst = false)
	{
		$this->_attributes['exceptionMultipleResultOnFirst'] = $exceptionMultipleResultOnFirst;
	}

	/**
	 * Getter for exceptionMultipleResultOnFirst parameter
	 * 
	 * Get the replacer to use when building aliases in selection
	 *
	 * @return string
	 */
	public function getExceptionMultipleResultOnFirst()
	{
		return isset($this->_attributes['exceptionMultipleResultOnFirst']) ? $this->_attributes['exceptionMultipleResultOnFirst'] : false;
	}

}
