<?php

/**
 * This file is part of Casterlith.
 *
 * @link https://github.com/merry-goblin/casterlith
 */

namespace Monolith\Casterlith\Schema;

use Monolith\Casterlith\Casterlith;
use Monolith\Casterlith\Entity\EntityInterface;
use Monolith\Casterlith\Mapper\MapperInterface;
use Monolith\Casterlith\Relations\ManyToOne;
use Monolith\Casterlith\Relations\OneToMany;
use Monolith\Casterlith\Relations\OneToOne;

use Doctrine\DBAL\Driver\PDOStatement;
use Doctrine\DBAL\Query\QueryBuilder;

class Builder
{
	protected $queryBuilder     = null;
	protected $connection       = null;

	protected $mapperList      = null;
	protected $selectionList   = null;
	protected $jointList       = null;

	protected $num             = 0;
	protected $customReplacer  = "cl";
	protected $usePDOStatement = false; // PHP < 7

	protected $rootAlias       = null;

	/**
	 * @param  Doctrine\DBAL\Query\QueryBuilder  $queryBuilder
	 * @param  string                            $customReplacer
	 * @param  boolean                           $usePDOStatement
	 */
	public function __construct(QueryBuilder $queryBuilder, $customReplacer, $usePDOStatement = false)
	{
		$this->queryBuilder     = $queryBuilder;
		$this->connection       = $this->queryBuilder->getConnection();

		$this->customReplacer   = $customReplacer;
		$this->usePDOStatement  = $usePDOStatement;

		$this->mapperList       = array();
		$this->jointList        = array();
		$this->selectionList    = array();
	}

	/**
	 * Defines an unique alias to a table
	 * 
	 * @param  string  $alias
	 * @return null
	 */
	public function select($alias = null)
	{
		if (empty($alias)) {
			throw new \Exception("Alias can't be either empty or null");
		}
		if (isset($this->selectionList[$alias])) {
			throw new \Exception("Alias already exists in select method of Monolith\Casterlith\Schema\Builder class");
		}

		$this->num++;
		$replacer = $alias.$this->customReplacer.$this->num."_";

		$this->selectionList[$alias] = new Selection($alias, $replacer);
	}

	/**
	 * @param  string $alias
	 * @param  Monolith\Casterlith\Mapper\MapperInterface $mapper
	 * @return null
	 */
	public function from($alias, MapperInterface $mapper)
	{
		if (!empty($this->rootAlias)) {
			throw new \Exception("This method can't be called twice");
		}
		$this->rootAlias = $alias;
		$this->mapperList[$alias] = $mapper;
	}

	/**
	 * To have an association in selection call select first
	 * If not called first association will not be builded
	 * 
	 * @param string $fromAlias
	 * @param string $toAlias
	 * @param string $relName
	 */
	public function join($fromAlias, $toAlias, $relationKey)
	{
		if (empty($fromAlias)) {
			throw new \Exception("From Alias can't be either empty or null");
		}
		if (empty($toAlias)) {
			throw new \Exception("To Alias can't be either empty or null");
		}
		if (empty($relationKey)) {
			throw new \Exception("Relation key can't be either empty or null");
		}
		if (!isset($this->mapperList[$fromAlias])) {
			throw new \Exception("Entity alias with name ".$fromAlias." doesn't exist");
		}
		$fromMapper    = $this->mapperList[$fromAlias];
		$fromRelation  = $fromMapper->getRelation($relationKey);
		$toMapper      = $fromRelation->getMapper();

		$this->addMapper($toAlias, $toMapper);
		$this->addJoint($fromAlias, $toAlias, $relationKey, $fromRelation);
		$this->addJointOnSelection($fromAlias, $toAlias, $relationKey);
		
		//	Association in both ways
		$reversedBy  = $fromRelation->getReversedBy();
		if (!is_null($reversedBy)) {
			$toRelation  = $toMapper->getRelation($reversedBy);
			$this->addJoint($toAlias, $fromAlias, $reversedBy, $toRelation);
			$this->addJointOnSelection($toAlias, $fromAlias, $reversedBy);
		}

		//	Response for DBal's queryBuilder
		$table       = $toMapper->getTable();
		$condition   = $this->getReplacedJoinCondition($fromAlias, $toAlias, $fromRelation);

		return array($table, $condition);
	}

	private function getReplacedJoinCondition($fromAlias, $toAlias, $fromRelation)
	{
		$condition = $fromRelation->getCondition($fromAlias, $toAlias);

		$condition = $this->getReplacedFieldOfEntity($fromAlias, $condition);
		$condition = $this->getReplacedFieldOfEntity($toAlias, $condition);

		return $condition;
	}

