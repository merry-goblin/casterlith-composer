<?php

/**
 * This file is part of Casterlith.
 *
 * @link https://github.com/merry-goblin/casterlith
 */

namespace Monolith\Casterlith\Composer;

use Doctrine\DBAL\Query\QueryBuilder;
use Monolith\Casterlith\Schema\Builder as SchemaBuilder;
use Monolith\Casterlith\Mapper\MapperInterface;
use Monolith\Casterlith\Configuration;

/**
 * Abstract class for a Composer
 * 
 * Any Composer must extend this class.
 * A composer represent the access to a table with the help of a Mapper.
 * It provides methods to build a query.
 * A query can be based on "select" to get entities or based on "selectAsRaw" to get a simple stdClass without mapping (ex:for doing a count).
 * 
 * Example of a query:
 * ```php
 * $trackComposer  = $orm->getComposer('Acme\Composers\Track');
 * $qb             = $trackComposer->getDBALQueryBuilder();
 * 
 * $tracks = $trackComposer
 *   ->select("tra", "alb", "art")
 *   ->join("tra", "alb", "album")
 *   ->join("alb", "art", "artist")
 *   ->where($qb->expr()->andX(
 *     $qb->expr()->like('tra.Name', ':trackName'),
 *     $qb->expr()->eq('art.Name', ':artistName')
 *   ))
 *   ->setParameter('trackName', "%Princess%")
 *   ->setParameter('artistName', "Accept")
 *   ->all();
 * ```
 */
abstract class AbstractComposer
{
	/** @var Doctrine\DBAL\Query\QueryBuilder */
	protected $queryBuilder = null;

	/** @var string */
	protected static $mapperName = null;

	/** @var Monolith\Casterlith\Mapper\MapperInterface */
	protected $mapper = null;

	/** @var Monolith\Casterlith\Schema\Builder */
	protected $schemaBuilder = null;

	/** @var string[] */
	protected $yetToSelectList = null;

	/** @var string[] */
	protected $yetToSelectAsRawList = null;

	/** @var boolean */
	protected $isRaw = false;

	/** @var string */
	protected $selectionReplacer = null;

	/** @var boolean */
	protected $firstAutoSelection = null;

	/** @var boolean */
	protected $exceptionMultipleResultOnFirst = null;

	/**
	 * Constructor
	 * 
     * Initializes a new instance of the Composer which extends this abstract class.
     * Each Composer is related to its own Mapper. AbstractComposer::$mapperName static property must be filled.
     * 
	 * @param Doctrine\DBAL\Query\QueryBuilder   $queryBuilder
	 * @param Monolith\Casterlith\Configuration  $configuration
	 */
	public function __construct(QueryBuilder $queryBuilder, Configuration $configuration)
	{
		$this->queryBuilder = $queryBuilder;

		$this->selectionReplacer               = $configuration->getSelectionReplacer();
		$this->firstAutoSelection              = $configuration->getFirstAutoSelection();
		$this->exceptionMultipleResultOnFirst  = $configuration->getExceptionMultipleResultOnFirst();

		//	Neither an empty string nor null
		if (empty($this::$mapperName)) {
			throw new \Exception("mapperName property has to be initialized in current Composer's class");
		}

		$this->mapper = new $this::$mapperName();
		if (!($this->mapper instanceof MapperInterface)) {
			throw new \Exception("mapperName's class must implement MapperInterface");
		}

		$this->yetToSelectList = array();
	}

