<?php

namespace Monolith\Casterlith\Relations;

use Monolith\Casterlith\Mapper\MapperInterface;

class OneToOne extends AbstractRelation implements RelationInterface
{
	public static function getType()
	{
		return "OneToOne";
	}
}