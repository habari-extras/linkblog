<?php

require('linkhandler.php');
require('linkdatabase.php');

class LinkBlog extends Plugin
{
	/**
	 * Show options on plugins admin page
	 */
	public function configure()
	{
		$ui = new FormUI( strtolower( get_class( $this ) ) );
		$ui->append( 'textarea', 'original_text', 'linkblog__original', _t('Text to use for describing original in feeds:') );
			$ui->original_text->rows = 2;
		$ui->append( 'checkbox', 'atom_permalink', 'linkblog__atom_permalink', _t('Override atom permalink with link URL') );
		$ui->append( 'submit', 'save', _t('Save') );
		return $ui;
	}

	/**
	 * Register content type
	 */
	public function action_plugin_activation( $plugin_file )
	{
		self::install();
	}

	public function action_plugin_deactivation( $plugin_file )
	{
		Post::deactivate_post_type( 'link' );
	}

	/**
	 * install various stuff we need
	 */
	static public function install() {
		Post::add_new_type( 'link' );

		// Give anonymous users access
		$group = UserGroup::get_by_name('anonymous');
		$group->grant('post_link', 'read');

		// Set default settings
		Options::set('linkblog__original', '<p><a href="{permalink}">Permalink</a></p>');
		Options::set('linkblog__atom_permalink', false);

		self::database();
	}

