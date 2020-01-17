<?php

/**
 * This file is part of Casterlith.
 *
 * @link https://github.com/merry-goblin/casterlith
 */

namespace Monolith\Casterlith;

use Monolith\Casterlith\Composer\ComposerInterface;

use Doctrine\DBAL\DriverManager;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DBALException;

/**
 * Main class of Casterlith ORM DataMapper
 * 
 * Provides composers to build queries
 * The database connection is made with this class
 */
class Casterlith
{
	const NOT_LOADED = 0;

	/** @var Doctrine\DBAL\DriverManager */
	protected $connection = null;

	/** @var Monolith\Casterlith\Configuration */
	protected $configuration = null;

	/**
	 * @param  array  $params                                     [The database connection parameters]
	 * @param  Monolith\Casterlith\Configuration  $configuration  [The configuration to use]
	 * @param  Doctrine\Common\EventManager  $eventManager        [The event manager to use]
	 * 
	 * @return Monolith\Casterlith
	 * @throws Doctrine\DBAL\DBALException
	 */
	public function __construct(array $params, Configuration $configuration, EventManager $eventManager = null)
	{
		$this->connection = DriverManager::getConnection($params, $configuration, $eventManager);

		$this->configuration = $configuration;

		return $this;
	}

	/**
	 * @param  string $className
	 * @return Monolith\Casterlith\Composer\ComposerInterface
	 * @throws Exception
	 */
	public function getComposer($className)
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$composer = new $className($queryBuilder, $this->configuration);
		if (!($composer instanceof ComposerInterface)) {
			throw new \Exception("className parameter must be a Composer");
		}

		return $composer;
	}

	/**
	 * @return Doctrine\DBAL\Query\QueryBuilder
	 */
	public function getQueryBuilder()
	{
		$queryBuilder = $this->connection->createQueryBuilder();

		return $queryBuilder;
	}

	/**
	 * @return Doctrine\DBAL\Connection
	 */
	public function getDBALConnection()
	{
		return $this->connection;
	}

	/**
	 * @return PDO
	 */
	public function getPDOConnection()
	{
		return $this->getDBALConnection()->getWrappedConnection();
	}
}