	public function getReplacedFieldOfEntity($alias, $literal)
	{
		$mapper = $this->mapperList[$alias];

		$pattern = "#`?\b".$alias."\b`?\.`?\b([0-9a-zA-Z$\_]+)\b`?#";
		$matches = array();
		$result = preg_match_all($pattern, $literal, $matches, PREG_SET_ORDER);
		if ($result >= 0) {

			$fields = $mapper->getFields();
			foreach ($matches as $match) {
				$aliasAndField = $match[0];
				$fieldName     = $match[1];
				if (isset($fields[$fieldName])) {
					$replacedAliasAndField = "`".$alias."`.`".$fields[$fieldName]['name']."`";
					$limit = 1;
					$literal = preg_replace("#".$aliasAndField."#", $replacedAliasAndField, $literal, $limit);
				}
			}
		}

		return $literal;
	}

	public function getReplacedFieldsOfAnyEntity($literal)
	{
		foreach ($this->mapperList as $alias => $mapper) {
			$literal = $this->getReplacedFieldOfEntity($alias, $literal);
		}

		return $literal;
	}

	/**
	 * Because two tables (or two aliases of the same table) can have the same field names
	 * we change field names in a way those field names will be unique
	 * If those field names are not unique after all, have a look at the 
	 * Monolith\Casterlith\Configuration::setSelectionReplacer method
	 * 
	 * @param  string $alias
	 * @return string
	 */
	public function getAUniqueSelection($alias)
	{
		$replacer  = $this->getReplacer($alias);
		$mapper    = $this->getMapper($alias);
		$fields    = $mapper::getFields();

		$selection = "";
		foreach ($fields as $key => $field) {
			if (!empty($selection)) {
				$selection .= ",";
			}
			$selection .= $alias.".".$field['name']." as ".$replacer.$field['name'];
		}

		return $selection;
	}

	/**
	 * Because two tables (or two aliases of the same table) can have the same field names
	 * we change field names in a way those field names will be unique
	 * If those field names are not unique after all, have a look at the 
	 * Monolith\Casterlith\Configuration::setSelectionReplacer method
	 * 
	 * @param  string $alias
	 * @return string
	 */
	public function getAUniqueSelectionFromRaw($rawSelection)
	{
		/*$replacer  = $this->getReplacer($alias);
		$mapper    = $this->getMapper($alias);
		$fields    = $mapper::getFields();

		$selection = "";
		foreach ($fields as $key => $field) {
			if (!empty($selection)) {
				$selection .= ",";
			}
			$selection .= $alias.".".$key." as ".$replacer.$key;
		}*/

		$rawSelection = $this->getReplacedFieldsOfAnyEntity($rawSelection);

		return $rawSelection;
	}

	/**
	 * This method does no optimization. Optimization is up to the caller
	 * 
	 * @param  Doctrine\DBAL\Driver\PDOStatement  $statement [dbal seems to use Doctrine\DBAL\ForwardCompatibility\Result now]
	 * @param  boolean $exceptionMultipleResultOnFirst
	 * @return array(Monolith\Casterlith\Entity\EntityInterface)
	 */
	public function buildFirst(/*PDOStatement*/ $statement, $exceptionMultipleResultOnFirst = false)
	{
		$this->build($statement);

		if ($exceptionMultipleResultOnFirst) {
			if (count($this->selectionList[$this->rootAlias]->loaded) > 1) {
				throw new \Exception("More than one result");
			}
		}

		$entity = null;
		foreach ($this->selectionList[$this->rootAlias]->loaded as $rootEntity) {
			$entity = $rootEntity;
			break;
		}

		return $entity;
	}

	/**
	 * @param  Doctrine\DBAL\Driver\PDOStatement  $statement [dbal seems to use Doctrine\DBAL\ForwardCompatibility\Result now]
	 * @return array(Monolith\Casterlith\Entity\EntityInterface)
	 */
	public function buildAll(/*PDOStatement*/ $statement)
	{
		$this->build($statement);

		$entities = $this->selectionList[$this->rootAlias]->loaded;

		return $entities;
	}

	/**
	 * This method does no optimization. Optimization is up to the caller
	 * 
	 * @param  Doctrine\DBAL\Driver\PDOStatement  $statement [dbal seems to use Doctrine\DBAL\ForwardCompatibility\Result now]
	 * @return array()
	 */
	public function buildFirstAsRaw(/*PDOStatement*/ $statement)
	{
		if ($this->usePDOStatement) {
			$row = $statement->fetch(\PDO::FETCH_ASSOC);
		}
		else {
			$row = $statement->fetchAssociative();
		}

		return $row;
	}

