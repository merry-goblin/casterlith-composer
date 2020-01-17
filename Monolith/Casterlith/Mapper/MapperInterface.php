<?php

/**
 * This file is part of Casterlith.
 *
 * @link https://github.com/merry-goblin/casterlith
 */

namespace Monolith\Casterlith\Mapper;

interface MapperInterface
{
	public function getTable();
	public function getEntity();
	public static function getPrimaryKey();
	public static function getFields();
	public static function getRelations();
	public static function getRelation($relName);
}
