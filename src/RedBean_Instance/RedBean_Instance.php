<?php
/**
 * RedBean Facade converted to an Instance
 *
 * Version Information
 * RedBean Version @version 3.5
 *
 * @file    RedBean/Facade.php
 * @desc    Convenience class for RedBeanPHP.
 * @author  Gabor de Mooij and the RedBeanPHP Community
 * @license BSD/GPLv2
 *
 * This class hides the object landscape of
 * RedBeanPHP behind a single letter class providing
 * almost all functionality with simple static calls.
 *
 * copyright (c) G.J.G.T. (Gabor) de Mooij and the RedBeanPHP Community
 * This source file is subject to the BSD/GPLv2 License that is bundled
 * with this source code in the file license.txt.
 */
class RedBean_Instance
{
	/**
	 * RedBeanPHP version constant.
	 */
	const C_REDBEANPHP_VERSION = '3.5';

	/**
	 * @var boolean
	 */
	private $strictType = TRUE;

	/**
	 * @var array
	 */
	public $toolboxes = array();

	/**
	 * @var RedBean_ToolBox
	 */
	public $toolbox;

	/**
	 * @var RedBean_OODB
	 */
	public $redbean;

	/**
	 * @var RedBean_QueryWriter
	 */
	public $writer;

	/**
	 * @var RedBean_Adapter_DBAdapter
	 */
	public $adapter;

	/**
	 * @var RedBean_AssociationManager
	 */
	public $associationManager;

	/**
	 * @var RedBean_AssociationManager_ExtAssociationManager
	 */
	public $extAssocManager;

	/**
	 * @var RedBean_TagManager
	 */
	public $tagManager;

	/**
	 * @var RedBean_DuplicationManager
	 */
	public $duplicationManager;

	/**
	 * @var RedBean_LabelMaker
	 */
	public $labelMaker;

	/**
	 * @var RedBean_Finder
	 */
	public $finder;

	/**
	 * @var string
	 */
	public $currentDB = '';

	/**
	 * @var RedBean_SQLHelper
	 */
	public $f;

	/**
	 * @var array
	 */
	public $plugins = array();

	/**
	 * Internal Query function, executes the desired query. Used by
	 * all facade query functions. This keeps things DRY.
	 *
	 * @throws RedBean_Exception_SQL
	 *
	 * @param string $method   desired query method (i.e. 'cell', 'col', 'exec' etc..)
	 * @param string $sql      the sql you want to execute
	 * @param array  $bindings array of values to be bound to query statement
	 *
	 * @return array
	 */
	private function query( $method, $sql, $bindings )
	{
		if ( !$this->redbean->isFrozen() ) {
			try {
				$rs = $this->adapter->$method( $sql, $bindings );
			} catch ( RedBean_Exception_SQL $exception ) {
				if ( $this->writer->sqlStateIn( $exception->getSQLState(),
					array(
						RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_COLUMN,
						RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE )
				)
				) {
					return ( $method === 'getCell' ) ? NULL : array();
				} else {
					throw $exception;
				}
			}

			return $rs;
		} else {
			return $this->adapter->$method( $sql, $bindings );
		}
	}

	/**
	 * Returns the RedBeanPHP version string.
	 * The RedBeanPHP version string always has the same format "X.Y"
	 * where X is the major version number and Y is the minor version number.
	 * Point releases are not mentioned in the version string.
	 *
	 * @return string
	 */
	public function getVersion()
	{
		return self::C_REDBEANPHP_VERSION;
	}

	/**
	 * Turns an array (post/request array) into a collection of beans.
	 * Handy for turning forms into bean structures that can be stored with a
	 * single call.
	 *
	 * Typical usage:
	 *
	 * $struct = $db->graph($_POST);
	 * $db->store($struct);
	 *
	 * Example of a valid array:
	 *
	 *    $form = array(
	 *        'type' => 'order',
	 *        'ownProduct' => array(
	 *            array('id' => 171, 'type' => 'product'),
	 *        ),
	 *        'ownCustomer' => array(
	 *            array('type' => 'customer', 'name' => 'Bill')
	 *        ),
	 *        'sharedCoupon' => array(
	 *            array('type' => 'coupon', 'name' => '123'),
	 *            array('type' => 'coupon', 'id' => 3)
	 *        )
	 *    );
	 *
	 * Each entry in the array will become a property of the bean.
	 * The array needs to have a type-field indicating the type of bean it is
	 * going to be. The array can have nested arrays. A nested array has to be
	 * named conform the bean-relation conventions, i.e. ownPage/sharedPage
	 * each entry in the nested array represents another bean.
	 *
	 * @param array   $array       array to be turned into a bean collection
	 * @param boolean $filterEmpty whether you want to exclude empty beans
	 *
	 * @return array
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function graph( $array, $filterEmpty = FALSE )
	{
		$c = new RedBean_Plugin_Cooker;
		$c->setToolbox( $this->toolbox );
		return $c->graph( $array, $filterEmpty);
	}

	/**
	 * Logs queries beginning with CREATE or ALTER to file (TimeLine).
	 * Attaches a listener to the adapter to monitor for schema altering queries.
	 *
	 * @param string $filename destination file
	 *
	 * @return void
	 */
	public function log($filename)
	{
		$tl = new RedBean_Plugin_TimeLine($filename);
		$this->adapter->addEventListener('sql_exec', $tl);
	}

	/**
	 * Kickstarts redbean for you. This method should be called before you start using
	 * RedBean. The Setup() method can be called without any arguments, in this case it will
	 * try to create a SQLite database in /tmp called red.db (this only works on UNIX-like systems).
	 *
	 * @param string  $dsn      Database connection string
	 * @param string  $username Username for database
	 * @param string  $password Password for database
	 * @param boolean $frozen   TRUE if you want to setup in frozen mode
	 *
	 * @return RedBean_ToolBox
	 */
	public function setup( $dsn = NULL, $username = NULL, $password = NULL, $frozen = FALSE )
	{
		if ( is_null( $dsn ) ) {
			$dsn = 'sqlite:/' . sys_get_temp_dir() . '/red.db';
		}

		$this->addDatabase( 'default', $dsn, $username, $password, $frozen );
		$this->selectDatabase( 'default' );

		return $this->toolbox;
	}

