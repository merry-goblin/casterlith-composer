<?php

namespace Monolith\Casterlith\Relations;

use Monolith\Casterlith\Mapper\MapperInterface;

class ManyToOne extends AbstractRelation implements RelationInterface
{
	public static function getType()
	{
		return "ManyToOne";
	}
}
