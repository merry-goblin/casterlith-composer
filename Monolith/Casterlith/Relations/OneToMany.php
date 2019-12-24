<?php

namespace Monolith\Casterlith\Relations;

use Monolith\Casterlith\Mapper\MapperInterface;

class OneToMany extends AbstractRelation implements RelationInterface
{
	public static function getType()
	{
		return "OneToMany";
	}
}
