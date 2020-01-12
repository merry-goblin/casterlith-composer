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
	public static function getRelation($relName = null)
	{
		if (empty($relName)) {
			throw new \Exception("Relation name can't be either empty or null");
		}

		if (is_null(static::$relations)) {
			static::getRelations();
		}

		if (!isset(static::$relations[$relName])) {
			throw new \Exception("Relation with name ".$relName." doesn't exist for table ".static::$table);
		}

		return static::$relations[$relName];
	}
}