	/**
	 * Starts a transaction within a closure (or other valid callback).
	 * If an Exception is thrown inside, the operation is automatically rolled back.
	 * If no Exception happens, it commits automatically.
	 * It also supports (simulated) nested transactions (that is useful when
	 * you have many methods that needs transactions but are unaware of
	 * each other).
	 * ex:
	 *        $from = 1;
	 *        $to = 2;
	 *        $amount = 300;
	 *
	 *        $db->transaction(function() use($from, $to, $amount)
	 *        {
	 *            $accountFrom = $db->load('account', $from);
	 *            $accountTo = $db->load('account', $to);
	 *
	 *            $accountFrom->money -= $amount;
	 *            $accountTo->money += $amount;
	 *
	 *            $db->store($accountFrom);
	 *            $db->store($accountTo);
	 *      });
	 *
	 * @param callable $callback Closure (or other callable) with the transaction logic
	 *
	 * @throws RedBean_Exception_Security
	 *
	 * @return mixed
	 *
	 */
	public function transaction( $callback )
	{
		if ( !is_callable( $callback ) ) {
			throw new RedBean_Exception_Security( '$db->transaction needs a valid callback.' );
		}

		static $depth = 0;
		$result = null;
		try {
			if ( $depth == 0 ) {
				$this->begin();
			}
			$depth++;
			$result = call_user_func( $callback ); //maintain 5.2 compatibility
			$depth--;
			if ( $depth == 0 ) {
				$this->commit();
			}
		} catch ( Exception $exception ) {
			$depth--;
			if ( $depth == 0 ) {
				$this->rollback();
			}
			throw $exception;
		}
		return $result;
	}

	/**
	 * Adds a database to the facade, afterwards you can select the database using
	 * selectDatabase($key), where $key is the name you assigned to this database.
	 *
	 * Usage:
	 *
	 * $db->addDatabase( 'database-1', 'sqlite:/tmp/db1.txt' );
	 * $db->selectDatabase( 'database-1' ); //to select database again
	 *
	 * This method allows you to dynamically add (and select) new databases
	 * to the facade. Adding a database with the same key as an older database
	 * will cause this entry to be overwritten.
	 *
	 * @param string      $key    ID for the database
	 * @param string      $dsn    DSN for the database
	 * @param string      $user   User for connection
	 * @param NULL|string $pass   Password for connection
	 * @param bool        $frozen Whether this database is frozen or not
	 *
	 * @return void
	 */
	public function addDatabase( $key, $dsn, $user = NULL, $pass = NULL, $frozen = FALSE, $autoSetEncoding = TRUE )
	{
		$this->toolboxes[$key] = RedBean_Setup::kickstart( $dsn, $user, $pass, $frozen, $autoSetEncoding );
	}

	/**
	 * Selects a different database for the Facade to work with.
	 * If you use the $db->setup() you don't need this method. This method is meant
	 * for multiple database setups. This method selects the database identified by the
	 * database ID ($key). Use addDatabase() to add a new database, which in turn
	 * can be selected using selectDatabase(). If you use $db->setup(), the resulting
	 * database will be stored under key 'default', to switch (back) to this database
	 * use $db->selectDatabase( 'default' ). This method returns TRUE if the database has been
	 * switched and FALSE otherwise (for instance if you already using the specified database).
	 *
	 * @param  string $key Key of the database to select
	 *
	 * @return boolean
	 */
	public function selectDatabase( $key )
	{
		if ( $this->currentDB === $key ) {
			return FALSE;
		}

		$this->configureFacadeWithToolbox( $this->toolboxes[$key] );
		$this->currentDB = $key;

		return TRUE;
	}

	/**
	 * Toggles DEBUG mode.
	 * In Debug mode all SQL that happens under the hood will
	 * be printed to the screen or logged by provided logger.
	 * If no database connection has been configured using $db->setup() or
	 * $db->selectDatabase() this method will throw an exception.
	 *
	 * @param boolean        $tf
	 * @param RedBean_Logger $logger
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function debug( $tf = TRUE, $logger = NULL )
	{
		if ( !$logger ) {
			$logger = new RedBean_Logger_Default;
		}

		if ( !isset( $this->adapter ) ) {
			throw new RedBean_Exception_Security( 'Use $db->setup() first.' );
		}

		$this->adapter->getDatabase()->setDebugMode( $tf, $logger );
	}

	/**
	 * Inspects the database schema. If you pass the type of a bean this
	 * method will return the fields of its table in the database.
	 * The keys of this array will be the field names and the values will be
	 * the column types used to store their values.
	 * If no type is passed, this method returns a list of all tables in the database.
	 *
	 * @param string $type Type of bean (i.e. table) you want to inspect
	 *
	 * @return array
	 */
	public function inspect( $type = NULL )
	{
		return ($type === NULL) ? $this->writer->getTables() : $this->writer->getColumns( $type );
	}

	/**
	 * Stores a bean in the database. This method takes a
	 * RedBean_OODBBean Bean Object $bean and stores it
	 * in the database. If the database schema is not compatible
	 * with this bean and RedBean runs in fluid mode the schema
	 * will be altered to store the bean correctly.
	 * If the database schema is not compatible with this bean and
	 * RedBean runs in frozen mode it will throw an exception.
	 * This function returns the primary key ID of the inserted
	 * bean.
	 *
	 * The return value is an integer if possible. If it is not possible to
	 * represent the value as an integer a string will be returned.
	 *
	 * @param RedBean_OODBBean|RedBean_SimpleModel $bean bean to store
	 *
	 * @return integer|string
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function store( $bean )
	{
		return $this->redbean->store( $bean );
	}

	/**
	 * Toggles fluid or frozen mode. In fluid mode the database
	 * structure is adjusted to accomodate your objects. In frozen mode
	 * this is not the case.
	 *
	 * You can also pass an array containing a selection of frozen types.
	 * Let's call this chilly mode, it's just like fluid mode except that
	 * certain types (i.e. tables) aren't touched.
	 *
	 * @param boolean|array $trueFalse
	 */
	public function freeze( $tf = TRUE )
	{
		$this->redbean->freeze( $tf );
	}

