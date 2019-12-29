<?php

namespace Monolith\Casterlith\Mapper;

use Doctrine\DBAL\Query\QueryBuilder;

abstract class AbstractMapper
{
	public function getTable()
	{
		return $this::$table;
	}

	public function getEntity()
	{
		return $this::$entity;
	}

	/**
	 * @param  string  $relName
	 * @return Merry\Core\Services\Orm\Casterlith\Relations\RelationInterface
	 * @throws Exception
	 */
	public static function getRelation($relName)
	{
		if (is_null(static::$relations)) {
			static::getRelations();
		}

		if (!isset(static::$relations[$relName])) {
			throw new \Exception("Relation with name ".$relName." doesn't exist");
		}

		return static::$relations[$relName];
	}
}
