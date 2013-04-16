<?php
namespace Habari;
/**
	* LinkHandler class
	* Produces link specific RSS2 and Atom feeds
	*
	* @package Habari
	*
	* @version $Id$
	* @copyright 2008
 */

class LinkHandler extends AtomHandler
{

	public static $feeds= array(
		'all',
		'entries',
		'links'
		);

	private $current_url = '';

	/**
	* Respond to requests for podcast feeds
	*
	*/
	public function act_feed()
	{
		// Expecting: entire_match, name in handler_vars
		$this->current_url = Site::get_url( 'habari' ) . '/' . $this->handler_vars['entire_match'];
		
		$params= array();
		switch($this->handler_vars['name']) {
			case 'entries':
				parent::act_collection();
				exit;
			case 'links':
				$params['content_type']= array(
					Post::type( 'link' ),
				);
				break;
			case 'all':
				$params['content_type']= array(
					Post::type( 'link' ),
					Post::type( 'entry' )
				);
				break;
			default:
				$params['content_type']= array(
					Post::type( $this->handler_vars['name'] )
				);
				break;
		}
		
		parent::get_collection($params);
		
		exit;
	}

}
?>