	/**
	 * Loads multiple types of beans with the same ID.
	 * This might look like a strange method, however it can be useful
	 * for loading a one-to-one relation.
	 *
	 * Usage:
	 * list($author, $bio) = $db->load('author, bio', $id);
	 *
	 * @param string|array $types
	 * @param mixed        $id
	 *
	 * @return RedBean_OODBBean
	 */
	public function loadMulti( $types, $id )
	{
		if ( is_string( $types ) ) {
			$types = explode( ',', $types );
		}

		if ( !is_array( $types ) ) {
			return array();
		}

		foreach ( $types as $k => $typeItem ) {
			$types[$k] = $this->redbean->load( $typeItem, $id );
		}

		return $types;
	}

	/**
	 * Loads a bean from the object database.
	 * It searches for a RedBean_OODBBean Bean Object in the
	 * database. It does not matter how this bean has been stored.
	 * RedBean uses the primary key ID $id and the string $type
	 * to find the bean. The $type specifies what kind of bean you
	 * are looking for; this is the same type as used with the
	 * dispense() function. If RedBean finds the bean it will return
	 * the RedBean_OODB Bean object; if it cannot find the bean
	 * RedBean will return a new bean of type $type and with
	 * primary key ID 0. In the latter case it acts basically the
	 * same as dispense().
	 *
	 * Important note:
	 * If the bean cannot be found in the database a new bean of
	 * the specified type will be generated and returned.
	 *
	 * @param string  $type type of bean you want to load
	 * @param integer $id   ID of the bean you want to load
	 *
	 * @throws RedBean_Exception_SQL
	 *
	 * @return RedBean_OODBBean
	 */
	public function load( $type, $id )
	{
		return $this->redbean->load( $type, $id );
	}

	/**
	 * Removes a bean from the database.
	 * This function will remove the specified RedBean_OODBBean
	 * Bean Object from the database.
	 *
	 * @param RedBean_OODBBean|RedBean_SimpleModel $bean bean you want to remove from database
	 *
	 * @return void
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function trash( $bean )
	{
		$this->redbean->trash( $bean );
	}

	/**
	 * Dispenses a new RedBean OODB Bean for use with
	 * the rest of the methods.
	 *
	 * @param string  $type   type
	 * @param integer $number number of beans to dispense
	 *
	 * @return array|RedBean_OODBBean
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function dispense( $type, $num = 1 )
	{
		if ( !preg_match( '/^[a-z0-9]+$/', $type ) && $this->strictType ) {
			throw new RedBean_Exception_Security( 'Invalid type: ' . $type );
		}

		return $this->redbean->dispense( $type, $num );
	}

	/**
	 * Takes a comma separated list of bean types
	 * and dispenses these beans. For each type in the list
	 * you can specify the number of beans to be dispensed.
	 *
	 * Usage:
	 *
	 * list($book, $page, $text) = $db->dispenseAll('book,page,text');
	 *
	 * This will dispense a book, a page and a text. This way you can
	 * quickly dispense beans of various types in just one line of code.
	 *
	 * Usage:
	 *
	 * list($book, $pages) = $db->dispenseAll('book,page*100');
	 *
	 * This returns an array with a book bean and then another array
	 * containing 100 page beans.
	 *
	 * @param string $order a description of the desired dispense order using the syntax above
	 *
	 * @return array
	 */
	public function dispenseAll( $order )
	{

		$list = array();

		foreach( explode( ',', $order ) as $order ) {
			if ( strpos( $order, '*' ) !== false ) {
				list( $type, $amount ) = explode( '*', $order );
			} else {
				$type   = $order;
				$amount = 1;
			}

			$list[] = $this->dispense( $type, $amount );
		}

		return $list;
	}

	/**
	 * Toggles strict bean type names.
	 * If set to TRUE (default) this will forbid the use of underscores and
	 * uppercase characters in bean type strings ($db->dispense).
	 *
	 * @param boolean
	 */
	public function setStrictTyping( $trueFalse )
	{
		$this->strictType = (bool) $trueFalse;
	}

	/**
	 * Convience method. Tries to find beans of a certain type,
	 * if no beans are found, it dispenses a bean of that type.
	 *
	 * @param  string $type     type of bean you are looking for
	 * @param  string $sql      SQL code for finding the bean
	 * @param  array  $bindings parameters to bind to SQL
	 *
	 * @return array
	 */
	public function findOrDispense( $type, $sql = NULL, $bindings = array() )
	{
		return $this->finder->findOrDispense( $type, $sql, $bindings );
	}

	/**
	 * Associates two Beans. This method will associate two beans with eachother.
	 * You can then get one of the beans by using the related() function and
	 * providing the other bean. You can also provide a base bean in the extra
	 * parameter. This base bean allows you to add extra information to the association
	 * record. Note that this is for advanced use only and the information will not
	 * be added to one of the beans, just to the association record.
	 * It's also possible to provide an array or JSON string as base bean. If you
	 * pass a scalar this function will interpret the base bean as having one
	 * property called 'extra' with the value of the scalar.
	 *
	 * @todo extract from facade
	 *
	 * @param RedBean_OODBBean $bean1            bean that will be part of the association
	 * @param RedBean_OODBBean $bean2            bean that will be part of the association
	 * @param mixed            $extra            bean, scalar, array or JSON providing extra data.
	 *
	 * @return mixed
	 */
	public function associate( $beans1, $beans2, $extra = NULL )
	{
		if ( !$extra ) {
			return $this->associationManager->associate( $beans1, $beans2 );
		} else {
			return $this->extAssocManager->extAssociateSimple( $beans1, $beans2, $extra );
		}
	}

