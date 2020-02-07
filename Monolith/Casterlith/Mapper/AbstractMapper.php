<?php

/**
 * This file is part of Casterlith.
 *
 * @link https://github.com/merry-goblin/casterlith
 */

namespace Monolith\Casterlith\Mapper;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Abstract class for a Mapper
 * 
 * Any Mapper must extend this class.
 * It's not possible to change fields name.
 * There is no constraint for the number of joins between tables. So you can create multiple times a same constraint with a different name and some differences.
 * For example, you can make represant a relation between two tables by two joins. One which is recursive and the other wich is not :
 * ```php
 *  public static function getRelations()
 *  {
 *  	if (is_null(self::$relations)) {
 *  		self::$relations = array(
 *  			'albums'            => new OneToMany(new AlbumMapper(), 'artist', 'album', '`artist`.ArtistId = `album`.ArtistId', 'artist'),
 *  			'albumsNoRecursion' => new OneToMany(new AlbumMapper(), 'artist', 'album', '`artist`.ArtistId = `album`.ArtistId'),
 *  		);
 *  	}
 *  
 *  	return self::$relations;
 *  }
 * ```
 * 
 * or it could be the condition of this relation that changes :
 * ```php
 *  public static function getRelations()
 *  {
 *  	if (is_null(self::$relations)) {
 *  		self::$relations = array(
 *  			'albums'          => new OneToMany(new AlbumMapper(), 'artist', 'album', '`artist`.ArtistId = `album`.ArtistId', 'artist'),
 *  			'albumsExceptOne' => new OneToMany(new AlbumMapper(), 'artist', 'album', '`artist`.ArtistId = `album`.ArtistId AND `album`.AlbumId <> 10'),
 *  		);
 *  	}
 *  
 *  	return self::$relations;
 *  }
 * ```
 */
abstract class AbstractMapper
{
	static protected $init = false;

	/**
	 * Getter for table property
	 * 
	 * @return string
	 */
	public function getTable()
	{
		return $this::$table;
	}

	/**
	 * Getter for entity property
	 * 
	 * @return string
	 */
	public function getEntity()
	{
		return $this::$entity;
	}

	/**
	 * @return array
	 */
	final public static function getFields()
	{
		static::initFields();
		return static::$fields;
	}

	/**
	 * @return null
	 */
	static private function initFields()
	{
		static::completeFieldName();
	}

	/**
	 * @return null
	 */
	static private function completeFieldName()
	{
		foreach (static::$fields as $key => $field) {
			if (!isset($field['name'])) {
				$field['name'] = $key;
				static::$fields[$key] = $field;
			}
		}
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
