<?php

/**
 * This file is part of Casterlith.
 *
 * @link https://github.com/merry-goblin/casterlith
 */

namespace Monolith\Casterlith\Relations;

use Monolith\Casterlith\Mapper\MapperInterface;

abstract class AbstractRelation
{
	protected $mapper;
	protected $fromAlias    = null;
	protected $toAlias      = null;
	protected $condition    = null;
	protected $reversedBy   = null;

	public function __construct(MapperInterface $mapper, $fromAlias, $toAlias, $condition, $reversedBy = null)
	{
		$this->mapper      = $mapper;
		$this->fromAlias   = $fromAlias;
		$this->toAlias     = $toAlias;
		$this->condition   = $condition;
		$this->reversedBy  = $reversedBy;
	}

	public function getCondition($fromAlias = null, $toAlias = null)
	{
		if (empty($fromAlias)) {
			throw new \Exception("From Alias can't be either empty or null");
		}
		if (empty($toAlias)) {
			throw new \Exception("To Alias can't be either empty or null");
		}

		$condition = str_replace("`".$this->fromAlias."`.", "`".$fromAlias."`.", $this->condition);
		$condition = str_replace("`".$this->toAlias."`.", "`".$toAlias."`.", $condition);

		return $condition;
	}

	public function getMapper()
	{
		return $this->mapper;
	}

	public function getReversedBy()
	{
		return $this->reversedBy;
	}
}