	/**
	 * Breaks the association between two beans.
	 * This functions breaks the association between a pair of beans. After
	 * calling this functions the beans will no longer be associated with
	 * eachother. Calling related() with either one of the beans will no longer
	 * return the other bean.
	 *
	 * @param RedBean_OODBBean $bean1 bean
	 * @param RedBean_OODBBean $bean2 bean
	 *
	 * @return mixed
	 */
	public function unassociate( $beans1, $beans2, $fast = FALSE )
	{
		$this->associationManager->unassociate( $beans1, $beans2, $fast );
	}

	/**
	 * Returns all the beans associated with $bean.
	 * This method will return an array containing all the beans that have
	 * been associated once with the associate() function and are still
	 * associated with the bean specified. The type parameter indicates the
	 * type of beans you are looking for. You can also pass some extra SQL and
	 * values for that SQL to filter your results after fetching the
	 * related beans.
	 *
	 * Don't try to make use of subqueries, a subquery using IN() seems to
	 * be slower than two queries!
	 *
	 * Since 3.2, you can now also pass an array of beans instead just one
	 * bean as the first parameter.
	 *
	 * @param RedBean_OODBBean|array $bean     the bean you have, the reference bean
	 * @param string                 $type     the type of beans you want to search for
	 * @param string                 $sql      SQL snippet for extra filtering
	 * @param array                  $bindings values to be inserted in SQL slots
	 *
	 * @return array
	 */
	public function related( $bean, $type, $sql = '', $bindings = array() )
	{
		return $this->associationManager->relatedSimple( $bean, $type, $sql, $bindings );
	}

	/**
	 * Counts the number of related beans in an N-M relation.
	 * Counts the number of beans of type $type that are related to $bean,
	 * using optional filtering SQL $sql with $bindings. This count will
	 * only search for N-M associated beans (works like countShared).
	 * The $bean->countShared() method is the preferred way to obtain this
	 * number.
	 *
	 * @warning not a preferred method, use $bean->countShared if possible.
	 *
	 * @param RedBean_OODBBean $bean     the bean you have, the reference bean
	 * @param string           $type     the type of bean you want to count
	 * @param string           $sql      SQL snippet for extra filtering
	 * @param array            $bindings values to be inserted in SQL slots
	 *
	 * @return integer
	 */
	public function relatedCount( $bean, $type, $sql = NULL, $bindings = array() )
	{
		return $this->associationManager->relatedCount( $bean, $type, $sql, $bindings );
	}

	/**
	 * Returns only a single associated bean.
	 * This works just like $db->related but returns a single bean. Which bean will be
	 * returned depends on the SQL snippet provided.
	 * For more details refer to $db->related.
	 *
	 * @warning not a preferred method, use $bean->shared if possible.
	 *
	 * @param RedBean_OODBBean $bean     the bean you have, the reference bean
	 * @param string           $type     type of bean you are searching for
	 * @param string           $sql      SQL for extra filtering
	 * @param array            $bindings values to be inserted in SQL slots
	 *
	 * @return RedBean_OODBBean
	 */
	public function relatedOne( RedBean_OODBBean $bean, $type, $sql = NULL, $bindings = array() )
	{
		return $this->associationManager->relatedOne( $bean, $type, $sql, $bindings );
	}

	/**
	 * Returns only the last associated bean.
	 * This works just like $db->related but returns a single bean, the last one.
	 * If the query result contains multiple beans, the last bean from this result set will be returned.
	 * For more details refer to $db->related.
	 *
	 * @warning not a preferred method, use $bean->shared if possible.
	 *
	 * @param RedBean_OODBBean $bean     bean provided
	 * @param string           $type     type of bean you are searching for
	 * @param string           $sql      SQL for extra filtering
	 * @param array            $bindings values to be inserted in SQL slots
	 *
	 * @return RedBean_OODBBean
	 */
	public function relatedLast( RedBean_OODBBean $bean, $type, $sql = NULL, $bindings = array() )
	{
		return $this->associationManager->relatedLast( $bean, $type, $sql, $bindings );
	}

	/**
	 * Checks whether a pair of beans is related N-M. This function does not
	 * check whether the beans are related in N:1 way.
	 * The name may be bit confusing because two beans can be related in
	 * various ways. This method only checks for many-to-many relations, for other
	 * relations please use $bean->ownX where X is the type of the bean you are
	 * looking for.
	 *
	 * @param RedBean_OODBBean $bean1 first bean
	 * @param RedBean_OODBBean $bean2 second bean
	 *
	 * @return boolean
	 */
	public function areRelated( RedBean_OODBBean $bean1, RedBean_OODBBean $bean2 )
	{
		return $this->associationManager->areRelated( $bean1, $bean2 );
	}

	/**
	 * Clears all associated beans.
	 * Breaks all many-to-many associations of a bean and a specified type.
	 * Only breaks N-M relations.
	 *
	 * @warning not a preferred method, use $bean->shared = array() if possible.
	 *
	 * @param RedBean_OODBBean $bean bean you wish to clear many-to-many relations for
	 * @param string           $type type of bean you wish to break associations with
	 *
	 * @return void
	 */
	public function clearRelations( RedBean_OODBBean $bean, $type )
	{
		$this->associationManager->clearRelations( $bean, $type );
	}

	/**
	 * Finds a bean using a type and a where clause (SQL).
	 * As with most Query tools in RedBean you can provide values to
	 * be inserted in the SQL statement by populating the value
	 * array parameter; you can either use the question mark notation
	 * or the slot-notation (:keyname).
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return array
	 */
	public function find( $type, $sql = NULL, $bindings = array() )
	{
		return $this->finder->find( $type, $sql, $bindings );
	}

	/**
	 * @see RedBean_Instance::find
	 *      The findAll() method differs from the find() method in that it does
	 *      not assume a WHERE-clause, so this is valid:
	 *
	 * $db->findAll('person',' ORDER BY name DESC ');
	 *
	 * Your SQL does not have to start with a valid WHERE-clause condition.
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return array
	 */
	public function findAll( $type, $sql = NULL, $bindings = array() )
	{
		return $this->finder->find( $type, $sql, $bindings );
	}