	/**
	 * @param  Doctrine\DBAL\Driver\PDOStatement  $statement [dbal seems to use Doctrine\DBAL\ForwardCompatibility\Result now]
	 * @return array()
	 */
	public function buildAllAsRaw(/*PDOStatement*/ $statement)
	{
		if ($this->usePDOStatement) {
			$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
		}
		else {
			$rows = $statement->fetchAllAssociative();
		}

		return $rows;
	}

	public function getRootAlias()
	{
		return $this->rootAlias;
	}

	/**
	 * @param  PDOStatement $statement [dbal seems to use Doctrine\DBAL\ForwardCompatibility\Result now]
	 * @return null
	 */
	protected function build(/*PDOStatement*/ $statement)
	{
		$this->completeSelectionWithPrimaryKeys();

		while ($row = $statement->fetch()) {
			//	Load all entities present on the current row
			$this->loadEntitiesOfARow($row);

			//	Parse any relations (in selection only) to link entities together
			foreach ($this->selectionList as $fromAlias => $selection) {
				foreach ($selection->relations as $relationKey => $toAlias) {

					$joint       = $this->jointList[$fromAlias][$relationKey];
					$fromEntity  = $this->getLoadedEntity($fromAlias, $row);
					$toEntity    = $this->getLoadedEntity($toAlias, $row);

					$this->linkEntities($fromEntity, $toEntity, $joint);
				}
			}
		}
	}

	/**
	 * @param  string                         $fromEntity
	 * @param  string                         $toEntity
	 * @param  Merry\Casterlith\Schema\Joint  $joint
	 * @return null
	 */
	protected function linkEntities($fromEntity, $toEntity, $joint)
	{
		if ($joint->relation instanceof ManyToOne) {
			$this->linkEntitiesWithAManyToOneRelation($fromEntity, $toEntity, $joint);
		}
		elseif ($joint->relation instanceof OneToOne) {
			$this->linkEntitiesWithAOneToOneRelation($fromEntity, $toEntity, $joint);
		}
		elseif ($joint->relation instanceof OneToMany) {
			$this->linkEntitiesWithAOneToManyRelation($fromEntity, $toEntity, $joint);
		}
		else {
			throw new \Exception("this relation type isn't handled properly");
		}
	}

	/**
	 * @param  string                         $fromEntity
	 * @param  string                         $toEntity
	 * @param  Merry\Casterlith\Schema\Joint  $joint
	 * @return null
	 */
	protected function linkEntitiesWithAManyToOneRelation($fromEntity, $toEntity, $joint)
	{
		if (!is_null($fromEntity)) {
			$property = $joint->property;
			$fromEntity->$property = $toEntity;
		}
	}

	/**
	 * @param  string                         $fromEntity
	 * @param  string                         $toEntity
	 * @param  Merry\Casterlith\Schema\Joint  $joint
	 * @return null
	 */
	protected function linkEntitiesWithAOneToOneRelation($fromEntity, $toEntity, $joint)
	{
		$this->linkEntitiesWithAManyToOneRelation($fromEntity, $toEntity, $joint);
	}

	/**
	 * @param  string                                           $fromEntity
	 * @param  string                                           $toEntity
	 * @param  Monolith\Casterlith\Schema\Joint  $joint
	 * @return null
	 */
	protected function linkEntitiesWithAOneToManyRelation($fromEntity, $toEntity, $joint)
	{
		if (!is_null($fromEntity)) {
			$property = $joint->property;
			if (!is_array($fromEntity->$property)) {
				$fromEntity->$property = array();
			}
			if (!is_null($toEntity)) {
				$primaryKey = $this->mapperList[$joint->toAlias]->getPrimaryKey();
				$fromEntity->{$property}[$toEntity->$primaryKey] = $toEntity;
			}
		}
	}

	/**
	 * @param  string                                                    $alias
	 * @param  Monolith\Casterlith\Mapper\MapperInterface $mapper
	 * @return null
	 */
	protected function addMapper($alias, MapperInterface $mapper)
	{
		if (!isset($this->mapperList[$alias])) {
			$this->mapperList[$alias] = $mapper;
		}
	}

