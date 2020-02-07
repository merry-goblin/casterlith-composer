<?php

/**
 * This file is part of Casterlith.
 *
 * @link https://github.com/merry-goblin/casterlith
 */

namespace Monolith\Casterlith\Schema;

class Selection
{
	public $alias              = null;
	public $replacer           = null;
	public $relations          = null;
	public $primaryKey         = null;
	public $realPrimaryKey     = null;
	public $replacedPrimaryKey = null;
	public $loaded             = null;

	public function __construct($alias, $replacer)
	{
		$this->alias            = $alias;
		$this->replacer         = $replacer;
		$this->relations        = array();
		$this->loaded           = array();
	}
}