	/**
	 * @see RedBean_Instance::find
	 * The variation also exports the beans (i.e. it returns arrays).
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return array
	 */
	public function findAndExport( $type, $sql = NULL, $bindings = array() )
	{
		return $this->finder->findAndExport( $type, $sql, $bindings );
	}

	/**
	 * @see RedBean_Instance::find
	 * This variation returns the first bean only.
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return RedBean_OODBBean
	 */
	public function findOne( $type, $sql = NULL, $bindings = array() )
	{
		return $this->finder->findOne( $type, $sql, $bindings );
	}

	/**
	 * @see RedBean_Instance::find
	 * This variation returns the last bean only.
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return RedBean_OODBBean
	 */
	public function findLast( $type, $sql = NULL, $bindings = array() )
	{
		return $this->finder->findLast( $type, $sql, $bindings );
	}

	/**
	 * Returns an array of beans. Pass a type and a series of ids and
	 * this method will bring you the corresponding beans.
	 *
	 * important note: Because this method loads beans using the load()
	 * function (but faster) it will return empty beans with ID 0 for
	 * every bean that could not be located. The resulting beans will have the
	 * passed IDs as their keys.
	 *
	 * @param string $type type of beans
	 * @param array  $ids  ids to load
	 *
	 * @return array
	 */
	public function batch( $type, $ids )
	{
		return $this->redbean->batch( $type, $ids );
	}

	/**
	 * @see RedBean_Instance::batch
	 *
	 * Alias for batch(). Batch method is older but since we added so-called *All
	 * methods like storeAll, trashAll, dispenseAll and findAll it seemed logical to
	 * improve the consistency of the Facade API and also add an alias for batch() called
	 * loadAll.
	 *
	 * @param string $type type of beans
	 * @param array  $ids  ids to load
	 *
	 * @return array
	 */
	public function loadAll( $type, $ids )
	{
		return $this->redbean->batch( $type, $ids );
	}

	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return integer
	 */
	public function exec( $sql, $bindings = array() )
	{
		return $this->query( 'exec', $sql, $bindings );
	}

	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return array
	 */
	public function getAll( $sql, $bindings = array() )
	{
		return $this->query( 'get', $sql, $bindings );
	}

	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return string
	 */
	public function getCell( $sql, $bindings = array() )
	{
		return $this->query( 'getCell', $sql, $bindings );
	}

	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return array
	 */
	public function getRow( $sql, $bindings = array() )
	{
		return $this->query( 'getRow', $sql, $bindings );
	}

	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return array
	 */
	public function getCol( $sql, $bindings = array() )
	{
		return $this->query( 'getCol', $sql, $bindings );
	}

	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 * Results will be returned as an associative array. The first
	 * column in the select clause will be used for the keys in this array and
	 * the second column will be used for the values. If only one column is
	 * selected in the query, both key and value of the array will have the
	 * value of this field for each row.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return array
	 */
	public function getAssoc( $sql, $bindings = array() )
	{
		return $this->query( 'getAssoc', $sql, $bindings );
	}

	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 * Results will be returned as an associative array indexed by the first
	 * column in the select.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return array
	 */
	public function getAssocRow( $sql, $bindings = array() )
	{
		return $this->query( 'getAssocRow', $sql, $bindings );
	}

	/**
	 * Makes a copy of a bean. This method makes a deep copy
	 * of the bean.The copy will have the following features.
	 * - All beans in own-lists will be duplicated as well
	 * - All references to shared beans will be copied but not the shared beans themselves
	 * - All references to parent objects (_id fields) will be copied but not the parents themselves
	 * In most cases this is the desired scenario for copying beans.
	 * This function uses a trail-array to prevent infinite recursion, if a recursive bean is found
	 * (i.e. one that already has been processed) the ID of the bean will be returned.
	 * This should not happen though.
	 *
	 * Note:
	 * This function does a reflectional database query so it may be slow.
	 *
	 * @param RedBean_OODBBean $bean  bean to be copied
	 * @param array            $trail for internal usage, pass array()
	 * @param boolean          $pid   for internal usage
	 *
	 * @return array
	 */
	public function dup( $bean, $trail = array(), $pid = FALSE, $filters = array() )
	{
		$this->duplicationManager->setFilters( $filters );

		return $this->duplicationManager->dup( $bean, $trail, $pid );
	}

	/**
	 * Exports a collection of beans. Handy for XML/JSON exports with a
	 * Javascript framework like Dojo or ExtJS.
	 * What will be exported:
	 * - contents of the bean
	 * - all own bean lists (recursively)
	 * - all shared beans (not THEIR own lists)
	 *
	 * @param    array|RedBean_OODBBean $beans   beans to be exported
	 * @param    boolean                $parents whether you want parent beans to be exported
	 * @param   array                   $filters whitelist of types
	 *
	 * @return    array
	 */
	public function exportAll( $beans, $parents = FALSE, $filters = array() )
	{
		return $this->duplicationManager->exportAll( $beans, $parents, $filters );
	}

	/**
	 * @deprecated
	 * Given two beans and a property this method will
	 * swap the values of the property in the beans.
	 *
	 * @param array  $beans    beans to swap property values of
	 * @param string $property property whose value you want to swap
	 *
	 * @return void
	 */
	public function swap( $beans, $property )
	{
		$this->associationManager->swap( $beans, $property );
	}

	/**
	 * Converts a series of rows to beans.
	 * This method converts a series of rows to beans.
	 * The type of the desired output beans can be specified in the
	 * first parameter. The second parameter is meant for the database
	 * result rows.
	 *
	 * @param string $type type of beans to produce
	 * @param array  $rows must contain an array of array
	 *
	 * @return array
	 */
	public function convertToBeans( $type, $rows )
	{
		return $this->redbean->convertToBeans( $type, $rows );
	}