	/**
	 * 
	 * 
	 * One or more aliases to select.
	 * First one must be the one related to the Composer.
	 * Any alias added must be present in futur joins before calling first, limit or all except for the first alias which match the current Composer's Mapper.
	 * Calling this method reset entirely the query.
	 * 
	 * @param  string  $rootEntityAlias
	 * @param  string  $entityAlias2 [optional]
	 * @param  string  $entityAlias3 [optional]
	 * ...
	 * @param  string  $entityAliasN [optional]
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function select()
	{
		$this->reset();
		$this->isRaw = false;

		//	One or more aliases
		$args = func_get_args();
		if (count($args) == 0) {
			throw new \Exception("At least one alias is needed");
		}

		//	Alias of the current composer's entity
		$rootEntityAlias = $args[0];

		//	Neither empty nor null
		if (empty($rootEntityAlias)) {
			throw new \Exception("Alias can't be either empty or null.");
		}

		//	Alias has to be a string
		if (!is_string($rootEntityAlias)) {
			throw new \Exception("String expected, ".gettype($rootEntityAlias)." given instead.");
		}

		//	Schema builder
		$this->schemaBuilder->select($rootEntityAlias);
		$this->schemaBuilder->from($rootEntityAlias, $this->mapper);

		//	Query builder
		//		Reset selection
		$this->queryBuilder
			->resetQueryParts();
		//		Select of root entity
		$this->queryBuilder
			//->select($this->mapper->selectAll($rootEntityAlias, $replacer));
			->select($this->schemaBuilder->getAUniqueSelection($rootEntityAlias));
		//		From
		$this->queryBuilder
			->from($this->mapper->getTable(), $rootEntityAlias);

		//	Any entity to select other than the main one
		if (count($args) > 1) {
			array_shift($args);
			$this->addSelect($args);
		}

		return $this;
	}

	/**
	 * One or more aliases to select
	 * Any alias added must be present in futur joins before calling first, limit or all.
	 * select method must be called before calling addSelect method.
	 *
	 * @param  string  $entityAlias1
	 * @param  string  $entityAlias2 [optional]
	 * ...
	 * @param  string  $entityAliasN [optional]
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function addSelect()
	{
		//	One or more aliases
		$args = func_get_args();
		if (count($args) == 0) {
			throw new \Exception("At least one alias is needed");
		}

		if (is_array($args[0])) {
			$args = $args[0];
		}

		//	Aliases of future joints
		for ($i=0, $len=count($args); $i<$len; $i++) {

			//	Alias of joint entities to select
			$alias = $args[$i];

			//	Neither empty nor null
			if (empty($alias)) {
				throw new \Exception("Alias can't be either empty or null.");
			}

			//	Alias has to be a string
			if (!is_string($alias)) {
				throw new \Exception("String expected, ".gettype($alias)." given instead.");
			}

			//	Can't add an existing entity alias
			$rootAlias = $this->schemaBuilder->getRootAlias();
			if (in_array($alias, $this->yetToSelectList) || $alias == $rootAlias) {
				throw new \Exception("Entity alias ".$alias." already added.");
			}

			$this->schemaBuilder->select($alias);
			$this->yetToSelectList[] = $alias;
		}

		return $this;
	}

	/**
	 * For custom selection. Will not cast tables in entities.
	 * All values in result will be strings.
	 * Calling this method resets entirely the query.
	 * 
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function selectAsRaw($rootEntityAlias)
	{
		$this->reset();
		$this->isRaw = true;

		//	Schema builder
		$this->schemaBuilder->select($rootEntityAlias);
		$this->schemaBuilder->from($rootEntityAlias, $this->mapper);

		//	Query builder
		//		Reset selection
		$this->queryBuilder
			->resetQueryParts();
		//		From
		$this->queryBuilder
			->from($this->mapper->getTable(), $rootEntityAlias);

		//	Any entity to select other than the main one
		$args = func_get_args();
		if (count($args) > 1) {
			array_shift($args);
			$this->addSelectAsRaw($args);
		}

		return $this;
	}

	/**
	 * One or more custom selection.
	 * All values in result will be strings.
	 * selectAsRaw method must be called before calling addSelectAsRaw method.
	 *
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function addSelectAsRaw()
	{
		//	One or more selections
		$args = func_get_args();
		if (count($args) == 0) {
			throw new \Exception("At least one selection is needed");
		}

		if (is_array($args[0])) {
			$args = $args[0];
		}

		//	Aliases of future joints
		for ($i=0, $len=count($args); $i<$len; $i++) {

			//	Alias of joint entities to select
			$selection = $args[$i];
			//$this->schemaBuilder->select($alias);
			$this->yetToSelectAsRawList[] = $selection;
		}

		return $this;
	}

	/**
	 * Alias of innerJoin
	 * 
	 * @param  string $fromAlias
	 * @param  string $toAlias
	 * @param  string $relName
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function join($fromAlias, $toAlias, $relName)
	{
		return $this->innerJoin($fromAlias, $toAlias, $relName);
	}

	/**
	 * Create an inner join between two tables.
	 * The join is a relation with a name ($relName) and a condition which is made in a Mapper.
	 * 
	 * @param  string $fromAlias
	 * @param  string $toAlias
	 * @param  string $relName
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function innerJoin($fromAlias, $toAlias, $relName)
	{
		if (empty($fromAlias)) {
			throw new \Exception("innerJoin : From entity alias can't either be empty or null");
		}
		if (empty($toAlias)) {
			throw new \Exception("innerJoin : To entity alias can't either be empty or null");
		}

		if ($fromAlias == $toAlias) {
			throw new \Exception("innerJoin : From and to entity aliases must have different names");
		}

		list($table, $condition) = $this->schemaBuilder->join($fromAlias, $toAlias, $relName);

		$this->queryBuilder
			->innerJoin($fromAlias, $table, $toAlias, $condition);

		return $this;
	}

	/**
	 * Create an left join between two tables.
	 * The join is a relation with a name ($relName) and a condition which is made in a Mapper.
	 * 
	 * @param  string $fromAlias
	 * @param  string $toAlias
	 * @param  string $relName
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function leftJoin($fromAlias, $toAlias, $relName)
	{
		if (empty($fromAlias)) {
			throw new \Exception("leftJoin : From entity alias can't either be empty or null");
		}
		if (empty($toAlias)) {
			throw new \Exception("leftJoin : To entity alias can't either be empty or null");
		}

		if ($fromAlias == $toAlias) {
			throw new \Exception("leftJoin : From and to entity aliases must have different names");
		}

		list($table, $condition) = $this->schemaBuilder->join($fromAlias, $toAlias, $relName);

		$this->queryBuilder
			->leftJoin($fromAlias, $table, $toAlias, $condition);

		return $this;
	}

	/**
	 * Condition(s) of the query. 
	 * It is advise to use the expression builder of DBal.
	 * Calling this method resets some parts of the query (where,groupBy,having,orderBy,values)
	 * 
	 * @param  string $condition
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function where($condition)
	{
		$this->queryBuilder
			->resetQueryParts(array(
				'where',
				'groupBy',
				'having',
				'orderBy',
				'values',
			));

		$condition = $this->schemaBuilder->getReplacedFieldsOfAnyEntity($condition);

		$this->queryBuilder
			->where($condition);

		return $this;
	}

	/**
	 * Add other conditions to the query attached with an intersection. 
	 * It is advise to use the expression builder of DBal.
	 * 
	 * @param  string $condition
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function andWhere($condition)
	{
		$condition = $this->schemaBuilder->getReplacedFieldsOfAnyEntity($condition);

		$this->queryBuilder
			->andWhere($condition);

		return $this;
	}

	/**
	 * Add other conditions to the query attached with an union. 
	 * It is advise to use the expression builder of DBal.
	 * 
	 * @param  string $condition
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function orWhere($condition)
	{
		$condition = $this->schemaBuilder->getReplacedFieldsOfAnyEntity($condition);

		$this->queryBuilder
			->orWhere($condition);

		return $this;
	}

	/**
	 * Be cautious when using groupBy since some rows won't appear especially in your joins
	 * This is more suited for 'selectAsRaw' than 'select' method
	 * Calling this method resets some parts of the query (groupBy,having)
	 * 
	 * @param  string  $groupBy
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function groupBy($groupBy)
	{
		$args = func_get_args();
		if (count($args) == 0) {
			throw new \Exception("At least one groupBy is needed");
		}

		$this->queryBuilder
		->resetQueryParts(array(
			'groupBy',
			'having',
		));

		$groupBy = $this->schemaBuilder->getReplacedFieldsOfAnyEntity($groupBy);

		$this->queryBuilder
			->groupBy($groupBy);

		return $this;
	}

	/**
	 * @param  string  $groupBy
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function addGroupBy($groupBy)
	{
		$args = func_get_args();
		if (count($args) == 0) {
			throw new \Exception("At least one groupBy is needed");
		}

		$groupBy = $this->schemaBuilder->getReplacedFieldsOfAnyEntity($groupBy);

		$this->queryBuilder
			->addGroupBy($groupBy);

		return $this;
	}

	/**
	 * Calling this method resets some parts of the query (having)
	 *
	 * @param  mixed  $having
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function having($having)
	{
		$args = func_get_args();
		if (count($args) == 0) {
			throw new \Exception("At least one having is needed");
		}

		$this->queryBuilder
		->resetQueryParts(array(
			'having',
		));

		$having = $this->schemaBuilder->getReplacedFieldsOfAnyEntity($having);

		$this->queryBuilder
			->having($having);

		return $this;
	}

	/**
	 * @param  mixed  $having
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function andHaving($having)
	{
		$args = func_get_args();
		if (count($args) == 0) {
			throw new \Exception("At least one having is needed");
		}

		$having = $this->schemaBuilder->getReplacedFieldsOfAnyEntity($having);

		$this->queryBuilder
			->andHaving($having);

		return $this;
	}

	/**
	 * @param  mixed  $having
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function orHaving($having)
	{
		$args = func_get_args();
		if (count($args) == 0) {
			throw new \Exception("At least one having is needed");
		}

		$having = $this->schemaBuilder->getReplacedFieldsOfAnyEntity($having);

		$this->queryBuilder
			->orHaving($having);

		return $this;
	}

	/**
	 * Specifies an ordering for the query.
     * Replaces any previously specified orderings, if any.
	 * 
	 * @param  string  $sort
	 * @param  string  $order
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function order($sort, $order = null)
	{
		$sort = $this->schemaBuilder->getReplacedFieldsOfAnyEntity($sort);

		$this->queryBuilder->orderBy($sort, $order);

		return $this;
	}

	/**
	 * Adds an ordering to the query.
	 *
	 * @param  string  $sort
	 * @param  string  $order
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function addOrder($sort, $order = null)
	{
		$sort = $this->schemaBuilder->getReplacedFieldsOfAnyEntity($sort);

		$this->queryBuilder->addOrderBy($sort, $order);

		return $this;
	}

	/**
	 * 
	 * 
	 * @param  string|integer $key
	 * @param  mixed          $value
	 * @param  string         $type  [optional]
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 */
	public function setParameter($key, $value, $type = null)
	{
		$this->queryBuilder->setParameter($key, $value, $type);

		return $this;
	}

