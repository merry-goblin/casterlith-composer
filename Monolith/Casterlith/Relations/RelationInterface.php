<?php

/**
 * This file is part of Casterlith.
 *
 * @link https://github.com/merry-goblin/casterlith
 */

namespace Monolith\Casterlith\Relations;

interface RelationInterface
{
	public function getCondition($fromAlias, $toAlias);
	public function getMapper();
}