	/**
	 * Part of RedBeanPHP Tagging API.
	 * Tests whether a bean has been associated with one ore more
	 * of the listed tags. If the third parameter is TRUE this method
	 * will return TRUE only if all tags that have been specified are indeed
	 * associated with the given bean, otherwise FALSE.
	 * If the third parameter is FALSE this
	 * method will return TRUE if one of the tags matches, FALSE if none
	 * match.
	 *
	 * @param  RedBean_OODBBean $bean bean to check for tags
	 * @param  array            $tags list of tags
	 * @param  boolean          $all  whether they must all match or just some
	 *
	 * @return boolean
	 */
	public function hasTag( $bean, $tags, $all = FALSE )
	{
		return $this->tagManager->hasTag( $bean, $tags, $all );
	}

	/**
	 * Part of RedBeanPHP Tagging API.
	 * Removes all specified tags from the bean. The tags specified in
	 * the second parameter will no longer be associated with the bean.
	 *
	 * @param  RedBean_OODBBean $bean    tagged bean
	 * @param  array            $tagList list of tags (names)
	 *
	 * @return void
	 */
	public function untag( $bean, $tagList )
	{
		$this->tagManager->untag( $bean, $tagList );
	}

	/**
	 * Part of RedBeanPHP Tagging API.
	 * Tags a bean or returns tags associated with a bean.
	 * If $tagList is NULL or omitted this method will return a
	 * comma separated list of tags associated with the bean provided.
	 * If $tagList is a comma separated list (string) of tags all tags will
	 * be associated with the bean.
	 * You may also pass an array instead of a string.
	 *
	 * @param RedBean_OODBBean $bean    bean
	 * @param mixed            $tagList tags
	 *
	 * @return string
	 */
	public function tag( RedBean_OODBBean $bean, $tagList = NULL )
	{
		return $this->tagManager->tag( $bean, $tagList );
	}

	/**
	 * Part of RedBeanPHP Tagging API.
	 * Adds tags to a bean.
	 * If $tagList is a comma separated list of tags all tags will
	 * be associated with the bean.
	 * You may also pass an array instead of a string.
	 *
	 * @param RedBean_OODBBean $bean    bean
	 * @param array            $tagList list of tags to add to bean
	 *
	 * @return void
	 */
	public function addTags( RedBean_OODBBean $bean, $tagList )
	{
		$this->tagManager->addTags( $bean, $tagList );
	}

	/**
	 * Part of RedBeanPHP Tagging API.
	 * Returns all beans that have been tagged with one of the tags given.
	 *
	 * @param string $beanType type of bean you are looking for
	 * @param array  $tagList  list of tags to match
	 *
	 * @return array
	 */
	public function tagged( $beanType, $tagList )
	{
		return $this->tagManager->tagged( $beanType, $tagList );
	}

	/**
	 * Part of RedBeanPHP Tagging API.
	 * Returns all beans that have been tagged with ALL of the tags given.
	 *
	 * @param string $beanType type of bean you are looking for
	 * @param array  $tagList  list of tags to match
	 *
	 * @return array
	 */
	public function taggedAll( $beanType, $tagList )
	{
		return $this->tagManager->taggedAll( $beanType, $tagList );
	}

	/**
	 * Wipes all beans of type $beanType.
	 *
	 * @param string $beanType type of bean you want to destroy entirely
	 *
	 * @return boolean
	 */
	public function wipe( $beanType )
	{
		return $this->redbean->wipe( $beanType );
	}

	/**
	 * Counts the number of beans of type $type.
	 * This method accepts a second argument to modify the count-query.
	 * A third argument can be used to provide bindings for the SQL snippet.
	 *
	 * @param string $type     type of bean we are looking for
	 * @param string $addSQL   additional SQL snippet
	 * @param array  $bindings parameters to bind to SQL
	 *
	 * @return integer
	 *
	 * @throws RedBean_Exception_SQL
	 */
	public function count( $type, $addSQL = '', $bindings = array() )
	{
		return $this->redbean->count( $type, $addSQL, $bindings );
	}

	/**
	 * Configures the facade, want to have a new Writer? A new Object Database or a new
	 * Adapter and you want it on-the-fly? Use this method to hot-swap your facade with a new
	 * toolbox.
	 *
	 * @param RedBean_ToolBox $tb toolbox
	 *
	 * @return RedBean_ToolBox
	 */
	public function configureFacadeWithToolbox( RedBean_ToolBox $tb )
	{
		$oldTools                 = $this->toolbox;

		$this->toolbox            = $tb;

		$this->writer             = $this->toolbox->getWriter();
		$this->adapter            = $this->toolbox->getDatabaseAdapter();
		$this->redbean            = $this->toolbox->getRedBean();
		$this->finder             = new RedBean_Finder( $this->toolbox );

		$this->associationManager = new RedBean_AssociationManager( $this->toolbox );

		$this->redbean->setAssociationManager( $this->associationManager );

		$this->labelMaker         = new RedBean_LabelMaker( $this->toolbox );
		$this->extAssocManager    = new RedBean_AssociationManager_ExtAssociationManager( $this->toolbox );

		$helper                   = new RedBean_ModelHelper();

		$helper->attachEventListeners( $this->redbean );

		$this->associationManager->addEventListener( 'delete', $helper );

		$this->duplicationManager = new RedBean_DuplicationManager( $this->toolbox );
		$this->tagManager         = new RedBean_TagManager( $this->toolbox );
		$this->f                  = new RedBean_SQLHelper( $this->adapter );

		return $oldTools;
	}

	/**
	 * Facade Convience method for adapter transaction system.
	 * Begins a transaction.
	 *
	 * @return bool
	 */
	public function begin()
	{
		if ( !$this->redbean->isFrozen() ) return FALSE;

		$this->adapter->startTransaction();

		return TRUE;
	}

	/**
	 * Facade Convience method for adapter transaction system.
	 * Commits a transaction.
	 *
	 * @return bool
	 */
	public function commit()
	{
		if ( !$this->redbean->isFrozen() ) return FALSE;

		$this->adapter->commit();

		return TRUE;
	}

