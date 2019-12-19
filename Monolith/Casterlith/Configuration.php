<?php

namespace Monolith\Casterlith;

class Configuration extends \Doctrine\DBAL\Configuration
{
	/**
	 * Set the replacer to use when building aliases in selection
	 *
	 * @param  string $replacer
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
	 * Get the replacer to use when building aliases in selection
	 *
	 * @return string
	 */
	public function getSelectionReplacer()
	{
		return isset($this->_attributes['replacer']) ? $this->_attributes['replacer'] : "cl";
	}

}
