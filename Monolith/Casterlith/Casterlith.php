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
	 * Constructor
	 * 
	 * @param  array  $params                                     [The database connection parameters]
	 * @param  Monolith\Casterlith\Configuration  $configuration  [The configuration to use]
	 * @param  Doctrine\Common\EventManager  $eventManager        [The event manager to use]
	 * 
	 * @return Monolith\Casterlith
	 * @throws Doctrine\DBAL\DBALException
	 */
	public function __construct(array $params, Configuration $configuration, EventManager $eventManager = null)
	{
		$configuration->initVersion(); // Retro compatibility according to dbal version

		$this->connection = DriverManager::getConnection($params, $configuration, $eventManager);

		$this->configuration = $configuration;

		return $this;
	}

	/**
	 * Instantiate a specific composer
	 * 
	 * With a provided Composer's class name this method returns a new instance of this composer.
	 * A composer is used to get a Casterlith Query Builder.
	 * ```php
	 * $trackComposer  = $orm->getComposer('Acme\Composers\Track');
	 * ```
	 * 
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
	 * Create a DBAL's query builder
	 * 
	 * @return Doctrine\DBAL\Query\QueryBuilder
	 */
	public function getDBALQueryBuilder()
	{
		$queryBuilder = $this->connection->createQueryBuilder();

		return $queryBuilder;
	}

	/**
	 * Get current DBAL's connection
	 * 
	 * @return Doctrine\DBAL\Connection
	 */
	public function getDBALConnection()
	{
		return $this->connection;
	}

	/**
	 * Get current PDO's connection
	 * 
	 * @return PDO
	 */
	public function getPDOConnection()
	{
		return $this->getDBALConnection()->getWrappedConnection();
	}
}