	/**
	 * Facade Convience method for adapter transaction system.
	 * Rolls back a transaction.
	 *
	 * @return bool
	 */
	public function rollback()
	{
		if ( !$this->redbean->isFrozen() ) return FALSE;

		$this->adapter->rollback();

		return TRUE;
	}

	/**
	 * Returns a list of columns. Format of this array:
	 * array( fieldname => type )
	 * Note that this method only works in fluid mode because it might be
	 * quite heavy on production servers!
	 *
	 * @param  string $table   name of the table (not type) you want to get columns of
	 *
	 * @return array
	 */
	public function getColumns( $table )
	{
		return $this->writer->getColumns( $table );
	}

	/**
	 * Generates question mark slots for an array of values.
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	public function genSlots( $array )
	{
		return $this->f->genSlots( $array );
	}

	/**
	 * Nukes the entire database.
	 * This will remove all schema structures from the database.
	 * Only works in fluid mode. Be careful with this method.
	 *
	 * @warning dangerous method, will remove all tables, columns etc.
	 *
	 * @return void
	 */
	public function nuke()
	{
		if ( !$this->redbean->isFrozen() ) {
			$this->writer->wipeAll();
		}
	}

	/**
	 * Sets a list of dependencies.
	 * A dependency list contains an entry for each dependent bean.
	 * A dependent bean will be removed if the relation with one of the
	 * dependencies gets broken.
	 *
	 * Example:
	 *
	 * array(
	 *    'page' => array('book', 'magazine')
	 * )
	 *
	 * A page will be removed if:
	 *
	 * unset($book->ownPage[$pageID]);
	 *
	 * or:
	 *
	 * unset($magazine->ownPage[$pageID]);
	 *
	 * but not if:
	 *
	 * unset($paper->ownPage[$pageID]);
	 *
	 * @param array $dep list of dependencies
	 *
	 * @return void
	 */
	public function dependencies( $dep )
	{
		$this->redbean->setDepList( $dep );
	}

	/**
	 * Short hand function to store a set of beans at once, IDs will be
	 * returned as an array. For information please consult the $db->store()
	 * function.
	 * A loop saver.
	 *
	 * @param array $beans list of beans to be stored
	 *
	 * @return array
	 */
	public function storeAll( $beans )
	{
		$ids = array();
		foreach ( $beans as $bean ) {
			$ids[] = $this->store( $bean );
		}

		return $ids;
	}

	/**
	 * Short hand function to trash a set of beans at once.
	 * For information please consult the $db->trash() function.
	 * A loop saver.
	 *
	 * @param array $beans list of beans to be trashed
	 *
	 * @return void
	 */
	public function trashAll( $beans )
	{
		foreach ( $beans as $bean ) {
			$this->trash( $bean );
		}
	}

	/**
	 * Toggles Writer Cache.
	 * Turns the Writer Cache on or off. The Writer Cache is a simple
	 * query based caching system that may improve performance without the need
	 * for cache management. This caching system will cache non-modifying queries
	 * that are marked with special SQL comments. As soon as a non-marked query
	 * gets executed the cache will be flushed. Only non-modifying select queries
	 * have been marked therefore this mechanism is a rather safe way of caching, requiring
	 * no explicit flushes or reloads. Of course this does not apply if you intend to test
	 * or simulate concurrent querying.
	 *
	 * @param boolean $yesNo TRUE to enable cache, FALSE to disable cache
	 *
	 * @return void
	 */
	public function useWriterCache( $yesNo )
	{
		$this->getWriter()->setUseCache( $yesNo );
	}


	/**
	 * A label is a bean with only an id, type and name property.
	 * This function will dispense beans for all entries in the array. The
	 * values of the array will be assigned to the name property of each
	 * individual bean.
	 *
	 * @param string $type   type of beans you would like to have
	 * @param array  $labels list of labels, names for each bean
	 *
	 * @return array
	 */
	public function dispenseLabels( $type, $labels )
	{
		return $this->labelMaker->dispenseLabels( $type, $labels );
	}

	/**
	 * Generates and returns an ENUM value. This is how RedBeanPHP handles ENUMs.
	 * Either returns a (newly created) bean respresenting the desired ENUM
	 * value or returns a list of all enums for the type.
	 *
	 * To obtain (and add if necessary) an ENUM value:
	 *
	 * $tea->flavour = $db->enum( 'flavour:apple' );
	 *
	 * Returns a bean of type 'flavour' with  name = apple.
	 * This will add a bean with property name (set to APPLE) to the database
	 * if it does not exist yet.
	 *
	 * To obtain all flavours:
	 *
	 * $db->enum('flavour');
	 *
	 * To get a list of all flavour names:
	 *
	 * $db->gatherLabels( $db->enum( 'flavour' ) );
	 *
	 * @param string $enum either type or type-value
	 *
	 * @return array|RedBean_OODBBean
	 */
	public function enum( $enum )
	{
		return $this->labelMaker->enum( $enum );
	}

	/**
	 * Gathers labels from beans. This function loops through the beans,
	 * collects the values of the name properties of each individual bean
	 * and stores the names in a new array. The array then gets sorted using the
	 * default sort function of PHP (sort).
	 *
	 * @param array $beans list of beans to loop
	 *
	 * @return array
	 */
	public function gatherLabels( $beans )
	{
		return $this->labelMaker->gatherLabels( $beans );
	}

	/**
	 * Closes the database connection.
	 *
	 * @return void
	 */
	public function close()
	{
		if ( isset( $this->adapter ) ) {
			$this->adapter->close();
		}
	}

	/**
	 * Simple convenience function, returns ISO date formatted representation
	 * of $time.
	 *
	 * @param mixed $time UNIX timestamp
	 *
	 * @return string
	 */
	public function isoDate( $time = NULL )
	{
		if ( !$time ) {
			$time = time();
		}

		return @date( 'Y-m-d', $time );
	}

