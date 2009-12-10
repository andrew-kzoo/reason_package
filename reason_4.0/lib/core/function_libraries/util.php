<?php
	/**
	 *	Reason Utility Functions
	 *
	 *	This file contains a set of functions that can be considered the core of the Reason global function API.
	 *
	 *	This file contains basic functions for:
	 *	-- reading data from the Reason DB
	 *	-- providing reflection of the Reason DB data structure
	 *	-- privileges
	 *	-- editor integration
	 *	-- common string handling needs & other basic utilities
	 *
	 *	Functions that write to the database -- Create, Update, Delete -- are *not* in this file; they are in admin_actions.php
	 *
	 *	@todo Rethink the global namespace aspect of many of these functions -- can they be put in a static class so as to fimplify the API and clean up the global namespace?
	 *
	 *	@author Dave Hendler
	 *	@author Nate White
	 *	@author Matt Ryan
	 *
	 *	@copyright 2002-2008 Carleton College
	 *	@license http://www.gnu.org/copyleft/gpl.html GNU General Public License
	 *
	 *	@package reason
	 *  @subpackage function_libraries
	 */

	/**
	 * Include dependencies and other files that should be considered part of the basic Reason utilities
	 */
	include_once( 'reason_header.php' );
	include_once( CARL_UTIL_INC . 'db/db_selector.php' );
	reason_include_once('function_libraries/url_utils.php');

	/**
	 * Get the id of an item with a given unique name
	 *
	 * This is the standard way to find the ID of a specific item in the Reason database.
	 * It is how, for example, reason type entities are identified.
	 *
	 * For example, if you are confident a given unique name is in the database:
	 *
	 * $image_type = new entity(id_of('image'));
	 *
	 * Or, if you're not confident:
	 * 
	 * if($id = id_of('notsure', true, false))
	 * {
	 * 	$entity = new entity($id);
	 * 	...
	 * }
	 *
	 * @param string $unique_name The unique name that you want the id of
	 * @param boolean $cache Use a cache (specific to the script)? Set to false if you think the current process may have updated this unique name
	 * @param boolean $report_not_found_error Trigger a warning if the unique name is not in the database. Set to false if the value is coming from userland or if it is a test that you will be doing separate reporting on.
	 * @return integer The Reason ID of the corresponding entity or 0 if not found
	 */
	function id_of( $unique_name, $cache = true, $report_not_found_error = true )// {{{
	{
		static $retrieved = array();

		if( !$cache || empty( $retrieved ) )
		{
			$retrieved = array();
			$q = "SELECT id, unique_name FROM entity WHERE unique_name IS NOT NULL AND unique_name != '' AND (state = 'Live' OR state = 'Pending')";
			$r = db_query( $q , "Error getting unique_names" );
			while( $row = mysql_fetch_array( $r ))
				$retrieved[ $row[ 'unique_name' ] ] = $row[ 'id' ];
			mysql_free_result( $r );

		}
		if( isset( $retrieved[ $unique_name ] ) )
			return $retrieved[ $unique_name ];
		else
		{
			if($report_not_found_error)
				trigger_error('Unique name requested ('.$unique_name.') not in database');
			return 0;
		}
	} // }}}
	/**
	 * Check to see if a given unique name exists in the Reason database
	 *
	 * @param string $unique_name The unique name to check
	 * @param boolean $cache Set to false if you don't want Reason to use the process-level cache (this option will take somewhat longer to complete)
	 * @return boolean true if the unique name exists, false if it does not
	 */
	function reason_unique_name_exists($unique_name, $cache = true)
	{
		if(id_of($unique_name, $cache, false))
			return true;
		return false;
	}
	/**
	 * Identifies whether a given string could be used as a unique name
	 *
	 * Note that this function *does not* check whether the given string is already used as a unique name -- only if it matches a fixed pattern
	 *
	 * @param string $string The string to check
	 * @return boolean true if it matches the pattern, otherwise false
	 */
	function reason_unique_name_valid_string($string)
	{
		if(!empty($string) && preg_match( "|^[0-9a-z_]*$|i", $string))
			return true;
		return false;
	}
	/**
	 * Validate a string as fitting the unique name pattern
	 *
	 * This function is similar to @reason_unique_name_valid_string(), but returns the given string if the given string matches the pattern
	 *
	 * @param string $string The string to validate
	 * @return string The given string if it matches the pattern; otherwise an empty string
	 */
	function reason_unique_name_validate_string($string)
	{
		if(reason_unique_name_valid_string($string))
			return $string;
		return '';
	}
	/**
	 * Get the id of a relationship in the allowable_relationships table
	 *
	 * This is the standard way to reference a relationship type defined in the allowable_relationships table
	 *
	 * Usage:
	 * $es = new entity_selector();
	 * $es->add_type(id_of('image');
	 * $es->add_left_relationship($page_id, relationship_id_of('page_to_image'); // limit selection to images related to the page via the page_to_image relationship
	 * $images = $es->run_one();
	 *
	 * @param string $relationship_name The name of the relationship we want to get the ID of
	 * @param boolean $cache Set to false if you don't want Reason to consult its process-level cache (for example, if you have added the relationship earlier in the same process)
	 * @param boolean $report_not_found_error Set to false if you don't want Reason to emit a warning if the relationship name can't be found
	 * @return mixed The relationship's ID if found; otherwise boolean false
	 */
	function relationship_id_of( $relationship_name, $cache = true, $report_not_found_error = true ) // {{{
	{
		static $retrieved;
		if( !isset( $retrieved ) OR empty( $retrieved ) )
			$retrieved = array();

		if( !$cache OR !isset( $retrieved[ $relationship_name ] ) OR !$retrieved[ $relationship_name ] )
		{
			$q = "SELECT id FROM allowable_relationship WHERE name = '" . addslashes($relationship_name) . "'";
			$r = db_query( $q , "Error getting relationship id" );
			if( $row = mysql_fetch_array( $r ))
			{
				$id = $row['id'];
				mysql_free_result( $r );
				$retrieved[ $relationship_name ] = $id;
				return $id;
			}
			else
			{
				if($report_not_found_error)
					trigger_error('Relationship unique name requested ('.$relationship_name.') not in database');
				mysql_free_result( $r );
				return false;
			}
		}
		else
			return $retrieved[ $relationship_name ];
	} // }}}
	
	/**
	 * Find out if a given table name exists in reason
	 *
	 * @param string $table_name The table you want to test
	 * @return boolean true if found, otherwise false
	 */
	function reason_table_exists($table_name)
	{
		$es = new entity_selector();
		$es->add_type(id_of('content_table'));
		$es->add_relation('entity.name = "'.$table_name.'"');
		$results = $es->run_one();
		return (!empty($results));
	}
	
	/**
	 * Find out if a given relationship name exists in the allowable relationships table
	 *
	 * @param string $relationship_name The name we want to test
	 * @param boolean $cache Set to false if you don't want Reason to consult its process-level cache (for example, if you have added the relationship earlier in the same process)
	 * @return boolean true if found, otherwise false
	 */
	function reason_relationship_name_exists($relationship_name, $cache = true)
	{
		if(relationship_id_of($relationship_name, $cache, false))
			return true;
		return false;
	}
	
	/**
	 * Get all the allowable relationships that feature a given type
	 * @param integer $type_id
	 * @return array of type row arrays (fieldname=>fieldvalue)
	 * @author Matt Ryan
	 */
	function get_allowable_relationships_for_type($type_id)
	{
		$ret = array();
		$q = 'SELECT * FROM allowable_relationship WHERE (relationship_a = "' . addslashes($type_id) . '" OR relationship_b = "'.addslashes($type_id).'")';
		$r = db_query( $q , "Error getting relationships for type: $type_id" );
		while( $row = mysql_fetch_assoc( $r ))
		{
			$ret[$row['id']] = $row;
		}
		mysql_free_result( $r );
		return $ret;
	}
	
	/**
	 * Find the name of a relationship from its ID
	 *
	 * @param integer $relationship_id The ID of the relationship to test
	 * @param boolean $cache Set to false if you don't want Reason to consult its process-level cache (for example, if you have added the relationship earlier in the same process)
	 * @return mixed The (string) relationship name if found, otherwise boolean false
	 */
	function relationship_name_of( $relationship_id, $cache = true ) // {{{
	{
		static $retrieved;
		if( !isset( $retrieved ) OR empty( $retrieved ) )
			$retrieved = array();

		if( !$cache OR !isset( $retrieved[ $relationship_id ] ) OR !$retrieved[ $relationship_id ] )
		{
			$q = "SELECT name FROM allowable_relationship WHERE id = '" . $relationship_id . "'";
			$r = db_query( $q , "Error getting relationship name" );
			if( $row = mysql_fetch_array( $r ))
			{
				$name = $row['name'];
				mysql_free_result( $r );
				$retrieved[ $relationship_id ] = $name;
				return $name;
			}
			else
			{
				mysql_free_result( $r );
				return false;
			}
		}
		else
			return $retrieved[ $relationship_id ];
	} // }}}
	
	/**
	* Finds the id of the allowable relationship of the "site borrows ..." relationship for a given type.  
	* @param int $type_id The id of the type that the site borrows
	* @return mixed $alrel_id The id of the allowable relationship or false if none found
	*/
	function get_borrow_relationship_id($type_id)
	{
		static $cache = array();
		if(!isset($cache[$type_id]))
		{
			$q = 'SELECT `id` FROM allowable_relationship WHERE name = "borrows" AND relationship_a = '. id_of( 'site' ) . ' AND relationship_b = ' . $type_id.' LIMIT 0,1';
			$r = db_query( $q , 'Error selecting allowable relationship in get_borrow_relationship_id()' );
			$row = mysql_fetch_array( $r , MYSQL_ASSOC );
			if(!empty($row[ 'id']))
			{
				$cache[$type_id] = $row[ 'id'];
			}
			else
			{
				trigger_error('No allowable relationship found for site borrows type id '.$type_id);
				$cache[$type_id] = false;
			}
		}
		return $cache[$type_id];
	}
	
	/**
	 * Finds the id of the parent allowable relationship for a given type
	 *
	 * Note that this function caches results, so it can be called multiple times with little performance impact
	 *
	 * @param integer $type_id the id of the type
	 * @return mixed The alrel id if a parent relationship exists; otherwise false
	 * @author Matt Ryan (mryan@acs.carleton.edu)
	 */
	function get_parent_allowable_relationship_id($type_id)
	{
		static $cache = array();
		if(!isset($cache[$type_id]))
		{
			$q = 'SELECT `id` FROM allowable_relationship WHERE name LIKE "%parent%" AND relationship_a = "'. $type_id . '" AND relationship_b = "' . $type_id.'" LIMIT 0,1';
			$r = db_query( $q , 'Error selecting allowable relationship in get_parent_allowable_relationship_id()' );
			$row = mysql_fetch_array( $r , MYSQL_ASSOC );
			if(!empty($row[ 'id']))
			{
				$cache[$type_id] = $row[ 'id'];
			}
			else
			{
				$cache[$type_id] = false;
			}
		}
		return $cache[$type_id]; 
	}
	
	// big fat warning: this object will not retrieve the 'entity' table.  that is assumed.  use this function at your own risk.
	// REMEMBER TO ADD THE ENTITY TABLE TO THE LIST OF TABLES THIS WILL PRODUCE
	function get_entity_tables_by_type_object( $type ) // {{{
	{
		$dbq = new DBSelector;

		$dbq->add_field( 'e','name' );
		$dbq->add_table( 'e','entity' );
		$dbq->add_table( 'e2','entity' );
		$dbq->add_table( 'r','relationship' );
		$dbq->add_relation( 'e.type = e2.id' );
		$dbq->add_relation( 'e2.unique_name = "content_table"' );
		$dbq->add_relation( 'r.entity_a = '.$type );
		$dbq->add_relation( 'r.entity_b = e.id' );
		return $dbq;

	} // }}}
	// the easy API function adds the 'entity' table.  So it's cool.
	function get_entity_tables_by_type( $type, $cache = true ) // {{{
	{
		static $retrieved;
		if( !isset( $retrieved ) OR empty( $retrieved ) )
		{
			$dbq = new DBSelector;

			$dbq->add_field( 'e','name' );
			$dbq->add_field( 'r','entity_a' );
			$dbq->add_table( 'e','entity' );
			$dbq->add_table( 'e2','entity' );
			$dbq->add_table( 'r','relationship' );
			$dbq->add_relation( 'e.type = e2.id' );
			$dbq->add_relation( 'e2.unique_name = "content_table"' );
			$dbq->add_relation( 'r.entity_b = e.id' );
			
			$retrieved = array();
		}

		if( !$cache OR !isset( $retrieved[ $type ] ) OR !$retrieved[ $type ] )
		{
			$dbq = get_entity_tables_by_type_object( $type );
			
			$tables[] = 'entity';
			$r = db_query( $dbq->get_query(),'Unable to load entity tables by type.' );
			while( $row = mysql_fetch_array( $r, MYSQL_ASSOC ) )
				$tables[] = $row['name'];
			mysql_free_result( $r );
			
			$retrieved[ $type ] = $tables;
			return $tables;
		}
		else
			return $retrieved[ $type ];
	} // }}}

	function get_entity_tables_by_id_object( $id ) // {{{
	{
		$dbq = new DBSelector;

		$dbq->add_field( 't','name' );
		
		$dbq->add_table( 't','entity' );
		$dbq->add_table( 'type','entity' );
		$dbq->add_table( 'item','entity' );
		$dbq->add_table( 'r','relationship' );
		$dbq->add_table( 'ar','allowable_relationship' );

		$dbq->add_relation( 't.type = type.id' );
		$dbq->add_relation( 'type.unique_name = "content_table"' );
		$dbq->add_relation( 'item.id = '.$id );
		$dbq->add_relation( 'r.entity_a = item.type' );
		$dbq->add_relation( 'r.entity_b = t.id' );
		$dbq->add_relation( 'ar.name = "type_to_table"' );

		return $dbq;
	} // }}}
	/**
	 * Given a type id, find the tables that make up the type
	 * @param integer $id the id of a Reason type entity
	 * @param boolean $cache false will force this function to do a new query (normally does only one query per type and subsequently refers to an internal cache)
	 * @return array of table names
	 */
	function get_entity_tables_by_id( $id, $cache = true ) // {{{
	{
		$id = (integer) $id;
		static $retrieved;
		if( empty( $retrieved ) )
			$retrieved = array();
		if(!$id)
		{
			trigger_error('Empty ID passed to get_entity_tables_by_id()',FATAL);
			die();
		}
		
		// originally: if( !$cache OR !isset( $retrieved[ $id ] ) OR !$retrieved[ $id ] )
		if( !$cache OR empty( $retrieved[ $id ] ) )
		{
			$q = get_entity_tables_by_id_object( $id );
		
			$tables[] = 'entity';
			$r = db_query( $q->get_query(),'Unable to load entity tables by id.' );
			while( $row = mysql_fetch_array( $r, MYSQL_ASSOC ) )
				$tables[] = $row['name'];
			mysql_free_result( $r );

			$retrieved[ $id ] = $tables;
			return $tables;
		}
		else
			return $retrieved[ $id ];
	} // }}}

	function get_types_object() // {{{
	{
		$dbq = new DBSelector;

		$dbq->add_table( 'e','entity' );
		$dbq->add_table( 'e2','entity' );
		$dbq->add_field( 'e','*' );
		$dbq->add_relation( 'e.type = e2.id');
		$dbq->add_relation( 'e2.id = e2.type' );

		return $dbq;
	} // }}}
	function get_types() // {{{
	{
		$dbq = get_types_object();

		$r = db_query( $dbq->get_query(), 'Unable to grab types' );
		while( $row = mysql_fetch_array( $r, MYSQL_ASSOC ) )
			$results[ $row['id'] ] = $row;
		mysql_free_result( $r );
		return $results;
	} // }}}

	function get_type_id_from_name_object( $type_name ) // {{{
	{
		$q = new DBSelector;

		$q->add_table( 'type','entity' );
		$q->add_field( 'type','id' );
		$q->add_relation( 'type.unique_name = "'.$type_name.'"' );

		return $q;
	} // }}}
	function get_type_id_from_name( $type_name ) // {{{
	{
		$q = get_type_id_from_name_object( $type_name );

		$r = db_query( $q->get_query(), 'Unable to grab type id' );
		$row = mysql_fetch_array( $r, MYSQL_ASSOC );
		mysql_free_result( $r );
		return $row['id'];
	} // }}}

	function get_fields_by_content_table( $table ) // {{{
	{
		static $results = '';
		if( empty( $results ) )
			$results = array();
		if( empty( $results[ $table ] ) )
		{
			$results[ $table ] = array();
			$q = 'desc ' . $table;
			$r = db_query( $q , 'Error Describing ' . $table );
			while( $row = mysql_fetch_array( $r ) )
				$results[ $table ][] = $row[ 'Field' ];
		}
		return $results[ $table ];
	} // }}}

	//------------------------------------------------
	//	entity retrieval
	//------------------------------------------------
	
	function get_entity_by_id_object( $id ) // {{{
	{
		$dbq = new DBSelector;

		$tables = get_entity_tables_by_id( $id );
		if ( $tables )
		{
			foreach( $tables as $table )
			{
				$dbq->add_field( $table,'*' );
				$dbq->add_table( $table );
				$dbq->add_relation( 'entity.id = '.$table.'.id' );
			}
		}

		$dbq->add_relation( 'entity.id = '.$id );

		return $dbq;
	} // }}}
	function get_entity_by_id( $id, $cache = true ) // {{{
	{
		static $retrieved;
		if( !isset( $retrieved ) OR empty( $retrieved ) )
			$retrieved = array();
		if( !$cache OR !isset( $retrieved[ $id ] ) OR !$retrieved[ $id ] )
		{
			$q = get_entity_by_id_object( $id );

			$r = db_query( $q->get_query(), 'Unable to grab entity' );
			$result = array();
			while( $row = mysql_fetch_array( $r, MYSQL_ASSOC ) )
				$result = $row;
			mysql_free_result( $r );

			$retrieved[ $id ] = $result;
			return $result;
		}
		else
			return $retrieved[ $id ];
	} // }}}
	
	/**
	 * Set up a dbselector object for grabbing Reason entities
	 *
	 * @param integer $type the id of the type to grab
	 * @param mixed $site_id an site id (integer) or an array of site ids
	 * @param string $sharing either "owns", "borrows", "owns borrows", or ""
	 * @param array $table_mod tables to be included or excluded
	 * @param string $table_action either "include" or "exclude" -- specifies what to do with tables given in $table_mod
	 */
	function get_entities_by_type_object( $type, $site_id = '' , $sharing = 'owns', $table_mod = array(), $table_action = '' ) // {{{
	{
		$type = (integer) $type;
		$dbq = new DBSelector;
		if ($table_action == 'include') $tables = $table_mod;
		else $tables = get_entity_tables_by_type( $type );
		if ($table_action == 'exclude') $tables = array_diff($tables, $table_mod);
		
		foreach( $tables as $table )
		{
			$dbq->add_field( $table,'*' );
			$dbq->add_table( $table );
			// don't match entity table against itself
			if( $table != 'entity' )
				$dbq->add_relation( 'entity.id = '.$table.'.id' );
		}
		
		$dbq->add_relation( 'entity.type = '.$type );

		if( $site_id && $sharing )
		{
			$dbq->add_table( 'r','relationship' );
			$dbq->add_table( 'ar','allowable_relationship' );
			if(is_array($site_id))
				$dbq->add_relation( 'r.entity_a IN ("'.implode('","',$site_id).'")');
			else
				$dbq->add_relation( 'r.entity_a = "'.$site_id.'"');
			$dbq->add_relation( 'r.entity_b = entity.id');
			$dbq->add_relation( 'r.type = ar.id' );
			if( preg_match( '/owns/' , $sharing ) && preg_match( '/borrows/' , $sharing ) )
				$dbq->add_relation( '(ar.name = "owns" OR ar.name = "borrows")' );
			elseif( preg_match( '/borrows/' , $sharing ) )
				$dbq->add_relation( 'ar.name = "borrows"' );
			else $dbq->add_relation( 'ar.name = "owns"' );
		}

		return $dbq;
	} // }}}
	function get_entities_by_type( $type, $site_id = '' ) // {{{
	{
		$dbq = get_entities_by_type_object( $type, $site_id );

		$r = db_query( $dbq->get_query(), 'Unable to grab entities' );
		while( $row = mysql_fetch_array( $r, MYSQL_ASSOC ) )
			$results[ $row['id'] ] = $row;
		mysql_free_result( $r );
		return $results;
	} // }}}

	function get_entities_by_type_name_object( $type_name, $site_id = '' ) // {{{
	{
		return get_entities_by_type_object( get_type_id_from_name( $type_name, $site_id ) );
	} // }}}
	function get_entities_by_type_name( $type_name, $site_id = '' ) // {{{
	{
		return get_entities_by_type( get_type_id_from_name( $type_name, $site_id = '' ) );
	} // }}}

	function get_fields_by_type( $type , $full_field = false ) // {{{
	{
		$tables = get_entity_tables_by_type( $type );
		$fields = array();
		foreach( $tables as $value )
		{
			$r = db_query( 'desc ' . $value , 'Unable to select fields' );
			while( $row = mysql_fetch_array( $r , MYSQL_ASSOC ) )
			{
				if( $full_field )
					$fields[ $row[ 'Field' ] ] = $row;
				else
					$fields[ $row[ 'Field' ] ] = $row[ 'Field' ];
			}
			mysql_free_result( $r );
		}
		return $fields;
	} // }}}
	
	//------------------------------------------------
	//	association retrieval
	//------------------------------------------------

	function get_association_types_by_left_type_object( $type ) // {{{
	// retrieves allowable relationships where relationship_a = $type (the left entity type)
	{
		$q = new DBSelector;

		$q->add_table( 'ar','allowable_relationship' );
		$q->add_relation( 'ar.relationship_a = '.$type );

		return $q;
	} // }}}
	function get_association_types_by_left_type( $type ) // {{{
	{
		$q = get_association_types_by_left_type_object( $type );

		$r = db_query( $q->get_query(), 'Unable to get association types by left type' );
		while( $row = mysql_fetch_array( $r, MYSQL_ASSOC ) )
			$res[ $row['id'] ] = $row;
		return $res;
	} // }}}

	function get_associations_by_type_object( $type_a, $type_b ) // {{{
	/*	gets all associations where entity_a's content_type_id = $type_a and entity_b's ctid = $type_b
	 *	returns a list of entity ids 
	 */
	{
		$dbq = new DBSelector;

		$dbq->add_field( 'r', 'entity_a' );
		$dbq->add_field( 'r', 'entity_b' );
		$dbq->add_table( 'r', 'relationship' );
		$dbq->add_table( 'e1', 'entity' );
		$dbq->add_table( 'e2', 'entity' );
		$dbq->add_relation( 'e1.id = r.entity_a' );
		$dbq->add_relation( 'e1.type = '.$type_a );
		$dbq->add_relation( 'e2.id = r.entity_b' );
		$dbq->add_relation( 'e2.type = '.$type_b );

		return $dbq;
	} // }}}
	function get_associations_by_type( $type_a, $type_b ) // {{{
	{
		$dbq = get_associations_by_type_object( $type_a, $type_b );

		$r = db_query( $dbq->get_query(), 'Unable to retrieve associations' );
		while( $row = mysql_fetch_array( $r, MYSQL_ASSOC ) )
			$results[ $row['entity_a'] ] = $row[ 'entity_b' ];
		mysql_free_result( $r );
		return $results;
	} // }}}

	function get_entity_associations_object( $id ) // {{{
	{
		$dbq = new DBSelector;

		$dbq->add_table( 'r','relationship' );
		$dbq->add_field( 'r','entity_b' );
		$dbq->add_relation( 'r.entity_a = '.$id );

		return $dbq;
	} // }}}
	function get_entity_associations( $id ) // {{{
	{
		$dbq = get_entity_associations_object( $id );
		
		$r = db_query( $dbq->get_query(), 'Unable to retrieve associations for this entity' );
		while( $row = mysql_fetch_array( $r, MYSQL_ASSOC ) )
			$res[ $row['entity_b'] ] = $row['entity_b'];
		mysql_free_result( $r );
		return $res;
	} // }}}

	function get_entity_associations_by_type_name_object( $relation_type ) // {{{
	{
		$dbq = new DBSelector;

		$dbq->add_table( 'r','relationship' );
		$dbq->add_table( 'ar','allowable_relationship' );
		$dbq->add_field( 'r','entity_a' );
		$dbq->add_field( 'r','entity_b' );
		$dbq->add_relation( 'r.type = ar.id' );
		$dbq->add_relation( 'ar.name = "'.$relation_type.'"' );

		return $dbq;
	} // }}}
	function get_entity_associations_by_type_name( $relation_type ) // {{{
	{
		$dbq = get_entity_associations_by_type_name_object( $relation_type );
		
		$res = array();
		$r = db_query( $dbq->get_query(), 'Unable to retrieve associations for this type.' );
		while( $row = mysql_fetch_array( $r, MYSQL_ASSOC ) )
			$res[ $row['entity_a'] ] = $row['entity_b'];
		mysql_free_result( $r );
		return $res;
	} // }}}

	function get_entity_associations_by_type_object( $relation_id ) // {{{
	{
		$dbq = new DBSelector;

		$dbq->add_table( 'r','relationship' );
		$dbq->add_table( 'ar','allowable_relationship' );
		$dbq->add_field( 'r','entity_a' );
		$dbq->add_field( 'r','entity_b' );
		$dbq->add_relation( 'r.type = ar.id' );
		$dbq->add_relation( 'ar.id = "'.$relation_id.'"' );

		return $dbq;
	} // }}}

	//------------------------------------------------
	//	more specific functions
	//------------------------------------------------

	function auth_site_to_user( $site_id, $user_id ) // {{{
	{
		$d = get_entity_associations_by_type_name_object( 'site_to_user' );
		$d->add_table( 'user','entity' );
		$d->add_relation( 'user.name = "'.$user_id.'"' );
		$d->add_relation( 'user.id = r.entity_b' );
		$d->add_relation( 'r.entity_a = "'.$site_id.'"' );

		if ( !count( $d->run() ) )
			die( 'You are not authorized to use this page' );
	} // }}}

	function auth_site_to_type( $site_id, $type_id ) // {{{
	{
		$d = get_entity_associations_by_type_name_object( 'site_to_type' );
		$d->add_relation( 'r.entity_a = '.$site_id );
		$d->add_relation( 'r.entity_b = '.$type_id );

		if ( !$d->run() )
			die( 'This site does not have access to that content type' );
	} // }}}

	function site_owns_entity( $site_id, $entity_id ) // {{{
	{
		$d = get_entity_by_id_object( $entity_id );
		$d->add_table( 'ar' , 'allowable_relationship' );
		$d->add_table( 'r' , 'relationship' );

		$d->add_relation( 'ar.id = r.type' );
		$d->add_relation( 'ar.name = "owns"' );
		$d->add_relation( 'r.entity_a = ' . $site_id );
		$d->add_relation( 'r.entity_b = ' . $entity_id );
		$r = db_query( $d->get_query() , 'Error checking ownership' );
		if( $row = mysql_fetch_array( $r , MYSQL_ASSOC ) )
		{
			mysql_free_result( $r );
			return true;
		}
		else
		{
			mysql_free_result( $r );
			return false;
		}
	} // }}}

	function site_borrows_entity( $site_id, $entity_id ) // {{{
	{
		$d = get_entity_by_id_object( $entity_id );
		$d->add_table( 'ar' , 'allowable_relationship' );
		$d->add_table( 'r' , 'relationship' );

		$d->add_relation( 'ar.id = r.type' );
		$d->add_relation( 'ar.name = "borrows"' );
		$d->add_relation( 'r.entity_a = ' . $site_id );
		$d->add_relation( 'r.entity_b = ' . $entity_id );
		$r = db_query( $d->get_query() , 'Error checking ownership' );
		if( $row = mysql_fetch_array( $r , MYSQL_ASSOC ) )
		{
			mysql_free_result( $r );
			return true;
		}
		else
		{
			mysql_free_result( $r );
			return false;
		}
	} // }}}
	
	function get_sites_that_are_borrowing_entity($entity_id)
	{
		$d = get_entity_associations_by_type_name_object( 'borrows' );
		//$d->add_relation( 'r.entity_a = '.$site_id );
		$d->add_relation( 'r.entity_b = '.$entity_id );
		$r = db_query( $d->get_query() , 'Error checking borrowing' );
		$sites = array();
		while( $row = mysql_fetch_array( $r, MYSQL_ASSOC ) )
		{
			$sites[ $row['entity_a'] ] = new entity($row['entity_a']);
		}
		return $sites;
	}

	function get_user_id( $username ) // {{{
	{
		static $users = array();
		
		if(!empty($users[$username]))
		{
			return $users[$username];
		}
		else
		{
			$dbq = new DBSelector;
		
			// select user.name where user.type = user
			$dbq->add_table( 'user','entity' );
			$dbq->add_table( 'type','entity' );
			$dbq->add_field( 'user','id' );
			$dbq->add_relation( 'user.name = "'.addslashes($username).'"' );
			$dbq->add_relation( 'type.unique_name = "user"' );
			$dbq->add_relation( 'type.id = user.type' );
			$dbq->add_relation( 'user.state = "Live"' );
			$dbq->set_num(1);
	
			// get the result
			$res = $dbq->run();
			// get the first result
			$res = current( $res );
			
			$users[$username] = $res[ 'id' ];
			// return the id
			return $res[ 'id' ];
		}
	} // }}}

	/**
	* Determines if a given reason user has a given role
	*
	* Note: This function is fairly slow (requires a potentially poky db hit on every call).  If you must use it, store its results rather than asking again. Better yet, use reason_user_has_privs().
	*
	* @deprecated Use reason_user_has_privs(), for performance reasons and to allow extensibility of user roles
	* @param integer $user_id
	* @param integer $role_id
	*/
	function user_is_a( $user_id, $role_id ) // {{{
	{
		$user = new entity( $user_id );
		if( $user->has_left_relation_with_entity( new entity( $role_id ), relationship_id_of( 'user_to_user_role' ) ) )
			return true;
		else
			return false;
	} // }}}
	
	/**
	 * Determines if a given reason user has a given privilege
	 *
	 * Note: This function is *fast*. There's no need to carefully store and pass around its results -- just call it again.
	 *
	 * Privileges:
	 * <dl>
	 * <dt>add</dt><dd>The privilege to create new (pending) entities.</dd>
	 * <dt>edit_pending</dt><dd>The privilege to edit pending entities</dd>
	 * <dt>delete_pending</dt><dd>The privilege to delete pending entities (e.g. mark them as "deleted")</dd>
	 * <dt>edit</dt><dd>The privilege to edit live entities</dd>
	 * <dt>delete</dt><dd>The privilege to delete live entities (e.g. mark them as "deleted")</dd>
	 * <dt>publish</dt><dd>The privilege to publish entities (e.g. change their state from pending to live)</dd>
	 * <dt>borrow</dt><dd>The privilege to borrow entities from other sites</dd>
	 * <dt>expunge</dt><dd>The privilege to expunge deleted entities from the Reason database (That, is remove them forever)</dd>
	 * <dt>duplicate</dt><dd>The privilege to duplicate entities (By default, limited to admins as of 3/08, as this is a experimental feature of Reason)</dd>
	 * <dt>edit_html</dt><dd>The privilege to switch between WYSIWYG view and HTML view in the HTML editor</dd>
	 * <dt>switch_theme</dt><dd>The privilege to change the site's theme (if the site's theme is not locked by an administrator)</dd>
	 * <dt>pose_as_other_user</dt><dd>The privilege to interact with the Reason edministrative interface as if they were someone else. NOTE: This is a *very* powerful privilege, as it amounts to superuser rights!</dd>
	 * <dt>assign_any_page_type</dt><dd>The privilege to choose from all Reason page types, rather than a select few</dd>
	 * <dt>edit_head_items</dt><dd>The privilege to insert arbitrary HTML into the page head (css, scripts, meta tags, etc.)</dd>
	 * <dt>edit_unique_names</dt><dd>The privilege to give Reason entities unique names. This is necessary for creating sites and types.</dd>
	 * <dt>edit_fragile_slugs</dt><dd>The privilege to modify a slug that may cause broken links if changed (e.g. publication feed URL slugs)</dd>
	 * <dt>edit_home_page_nav_link</dt><dd>The privilege to insert a custom link to site home pages in the navigation (instead of the standard "Sitename Home")</dd>
	 * <dt>edit_form_advanced_options</dt><dd>The privilege to edit advanced options in the thor form content manager</dd>
	 * <dt>manage_allowable_relationships</dt><dd>The privilege to modify, create, and delete the set of relationships can be made between Reason entities. NOTE: This is very powerful, and should only be given to highly trustworthy individuals</dd>
	 * <dt>view_sensitive_data</dt><dd>The privilege to view any data in Reason</dd>
	 * <dt>manage_integration_settings</dt><dd>The privilege to modify or override foreign keys and other values in Reason that pertain to integration with external data sources</dd>
	 * <dt>edit_raw_ldap_filters</dt><dd>The privilege to write full LDAP filters/queries (e.g. in the construction of dynamic groups)</dd>
	 * <dt>upload_full_size_image</dt><dd>The privilege to keep images from being resized upon upload, thereby retaining their original dimensions</dd>
	 * <dt>upgrade</dt><dd>The privilege to run Reason's upgrade scripts</dd>
	 * <dt>db_maintenance</dt><dd>The privilege to run standard database cleanup and sanity-checking scripts</dd>
	 * <dt>update_urls</dt><dd>The privilege to run Reason's .htaccess regeneration script</dd></dl>
	 *
	 * @param integer $user_id The Reason entity id of the user
	 * @param string $privilege
	 * @return boolean true if the user has the privilege, false if not
	 */
	function reason_user_has_privs( $user_id, $privilege /*, $site_id = NULL, $type_id = NULL, $item_id = NULL */ )
	{
		$user_id = (integer) $user_id;
		if(empty($user_id))
			return false;
		static $roles_cache = array();
		static $privs_cache = array();
		if(empty($cache[$user_id]))
		{
			reason_include_once('classes/entity_selector.php');
			$es = new entity_selector();
			$es->add_type(id_of('user_role'));
			$es->limit_tables();
			$es->limit_fields(array('unique_name'));
			$es->add_right_relationship($user_id, relationship_id_of( 'user_to_user_role' ));
			$roles_cache[$user_id] = $es->run_one();
			if(empty($roles_cache[$user_id]))
			{
				$roles_cache[$user_id][id_of('editor_user_role')] = new entity(id_of('editor_user_role'));
			}
		}
		elseif(isset($privs_cache[$user_id][$privilege]))
		{
			return $privs_cache[$user_id][$privilege];
		}
		$privs = reason_get_privs_table();
		foreach($roles_cache[$user_id] as $role)
		{
			if(isset($privs[$role->get_value('unique_name')]) && in_array($privilege,$privs[$role->get_value('unique_name')]))
			{
				$privs_cache[$user_id][$privilege] = true;
				return true;
			}
		}
		$privs_cache[$user_id][$privilege] = false;
		return false;
	}
	
	function reason_get_privs_table()
	{
		return array(
				'contribute_only_role'=>array('add','edit_pending','delete_pending',),
				'editor_user_role'=>array('add','edit_pending','delete_pending','edit','delete','publish','borrow','expunge','switch_theme',),
				'power_user_role'=>array('add','edit_pending','delete_pending','edit','delete','publish','borrow','expunge','switch_theme','edit_html','upload_full_size_image',),
				'admin_role'=>array('add','edit_pending','delete_pending','edit','delete','publish','borrow','expunge','duplicate','edit_html','switch_theme','pose_as_other_user','assign_any_page_type','edit_head_items','edit_unique_names','edit_fragile_slugs','edit_home_page_nav_link','edit_form_advanced_options','manage_allowable_relationships','view_sensitive_data','manage_integration_settings','edit_raw_ldap_filters','upload_full_size_image','upgrade','db_maintenance','update_urls',),
		);
	}

	function get_owner_site_id( $entity_id ) //{{{
	{
		$d = get_entity_by_id_object( $entity_id );
		$d->add_field( 'r', 'entity_a', 'site_id' );
		$d->add_table( 'ar' , 'allowable_relationship' );
		$d->add_table( 'r' , 'relationship' );

		$d->add_relation( 'ar.id = r.type' );
		$d->add_relation( 'ar.name = "owns"' );
		$d->add_relation( 'r.entity_b = ' . $entity_id );
		$r = db_query( $d->get_query() , 'Error getting owning site ID.' );
		if( $row = mysql_fetch_array( $r , MYSQL_ASSOC ) )
		{
			return $row['site_id'];
		}
		else
			return false;
	} // }}}
	
	/**
	 * A simple helper function for getting the content of a uniquely named text blurb
	 *
	 * @param string $unique_name The unique name of the text blurb you want to get the contents of
	 * @param boolean $cache	Use the id_of function's cache or not
	 * @param boolean $report_not_found_error	Trigger an error if the unique name is not in the Reason database
	 * @return mixed	String if success; otherwise NULL
	 * @todo In a future version: return NULL if entity is not a text blurb
	 */
	function get_text_blurb_content( $unique_name, $cache = true, $report_not_found_error = true ) //{{{
	{
		$id = id_of( $unique_name, $cache, $report_not_found_error );
		if(!empty($id))
		{
			$e = new entity( $id );
			if($e->get_value('type') == id_of('text_blurb'))
			{
				return $e->get_value( 'content' );
			}
			else
			{
				trigger_error($unique_name.' is not a text blurb. Use of get_text_blurb_content() for non-blurb entities is deprecated.');
				return $e->get_value( 'content' );
			}
		}
		return;
	} // }}}

	// Like php basename, but returns partial path from the modules directory
	// When used in place of basename at the top of a module, prevents breakage of 
	// modules when a name change occurs for a directory of modules
	// nwhite 10-04-2006
	function module_basename( $full_path, $suffix = '.php', $module_dir = '/modules/' )
	{
		$module_strlength = strlen($module_dir);
		$module_strpos = strpos($full_path, $module_dir);
		if (is_numeric($module_strpos)) // found the string
		{
			$partial_path = substr($full_path, $module_strpos + $module_strlength);
			$filebasename = basename($partial_path, $suffix);
			$dirname = dirname($partial_path);
			return $dirname . '/' . $filebasename;			
		}
		else
		{
			trigger_error('The module directory ' . $module_dir . ' was not found in the full path string ' . $full_path . ' - returning just the file basename');
			return basename($full_path, $suffix);
		}
	}

	function clean_vars( &$vars, $rules )
	{
		$call_info = array_shift( debug_backtrace() );
		$code_line = $call_info['line'];
		$file = array_pop( explode('/', $call_info['file']));
		trigger_error('deprecated function clean_vars called by ' . $file . ' on line ' . $code_line . ' - use carl_clean_vars instead', WARNING);
		return carl_clean_vars( $vars, $rules );
	}
	
	/* **** Some helpful queries **** {{{

		>> show all relationships, substitute ids in relationship to the names in entity
		SELECT r.id,e1.name, e2.name FROM entity AS e1, entity AS e2, relationship AS r WHERE r.entity_a = e1.id AND r.entity_b = e2.id;


	 }}} */
	
	/**
	 * Determines if a site shares a type
	 * @param integer $site_id the id of the site
	 * @param integer $type_id the id of the type
	 * @return bool $site_shares_type true if site shares type, false if it does not
	 */
	function site_shares_type($site_id, $type_id)
	{
		static $retrieved;
		if (!isset($retrieved[$site_id][$type_id]))
		{
			$dbq = new DBSelector();
			$dbq->add_table( 'ar','allowable_relationship' );
			$dbq->add_table( 'r', 'relationship' );	

			$dbq->add_relation( 'ar.name = "site_shares_type"' );
			$dbq->add_relation( 'r.type = ar.id' );

			$dbq->add_relation( 'r.entity_a = '.$site_id );
			$dbq->add_relation( 'r.entity_b = '.$type_id );
	
			$q = $dbq->get_query();
			$r = db_query( $q, 'Failed determination of site\'s sharing status.' );
			if( mysql_num_rows( $r ) > 0 )
			{
				$retrieved[$site_id][$type_id] = true;
			}
			else
			{
				$retrieved[$site_id][$type_id] = false;
			}
		}
		return $retrieved[$site_id][$type_id];
	}
	
	
	/**
	 * Determines the HTML editor for a particular site
	 * @param integer $site_id the id of the site
	 * @return string $html_editor_name name of the html editor used by the site	 
	 */
	function html_editor_name($site_id)
	{
		$obj = get_html_editor_integration_object($site_id);
		return $obj->get_plasmature_type();
	}
	
	/**
	 * Returns params particular to the editor assigned to a particular site
	 * @param integer $site_id the id of the site
	 * @param integer $user_id the id of the user; can be 0 to indicate anonymous usage
	 * @return array $params params ready to be passed to the plasmature element
	 */
	function html_editor_params($site_id,$user_id=0)
	{
		$obj = get_html_editor_integration_object($site_id);
		return $obj->get_plasmature_element_parameters($site_id, $user_id);
	}
	function html_editor_options($site_id)
	{
		$obj = get_html_editor_integration_object($site_id);
		return $obj->get_configuration_options();
	}
	
	function get_html_editor_integration_object($site_id)
	{
		static $editor_objects = array();
		if( empty( $editor_objects[$site_id] ) )
		{
			$es = new entity_selector();
			$es->add_type(id_of('html_editor_type'));
			$es->add_right_relationship($site_id,relationship_id_of('site_to_html_editor'));
			$es->set_num(1);
			$editors = $es->run_one();
			
			$html_editor_filename = defined('REASON_DEFAULT_HTML_EDITOR') ? REASON_DEFAULT_HTML_EDITOR : 'base.php';
			if(!empty($editors))
			{
				$editor = current($editors);
				if($editor->get_value('html_editor_filename'))
				{
					$html_editor_filename = $editor->get_value('html_editor_filename');
				}
			}
			if(!strpos($html_editor_filename, '.php'))
			{
				$html_editor_filename .= '.php';
			}
			
			if(reason_file_exists('html_editors/'.$html_editor_filename))
			{
				reason_include_once('html_editors/'.$html_editor_filename);
				if(!empty($GLOBALS[ '_reason_editor_integration_classes' ][ $html_editor_filename ]))
				{
					if(class_exists($GLOBALS[ '_reason_editor_integration_classes' ][ $html_editor_filename ]))
					{
						$editor_objects[$site_id] = new $GLOBALS[ '_reason_editor_integration_classes' ][ $html_editor_filename ];
					}
					else
					{
						trigger_error('The class '.$GLOBALS[ '_reason_editor_integration_classes' ][ $html_editor_filename ].' does not exist; using default editor (plain text)');
					}
				}
				else
				{
					trigger_error('The file html_editors/'.$html_editor_filename.' is not recording its class name in $GLOBALS[ \'_reason_editor_integration_classes\' ]. Using default editor (plain text)');
				}
			}
			else
			{
				trigger_error('The file html_editors/'.$html_editor_filename.' does not exist. Using default editor (plain text)');
			}
			if(!isset($editor_objects[$site_id]))
			{
				reason_include_once('html_editors/base.php');
				$editor_objects[$site_id] = new $GLOBALS[ '_reason_editor_integration_classes' ][ 'base.php' ];
			}
		}
		return $editor_objects[$site_id];
	}
	
	function make_empty_array($site_id = 0, $user_id = 0)
	{
		return array();
	}
	
	/**
	 * Works like implode(), but only includes an element if it is not empty.
	 */
	function implode_non_empty($separator, $array)
	{
		$return = '';
		$fired = false;
		
		if (!is_array($array) && func_num_args() >= 2) {
			$array = func_get_args();
			unset($array[0]);
		}
		
		foreach ((array) $array as $el) {
			if (empty($el) && ($el !== (int) $el))
				continue;
			if ($fired)
				$return .= $separator;
			else
				$fired = true;
			$return .= $el;
		}
		
		return $return;
	}
	
	/**
	 * Find out what page types use the a given module
	 *
	 * @param string $module_name
	 * @return array $page_types
	 */
	function page_types_that_use_module($module_name)
	{
		static $modules_to_page_types = array();
		if(empty($modules_to_page_types))
		{
			reason_include_once('minisite_templates/page_types.php');
			foreach($GLOBALS['_reason_page_types'] as $page_type => $type )
			{
				if( $page_type != 'default' )
				{
					$type = array_merge( $GLOBALS['_reason_page_types'][ 'default' ], $type  );
				}
				foreach( $type AS $section => $module_info )
				{
					$module = is_array( $module_info ) ? $module_info[ 'module' ] : $module_info;
					$modules_to_page_types[$module][] = $page_type;
				}
			}
		}
		if(array_key_exists($module_name,$modules_to_page_types))
		{
			return $modules_to_page_types[$module_name];
		}
		return array();
	}
	/**
	 * Find a template entity given a template name
	 *
	 * @param string $name The name of the template
	 * @return mixed template entity if found; false if not found
	 * @author Matt Ryan
	 * @since Reason 4.0 beta 4
	 */
	function get_template_by_name($name)
	{
		static $retrieved = array();
		if(!isset($retrieved[$name]))
		{
			$es = new entity_selector();
			$es->add_type(id_of('minisite_template'));
			$es->add_relation('entity.name = "'.addslashes($name).'"');
			$es->set_num(1);
			$templates = $es->run_one();
			if(!empty($templates))
				$retrieved[$name] = current($templates);
			else
				$retrieved[$name] = false;
		}
		return $retrieved[$name];
	}
	
	/**
	 * Determine if a given user can edit a given site
	 *
	 * Note: this function takes a *Reason* ID -- other implementations generally take a username/netid
	 *
	 * @param integer $user_id The Reason entity ID of the user
	 * @param integer $site_id The Reason entity ID of the site
	 * @param boolean $use_cache Defaults to true; set to false if your script previously changed this value and you need to know what the new value will be
	 * @return boolean true if user can edit site, false if not
	 */
	function user_can_edit_site($user_id, $site_id, $use_cache = true)
	{
		static $cache = array();
		if(!isset($cache[$user_id]))
			$cache[$user_id] = array();
		if(!$use_cache || !isset($cache[$user_id][$site_id]))
		{
			$es = new entity_selector();
			$es->add_type(id_of('user'));
			$es->add_right_relationship($site_id,relationship_id_of('site_to_user'));
			$es->add_relation('entity.id = "'.$user_id.'"');
			$es->set_num(1);
			$es->limit_tables();
			$es->limit_fields();
			$users = $es->run_one();
			if(empty($users))
				$cache[$user_id][$site_id] = false;
			else
				$cache[$user_id][$site_id] = true;
		}
		return $cache[$user_id][$site_id];
	}
	
	/**
	 * Convert special characters into HTML/XHTML/XML entities, but don't double-encode
	 *
	 * This function is necessary because Tidy will convert some -- but not all -- characters into their entities (quotes remain simple quotes, for example)
	 *
	 * As of php 5.2 it might be possible to use the 4th parameter of htmlspeciachars
	 *
	 * @param string $string
	 * @return string encoded string
	 */
	function reason_htmlspecialchars($string)
	{
		$string = str_replace(array('&amp;','&gt;','&lt;','&quot;','&#039;'),array('&','>','<','"',"'"),$string);
		return htmlspecialchars($string, ENT_QUOTES, 'UTF-8' );
	}
	
	/**
	 * Log the amount of time it took to generate the current page/process
	 *
	 * @param integer $milliseconds The number of milliseconds that it took to generate the page
	 * @return boolean success
	 */
	function reason_log_page_generation_time($milliseconds)
	{
		if(defined('REASON_PERFORMANCE_PROFILE_LOG') && REASON_PERFORMANCE_PROFILE_LOG)
		{
			$pieces = array( carl_date('r'), get_current_url(), $milliseconds );
			array_walk( $pieces, 'quote_walk' );
			$msg = implode( ',', $pieces );
			return dlog( $msg, REASON_PERFORMANCE_PROFILE_LOG );
		}
		return false;
	}
	
	/**
	 * Turn an array of unique names into an array of entities
	 *
	 * The returned array is keyed on entity id.
	 *
	 * The returned array will be smaller than the array given if any of the unique names aren't
	 * found in the database.
	 *
	 * @param array $unique_names
	 * @return array Reason entities
	 */
	function reason_unique_names_to_entities($unique_names)
	{
		$entities = array();
		foreach($unique_names as $unique_name)
		{
			if($id = id_of($unique_name) )
			{
				$entities[$id] = new entity($id);
			}
		}
		return $entities;
	}
	
	/**
	 * Get a list of tables that are not normal entity tables, and which should not be treated as such
	 *
	 * @return array
	 */
	function reason_get_protected_tables()
	{
		return array('allowable_relationship','entity','page_cache_log','page_cache_log_archive','relationship','URL_history',);
	}
	
	/**
	 * Checks whether or not something is an entity.
	 *
	 * Specifically, this checks if an item is an object and has a method called entity (the entity class constructor). In addition, you
	 * can perform extra checks to see if the entity has a type value (any value or a specific value).
	 *
	 * There are better ways to do this in php 5, but to maintain cross compatibility with php 4 and php 5 instanceof and is_a are not reliable,
	 * and so we do a method_exists check to see if the constructor "entity" is defined.
	 *
	 * Please note this in its default behavior this just checks to see if an item is of class entity - that does not mean it has a type or
	 * or exists in the database.
	 * 
	 * @param object
	 * @param mixed of_type - if "true" makes sure the entity has some value for type, if given a unique name, makes sure the entity is of that type.
	 * @return boolean
	 */
	function reason_is_entity($obj, $of_type = false)
	{
		$is_entity = (is_object($obj) && method_exists($obj, "entity"));
		if ($is_entity && ($of_type === true))
		{
			return $obj->has_value('type') ? ($obj->get_value('type')) : false;
		}
		elseif ($is_entity && !empty($of_type) && reason_unique_name_exists($of_type))
		{
			return $obj->has_value('type') ? ($obj->get_value('type') == id_of($of_type)) : false;
		}
		return $is_entity;
	}
	
	/**
	 * In a multidomain reason install the web path for a site might not be the web path for the current domain.
	 *
	 * This function returns the correct webpath for a given reason site or false if a site with a custom domain setting is not defined in domain_settings.php
	 *
	 * @author Nathan White
	 * @return string absolute file system directory that is the web root for a site
	 */
	function reason_get_site_web_path($site_id_or_entity)
	{
		$site = (is_numeric($site_id_or_entity)) ? new entity($site_id_or_entity) : $site_id_or_entity;
		if (reason_is_entity($site, 'site'))
		{
			$domain = $site->get_value('domain');
			if (!empty($domain) && isset($GLOBALS['_reason_domain_settings'][$domain]['WEB_PATH']))
			{
				return $GLOBALS['_reason_domain_settings'][$domain]['WEB_PATH'];
			}
			elseif (!empty($domain))
			{
				trigger_error('reason_get_site_web_path called on site id ' . $site->id() . ' with domain value ' . $domain . ' that is not defined in domain_settings.php');
				return false;
			}
			return reason_get_default_web_path();
		}
		else
		{
			trigger_error('reason_get_site_web_path passed a value that is not a site_id or site entity');
			return false;
		}
	}
	/**
	 * Get the url of an icon for a given type
	 *
	 * Note that this only supports .png icons at the moment.
	 * If someone wants to use other file types, feel free to add support and contribute back. :)
	 *
	 * @param mixed $type id, unique name, or entity (entity will offer best performance in tight loops)
	 * @param boolean $use_default Provide the url of a default icon if none available
	 * @return string URL of the icon (html encoded), or an empty string if none found and $use_default set to false
	 * @todo add support for image types other than .png (will need some sort of hierarchy of formats)
	 */
	function reason_get_type_icon_url($type,$use_default = true)
	{
		if(!is_object($type))
		{
			if(is_numeric($type))
				$type = new entity($type);
			elseif(is_string($type))
				$type = new entity(id_of($type));
			else
			{
				trigger_error('$type not an object, integer, or string');
				$type = NULL;
			}
		}
		if(isset($type) && $type->get_values() && $type->get_value('unique_name') && (file_exists(REASON_INC.'www/ui_images/types/'.$type->get_value('unique_name').'.png') || file_exists(REASON_INC.'www/local/ui_images/types/'.$type->get_value('unique_name').'.png') ) )
		{
			return REASON_HTTP_BASE_PATH.'ui_images/types/'.reason_htmlspecialchars($type->get_value('unique_name')).'.png';
		}
		elseif($use_default)
		{
			return REASON_HTTP_BASE_PATH.'ui_images/types/default.png';
		}
		else
		{
			return '';
		}
		
	}
	
	/**
	 * Returns the default webpath when the setting is not derived from a domain defined in domain_settings.php
	 */
	function reason_get_default_web_path()
	{
		if (isset($GLOBALS['_default_domain_settings']['WEB_PATH']))
		{
			return $GLOBALS['_default_domain_settings']['WEB_PATH'];
		}
		else
		{
			trigger_error('A default domain setting is not defined for this Reason instance. Please update package_settings.php to use domain_define 
						  instead of define when setting the WEB_PATH constant.');
			return WEB_PATH;
		}
	}
?>