	/**
	 * This method must be called instead of the all method. 
	 * It will limit the elements of the current composer's entity
	 * instead of the number of rows returned by database.
	 * To do so, a sql requet is sent based on the current sql request 
	 * but with only the primary key on the current composer's entity 
	 * and with distinct function on it.
	 * 
	 * @return array(Monolith\Casterlith\Entity\EntityInterface)
	 * @throws Exception
	 */
	public function limit($first, $max = null)
	{
		if ($first < 0) {
			throw new \Exception("Offset parameter can't be negative"); 
		}
		if ($max < 0) {
			throw new \Exception("Limit parameter can't be negative");
		}

		$alias       = $this->schemaBuilder->getRootAlias();
		$mapperClass = get_class($this->mapper);
		$primaryKey  = $mapperClass::getPrimaryKey();

		//	Clone current dbal's request
		$selection         = "`".$alias."`.`".$primaryKey."`";
		$replacedSelection = $this->schemaBuilder->getReplacedFieldOfEntity($alias, $selection);
		$limitQueryBuilder = clone($this->queryBuilder);
		$limitQueryBuilder
			->select("distinct(".$replacedSelection.")")
			->setFirstResult($first)
			->setMaxResults($max);

		//	Get id list in the the range
		$idList = "";
		$statement = $limitQueryBuilder->execute();
		while ($id = $statement->fetchColumn()) {
			if (!empty($idList)) {
				$idList .= ",";
			}
			$idList .= $id;
		}

		//	Build a condition to limit the full dbal request
		if (!empty($idList)) {
			$condition  = $replacedSelection." IN (".$idList.")";
			$this->queryBuilder->andWhere($condition);
		}

		return $this->all();
	}