	/**
	 * Simple convenience function, returns ISO date time
	 * formatted representation
	 * of $time.
	 *
	 * @param mixed $time UNIX timestamp
	 *
	 * @return string
	 */
	public function isoDateTime( $time = NULL )
	{
		if ( !$time ) $time = time();

		return @date( 'Y-m-d H:i:s', $time );
	}

	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @param RedBean_Adapter $adapter
	 *
	 * @return void
	 */
	public function setDatabaseAdapter( RedBean_Adapter $adapter )
	{
		$this->adapter = $adapter;
	}

	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @param RedBean_QueryWriter $writer
	 *
	 * @return void
	 */
	public function setWriter( RedBean_QueryWriter $writer )
	{
		$this->writer = $writer;
	}

	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @param RedBean_OODB $redbean
	 */
	public function setRedBean( RedBean_OODB $redbean )
	{
		$this->redbean = $redbean;
	}

	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @return RedBean_Adapter_DBAdapter
	 */
	public function getDatabaseAdapter()
	{
		return $this->adapter;
	}

	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @return RedBean_QueryWriter
	 */
	public function getWriter()
	{
		return $this->writer;
	}

	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @return RedBean_OODB
	 */
	public function getRedBean()
	{
		return $this->redbean;
	}

	/**
	 * Returns the toolbox currently used by the facade.
	 * To set the toolbox use $db->setup() or $db->configureFacadeWithToolbox().
	 * To create a toolbox use RedBean_Setup::kickstart(). Or create a manual
	 * toolbox using the RedBean_Toolbox class.
	 *
	 * @return RedBean_ToolBox
	 */
	public function getToolBox()
	{
		return $this->toolbox;
	}

	/**
	 * Preloads certain properties for beans.
	 * Understands aliases.
	 *
	 * Usage:
	 *
	 * $db->preload($books, 'author');
	 *
	 * - preloads all the authors of all books,
	 * saves you a query per for-each iteration
	 *
	 * $db->preload($books, array('coauthor'=>'author'));
	 *
	 * - same but with alias
	 *
	 * $db->preload($texts,'page,page.book,page.book.author');
	 *
	 * - preloads all pages for the texts, the books and the authors
	 *
	 * $db->preload($texts,'page,*.book,*.author');
	 *
	 * - same as above bit with short syntax (* means prefix with previous types)
	 *
	 * $db->preload($p,'book,*.author,&.shelf');
	 *
	 * - if author and shelf are on the same level use & instead of *.
	 *
	 * The other way around is possible as well, to load child beans in own-lists or
	 * shared-lists use:
	 *
	 * $db->preload($books,'ownPage|page,sharedGenre|genre');
	 *
	 * @param array        $beans beans beans to use as a reference for preloading
	 * @param array|string $types types to load, either string or array
	 *
	 * @return array
	 */
	public function preload( $beans, $types, $closure = NULL )
	{
		return $this->redbean->preload( $beans, $types, $closure );
	}

	/**
	 * Alias for preload.
	 * Preloads certain properties for beans.
	 * Understands aliases.
	 *
	 * @see RedBean_Instance::preload
	 *
	 * Usage: $db->preload($books, array('coauthor'=>'author'));
	 *
	 * @param array        $beans   beans beans to use as a reference for preloading
	 * @param array|string $types   types to load, either string or array
	 * @param closure      $closure function to call
	 *
	 * @return array
	 */
	public function each( $beans, $types, $closure = NULL )
	{
		return $this->preload( $beans, $types, $closure );
	}

	/**
	 * Facade method for RedBean_QueryWriter_AQueryWrite$db->renameAssociation()
	 *
	 * @param string|array $from
	 * @param string       $to
	 *
	 * @return void
	 */
	public function renameAssociation( $from, $to = NULL )
	{
		RedBean_QueryWriter_AQueryWriter::renameAssociation( $from, $to );
	}

	/**
	 * Little helper method for Resty Bean Can server and others.
	 * Takes an array of beans and exports each bean.
	 * Unlike exportAll this method does not recurse into own lists
	 * and shared lists, the beans are exported as-is, only loaded lists
	 * are exported.
	 *
	 * @param array $beans beans
	 *
	 * @return array
	 */
	public function beansToArray( $beans )
	{
		$list = array();
		foreach( $beans as $bean ) {
			$list[] = $bean->export();
		}
		return $list;
	}

	/**
	 * Dynamically extends the facade with a plugin.
	 * Using this method you can register your plugin with the facade and then
	 * use the plugin by invoking the name specified plugin name as a method on
	 * the facade.
	 *
	 * Usage:
	 *
	 * $db->ext( 'makeTea', function() { ... }  );
	 *
	 * Now you can use your makeTea plugin like this:
	 *
	 * $db->makeTea();
	 *
	 * @param string   $pluginName name of the method to call the plugin
	 * @param callable $callable   a PHP callable
	 */
	public function ext( $pluginName, $callable )
	{
		if ( !ctype_alnum( $pluginName ) ) {
			throw new RedBean_Exception( 'Plugin name may only contain alphanumeric characters.' );
		}
		$this->plugins[$pluginName] = $callable;
	}

	/**
	 * Call for use with dynamic plugins. This magic method will
	 * intercept static calls and route them to the specified plugin.
	 *
	 * @param string $pluginName name of the plugin
	 * @param array  $params     list of arguments to pass to plugin method
	 *
	 * @return mixed
	 */
	public function __call( $pluginName, $params )
	{
		if ( !ctype_alnum( $pluginName) ) {
			throw new RedBean_Exception( 'Plugin name may only contain alphanumeric characters.' );
		}
		if ( !isset( $this->plugins[$pluginName] ) ) {
			throw new RedBean_Exception( 'Plugin \''.$pluginName.'\' does not exist, add this plugin using: $db->ext(\''.$pluginName.'\')' );
		}
		return call_user_func_array( $this->plugins[$pluginName], $params );
	}
}

//Compatibility with PHP 5.2 and earlier
if ( !function_exists( 'lcfirst' ) ) {
	function lcfirst( $str ) { return (string) ( strtolower( substr( $str, 0, 1 ) ) . substr( $str, 1 ) ); }
}
