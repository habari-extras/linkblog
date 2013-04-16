<?php
namespace Habari;
/**
	* Linkdatabase
	* Interacts with the database for links
	*
	* @package Habari
	*
	* @version $Id$
	* @copyright 2008
 */

class Traffum extends QueryRecord
{
	// our definitions for traffum types
	const TYPE_SEND_NORMAL = 1;
	const TYPE_SEND_ATOM = 2;
	const TYPE_VIEW_NORMAL = 3;
	const TYPE_VIEW_ATOM = 4;
	
	/**
	* static function default_fields
	* Returns the defined database columns for a traffum
	**/
	public static function default_fields()
	{
		return array(
			'id' => 0,
			'post_id' => 0,
			'date' => DateTime::date_create(),
			'type' => self::TYPE_VIEW_NORMAL,
			'ip' => sprintf("%u", ip2long( $_SERVER['REMOTE_ADDR'] ) ),
			'referrer' => $_SERVER['HTTP_REFERER']
		);
	}

	/**
	 * constructor __construct
	 * Constructor for the Traffum class.
	 * @param array an associative array of initial Traffum field values.
	 **/
	public function __construct( $paramarray = array() )
	{
		// Defaults
		$this->fields = array_merge( self::default_fields(), $this->fields );
		parent::__construct( $paramarray );
		$this->exclude_fields('id');
		
	}

	/**
	 * static function get
	 * Returns a single traffum, by ID
	 *
	 * @param int An ID
	 * @return array A single Traffum object
	 **/
	static function get( $ID = 0 )
	{
		if ( ! $ID ) {
			return false;
		}
		return DB::get_row( 'SELECT * FROM {link_traffic} WHERE id = ?', array( $ID ), 'Traffum' );
	}

	/**
	 * static function create
	 * Creates a traffum and saves it
	 * @param array An associative array of traffum fields
	 * $return Traffum The traffum object that was created
	 **/
	static function create($paramarray)
	{
		$traffum = new Traffum($paramarray);
		$traffum->insert();
		return $traffum;
	}

	/**
	 * function insert
	 * Saves a new comment to the posts table
	 */
	public function insert()
	{
		$result = parent::insertRecord( DB::table('link_traffic') );
		$this->newfields['id'] = DB::last_insert_id(); // Make sure the id is set in the comment object to match the row id
		$this->fields = array_merge($this->fields, $this->newfields);
		$this->newfields = array();
		return $result;
	}

	/**
	 * function update
	 * Updates an existing comment in the posts table
	 */
	public function update()
	{
		$result = parent::updateRecord( DB::table('link_traffic'), array('id'=>$this->id) );
		$this->fields = array_merge($this->fields, $this->newfields);
		$this->newfields = array();
		return $result;
	}

	/**
	 * function delete
	 * Deletes this comment
	 */
	public function delete()
	{
		return parent::deleteRecord( DB::table('link_traffic'), array('id'=>$this->id) );
	}

	/**
	 * function __get
	 * Overrides QueryRecord __get to implement custom object properties
	 * @param string Name of property to return
	 * @return mixed The requested field value
	 **/
	public function __get( $name )
	{
		$fieldnames = array_merge( array_keys( $this->fields ), array('post', 'info' ) );
		if( !in_array( $name, $fieldnames ) && strpos( $name, '_' ) !== false ) {
			preg_match('/^(.*)_([^_]+)$/', $name, $matches);
			list( $junk, $name, $filter ) = $matches;
		}
		else {
			$filter = false;
		}

		switch($name)
		{
			default:
				$out = parent::__get( $name );
				break;
		}
		return $out;
	}

	/**
	 * function __set
	 * Overrides QueryRecord __set to implement custom object properties
	 * @param string Name of property to return
	 * @return mixed The requested field value
	 **/
	public function __set( $name, $value )
	{
		return parent::__set( $name, $value );
	}

}

?>