	/**
	 * Initialize statement and return the first entity
	 *
	 * @return Monolith\Casterlith\Entity\EntityInterface
	 */
	public function first()
	{
		if ($this->isRaw) {
			$result = $this->firstRawSelections();
		}
		else {
			if ($this->firstAutoSelection) {
				$resultList  = $this->limit(0, 1);
				$result      = (count($resultList) >= 1) ? reset($resultList) : null;
			}
			else {
				$result  = $this->firstEntities();
			}
		}

		return $result;
	}

	/**
	 * Initialize statement and return an array of entities
	 * 
	 * @return array(Monolith\Casterlith\Entity\EntityInterface)
	 */
	public function all()
	{
		if ($this->isRaw) {
			$result = $this->allRawSelections();
		}
		else {
			$result = $this->allEntities();
		}

		return $result;
	}

	/**
	 * Initialize statement and return the first entity
	 * This method does no optimization. Optimization is up to the caller
	 * 
	 * @return Monolith\Casterlith\Entity\EntityInterface
	 */
	private function firstEntities()
	{
		$this->finishSelection();

		$statement  = $this->queryBuilder->execute();

		try {
			$entity = $this->schemaBuilder->buildFirst($statement, $this->exceptionMultipleResultOnFirst);
		}
		catch (\Exception $e) {
			throw new \Exception("More than one result on request : ".$this->getSQL());
		}

		return $entity;
	}