	/**
	 * @param  string $alias
	 * @return Monolith\Casterlith\Mapper\MapperInterface
	 */
	protected function getMapper($alias)
	{
		if (!isset($this->mapperList[$alias])) {
			throw new \Exception("This method cannot be called before the alias '".$alias."' as been declared with 'from' or 'join' methods of the SchemaBuilder class");
		}

		return $this->mapperList[$alias];
	}

	/**
	 * @param  string $alias
	 * @return string
	 */
	protected function getReplacer($alias)
	{
		if (!isset($this->selectionList[$alias])) {
			throw new \Exception("This method cannot be called before the alias '".$alias."' as been declared with 'select' method of the SchemaBuilder class");
		}

		return $this->selectionList[$alias]->replacer;
	}

	/**
	 * @param  string  $fromAlias
	 * @param  string  $relName
	 * @return null
	 */
	protected function addJoint($fromAlias, $toAlias, $relationKey, $relation)
	{
		if (!isset($this->jointList[$fromAlias])) {
			$this->jointList[$fromAlias] = array();
		}
		if (!isset($this->jointList[$fromAlias][$relationKey])) {
			$this->jointList[$fromAlias][$relationKey] = new Joint($fromAlias, $toAlias, $relationKey, $relation);
		}
	}

	/**
	 * If both left and right aliases of a joint are in selection
	 * this association has also to be the selection
	 * 
	 * @param string $fromAlias
	 * @param string $toAlias
	 * @param string $relationKey
	 */
	protected function addJointOnSelection($fromAlias, $toAlias, $relationKey)
	{
		if (isset($this->selectionList[$fromAlias]) && isset($this->selectionList[$toAlias])) {
			$this->selectionList[$fromAlias]->relations[$relationKey] = $toAlias;
		}
	}

	/**
	 * To know which entity is new, we check with primary key
	 * We need the replaced keys to do so
	 * 
	 * @return null
	 */
	protected function completeSelectionWithPrimaryKeys()
	{
		foreach ($this->selectionList as $alias => $selection) {

			if (is_null($selection->primaryKey)) {

				$replacer  = $selection->replacer;

				//	No need to call getMapper method because we are in the selection
				$mapper = $this->mapperList[$alias];
				$fields = $mapper->getFields();

				$selection->primaryKey          = $mapper->getPrimaryKey();
				$selection->realPrimaryKey      = $fields[$selection->primaryKey]['name'];
				$selection->replacedPrimaryKey  = $replacer.$selection->realPrimaryKey;
			}
		}

		return null;
	}

	/**
	 * @param  array  &$row
	 * @return null
	 */
	protected function loadEntitiesOfARow(&$row)
	{
		foreach ($this->selectionList as $alias => $selection) {

			$primaryValue = $row[$selection->replacedPrimaryKey];
			if (!is_null($primaryValue)) {
				if (!$this->doesEntityIsLoaded($alias, $primaryValue)) {
					$this->loadEntity($alias, $primaryValue, $row);
				}
			}
		}
	}

	/**
	 * @param  string  $alias
	 * @param  string  $primaryValue
	 * @return boolean
	 */
	protected function doesEntityIsLoaded($alias, $primaryValue)
	{
		$isLoaded = (isset($this->selectionList[$alias]->loaded[$primaryValue]));

		return $isLoaded;
	}

	/**
	 * @param  string  $alias
	 * @param  array   &$row
	 * @return Monolith\Casterlith\Entity\EntityInterface
	 */
	protected function getLoadedEntity($alias, &$row)
	{
		$entity        = null;
		$selection     = $this->selectionList[$alias];
		$primaryValue  = $row[$selection->replacedPrimaryKey];

		if (!is_null($primaryValue)) {
			$entity = (isset($selection->loaded[$primaryValue])) ? 
				$selection->loaded[$primaryValue] : 
				null;
		}

		return $entity;
	}

	/**
	 * @param  string  $alias
	 * @param  string  primaryValue
	 * @param  array   &$row
	 * @return null
	 */
	protected function loadEntity($alias, $primaryValue, &$row)
	{
		$mapper           = $this->getMapper($alias);
		$entityClassName  = $mapper->getEntity();
		$entity           = new $entityClassName();
		$replacer         = $this->selectionList[$alias]->replacer;

		$fields = $mapper->getFields();
		foreach ($fields as $key => $field) {
			$value = $row[$replacer.$field['name']];
			$value = $this->connection->convertToPHPValue($value, $field['type']); // cast
			$entity->$key = $value;
		}

		$this->selectionList[$alias]->loaded[$primaryValue] = $entity;
	}

}