	/**
	 * install database
	 */
	static public function database() {
		
		// $schema = Config::get('db_connection');
		list( $schema, $remainder )= explode( ':', Config::get( 'db_connection' )->connection_string );
		
		switch( $schema )
		{
			case 'sqlite':
				$q = 'CREATE TABLE IF NOT EXISTS ' . DB::table('link_traffic') . '(
				  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
				  post_id INTEGER NOT NULL,
				  date INTEGER NOT NULL,
				  type SMALLINTEGER NOT NULL,
				  ip INTEGER default NULL,
				  referrer VARCHAR(255) default NULL
				);';
				break;
				
			case 'mysql':
			default:
				$q = 'CREATE TABLE IF NOT EXISTS ' . DB::table('link_traffic') . '(
				  `id` int(10) unsigned NOT NULL auto_increment,
				  `post_id` int(10) unsigned NOT NULL,
				  `date` int(10) unsigned NOT NULL,
				  `type` int(5) unsigned NOT NULL,
				  `ip` int(10) unsigned default NULL,
				  `referrer` varchar(255) default NULL,
				  PRIMARY KEY  (`id`)
				);';
				
		}
		
		// Utils::debug( $schema, $q );
		
		// CREATE TABLE {$prefix}posts (
		// 		  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
		// 		  slug VARCHAR(255) NOT NULL,
		// 		  content_type SMALLINTEGER NOT NULL,
		// 		  title VARCHAR(255) NOT NULL,
		// 		  guid VARCHAR(255) NOT NULL,
		// 		  content TEXT NOT NULL,
		// 		  cached_content LONGTEXT NOT NULL,
		// 		  user_id SMALLINTEGER NOT NULL,
		// 		  status SMALLINTEGER NOT NULL,
		// 		  pubdate INTEGER NOT NULL,
		// 		  updated INTEGER NOT NULL,
		// 		  modified INTEGER NOT NULL
		// 		);
		
		
		return DB::dbdelta( $q );
	}

	/**
	 * Register templates
	 */
	public function action_init()
	{
		// Add rewrite rules
		// $this->add_rule('"link"/"redirect"/slug', 'link_redirect');
		
		// Create templates
		$this->add_template('link.single', dirname(__FILE__) . '/link.single.php');

		// register tables
		DB::register_table('link_traffic');

		// Utils::debug( RewriteRules::by_name('link_redirect'), URL::get('link_redirect', array('slug' => 'bob')) );

		self::database();
	}

	/**
	 * Redirect a link to its original destination
	 */
	public function action_plugin_act_link_redirect($handler)
	{
		$slug= $handler->handler_vars['slug'];
		$post= Post::get(array('slug' => $slug));

		if($post == FALSE) {
			$handler->theme->display('404');
			exit;
		}

		$type= Traffum::TYPE_SEND_NORMAL;

		if(isset($handler->handler_vars['refer']) && $handler->handler_vars['refer'] == 'atom') {
			$type= Traffum::TYPE_SEND_ATOM;
		}

		Traffum::create(array('post_id' => $post->id, 'type' => $type));

		Utils::redirect($post->info->url);
		exit;
	}

	/**
	 * Create name string
	 */
	public function filter_post_type_display($type, $foruse)
	{
		$names = array(
			'link' => array(
				'singular' => _t('Link'),
				'plural' => _t('Links'),
			)
		);
 		return isset($names[$type][$foruse]) ? $names[$type][$foruse] : $type;
	}

	/**
	 * Modify publish form
	 */
	public function action_form_publish($form, $post)
	{
		if ($post->content_type == Post::type('link')) {
			$url= $form->append('text', 'url', 'null:null', _t('URL'), 'admincontrol_text');
			$url->value= $post->info->url;
			$form->move_after($url, $form->title);

		}
	}

	/**
	 * Initiate tracking on display
	 */
	function action_add_template_vars( $theme )
	{
		static $set= false;

		if($set == true || !is_object($theme->matched_rule) || $theme->matched_rule->action != 'display_post' || $theme->post->content_type != Post::type('link')) {
			return;
		}

		$post= $theme->post;

		$type= Traffum::TYPE_VIEW_NORMAL;

		if(Controller::get_var('refer') != NULL && Controller::get_var('refer') == 'atom') {
			$type= Traffum::TYPE_VIEW_ATOM;
		}

		Traffum::create(array('post_id' => $post->id, 'type' => $type));

		$set= true;
	}

	/**
	 * Save our data to the database
	 */
	public function action_publish_post( $post, $form )
	{
		if ($post->content_type == Post::type('link')) {
			$this->action_form_publish($form, $post);

			$post->info->url= $form->url->value;
		}
	}

	public function filter_post_link($permalink, $post) {
		if($post->content_type == Post::type('link')) {
			return self::get_redirect_url($post);
		}
		else {
			return $permalink;
		}
	}

	public function filter_post_permalink_atom($permalink, $post) {
		if($post->content_type == Post::type('link')) {
			if(Options::get('linkblog__atom_permalink') == TRUE) {
				return self::get_redirect_url($post, 'atom');
			}
		}
		return $permalink;
	}

	public static function get_redirect_url($post, $context = NULL) {
		$params= array('slug' => $post->slug);
		

		if(isset($context) && $context == 'atom') {
			$params['refer']= 'atom';
		}

		$url= URL::get('link_redirect', $params);

		return $url;
	}

	public static function get_permalink_url($post, $context = NULL) {
		$url= $post->permalink;

		if(isset($context) && $context == 'atom') {
			$url.= '?refer=atom';
		}

		return $url;
	}

	public function filter_post_content_atom($content, $post) {
		if($post->content_type == Post::type('link')) {
			$text= Options::get('linkblog__original');
			$text= str_replace('{original}', self::get_redirect_url($post, 'atom'), $text);
			$text= str_replace('{permalink}', self::get_permalink_url($post, 'atom'), $text);
			return $content . $text;
		}
		else {
			return $content;
		}
	}

	/**
	 * Add the posts to the blog home
	 */
	public function filter_template_user_filters($filters) {
		if(isset($filters['content_type'])) {
			$filters['content_type']= Utils::single_array( $filters['content_type'] );
			$filters['content_type'][]= Post::type('link');
		}
		return $filters;
	}

	/**
	 * Provide the alternate representation of the new feeds
	 */
	public function filter_atom_get_collection_alternate_rules( $rules )
	{
		$rules['link_feed'] = 'display_home';
		return $rules;
	}
	
	/**
	 * Add links to the main atom feed
	 */
	public function filter_atom_get_collection_content_type( $content_type )
	{
		$content_type = Utils::single_array( $content_type );
		$content_type[] = Post::type( 'link' );
		return $content_type;
	}

	/**
	 * Add needed rewrite rules
	 */
	public function filter_rewrite_rules($rules)
	{
		$feed_regex= $feed_regex = implode( '|', LinkHandler::$feeds );

		$rules[] = new RewriteRule( array(
			'name' => 'link_feed',
			'parse_regex' => '%feed/(?P<name>' . $feed_regex . ')/?$%i',
			'build_str' => 'feed/{$name}',
			'handler' => 'LinkHandler',
			'action' => 'feed',
			'priority' => 7,
			'is_active' => 1,
			'description' => 'Displays the link feeds',
		));
		// '"link"/"redirect"/slug', 'link_redirect');		
		
		$rules[] = new RewriteRule( array(
			'name' => 'link_redirect',
			'parse_regex' => '%link/redirect/(?P<slug>[^/]+)/?$%i',
			'build_str' => 'link/redirect/{$slug}',
			'handler' => 'PluginHandler',
			'action' => 'link_redirect',
			'priority' => 7,
			'is_active' => 1,
			'description' => 'Redirects to the linked item',
		));

		return $rules;
	}

}

?>