	/**
	 * Initialize statement and return the first entity
	 * This method does no optimization. Optimization is up to the caller
	 * 
	 * @return Monolith\Casterlith\Entity\EntityInterface
	 */
	private function firstRawSelections()
	{
		$this->finishRawSelection();

		//$sql = $this->queryBuilder->getSQL();
		$statement  = $this->queryBuilder->execute();

		$row = $this->schemaBuilder->buildFirstAsRaw($statement);

		return $row;
	}

	private function allEntities()
	{
		$this->finishSelection();

		$statement  = $this->queryBuilder->execute();

		$entities = $this->schemaBuilder->buildAll($statement);

		return $entities;
	}

	private function allRawSelections()
	{
		$this->finishRawSelection();

		$statement  = $this->queryBuilder->execute();

		$rows = $this->schemaBuilder->buildAllAsRaw($statement);

		return $rows;
	}

	/**
	 * Reset occure when select method is called
	 * @return [type] [description]
	 */
	protected function reset()
	{
		$this->schemaBuilder         = new SchemaBuilder($this->queryBuilder, $this->selectionReplacer);
		$this->yetToSelectList       = array();
		$this->yetToSelectAsRawList  = array();
	}

	/**
	 * Select of other entities than the one related to the current composer
	 * 
	 * @return null
	 */
	protected function finishSelection()
	{
		foreach ($this->yetToSelectList as $key => $alias) {
			$selection = $this->schemaBuilder->getAUniqueSelection($alias);
			$this->queryBuilder->addSelect($selection);
			unset($this->yetToSelectList[$key]);
		}
	}

	/**
	 * Select of other entities than the one related to the current composer
	 * 
	 * @return null
	 */
	protected function finishRawSelection()
	{
		foreach ($this->yetToSelectAsRawList as $key => $rawSelection) {
			$selection = $this->schemaBuilder->getAUniqueSelectionFromRaw($rawSelection);
			
			$this->queryBuilder->addSelect($selection);
			unset($this->yetToSelectAsRawList[$key]);
		}
	}

	/**
	 * @return Monolith\Casterlith\Mapper\AbstractMapper
	 */
	public function getMapper()
	{
		return $this->mapper;
	}

	/**
	 * @return Doctrine\DBAL\Query\QueryBuilder
	 */
	public function getDBALQueryBuilder()
	{
		return $this->queryBuilder;
	}

	/**
	 * @return Doctrine\DBAL\Connection
	 */
	public function getDBALConnection()
	{
		return $this->queryBuilder->getConnection();
	}

	/**
	 * @return PDO
	 */
	public function getPDOConnection()
	{
		return $this->getDBALConnection()->getWrappedConnection();
	}

	/**
	 * @return string
	 */
	public function getSQL()
	{
		return $this->queryBuilder->getSQL();
	}

	/**
	 * @return \Doctrine\DBAL\Query\Expression\ExpressionBuilder
	 */
	public function expr()
	{
		return $this->getDBALConnection()->getExpressionBuilder();
	}
}
