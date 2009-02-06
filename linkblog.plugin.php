<?php

class LinkBlog extends Plugin
{ 
	
	/**
	 * Required plugin info() implementation provides info to Habari about this plugin.
	 */ 
	public function info()
	{
		return array (
			'name' => 'LinkBlog',
			'url' => 'http://habariproject.org',
			'author' => 'Habari Community',
			'authorurl' => 'http://habariproject.org',
			'version' => 0.1,
			'description' => 'Allows the publishing of link-based posts, which show up in the main entry feed',
			'license' => 'ASL 2.0',
		);
	}

	/**
	 * Create help file
	 */
	public function help() {
		$str= '';
		$str.= '<p>LinkBlog allows you to create posts with specific links attached to them.</p>';
		$str.= '<h3>Installation Instructions</h3>';
		$str.= '<p>Your theme needs to have a <code>link.single</code> template, or a generic <code>single</code> template. If it does not, you can usually copy <code>entry.single</code> to <code>link.single</code> and use it.</p>';
		return $str;
	}

	public function filter_plugin_config( $actions, $plugin_id )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			$actions[]= _t('Configure');
		}
		return $actions;
	}
	
	public function action_plugin_ui( $plugin_id, $action )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			switch ( $action ) {
				case _t('Configure') :
					$ui = new FormUI( strtolower( get_class( $this ) ) );
					$ui->append( 'text', 'original_text', 'linkblog__original', _t('Text to use for describing original in feeds:') );
					$ui->append( 'checkbox', 'atom_permalink', 'linkblog__atom_permalink', _t('Override atom permalink with link URL') );
					$ui->append( 'submit', 'save', _t('Save') );
					$ui->out();
					break;
			}
		}
	}

	/**
	* Add update beacon support
	**/
	public function action_update_check()
	{
		Update::add( $this->info->name, '1baf5dd5-8397-7db4-357e-ffa8b88697a1', $this->info->version );
	}
	
	/**
	 * Register content type
	 **/
	public function action_plugin_activation( $plugin_file )
	{
		Post::add_new_type( 'link' );
		
		// Give anonymous users access
		$group = UserGroup::get_by_name('anonymous');
		$group->grant('post_link', 'read');
		
		// Set default settings
		Options::set('linkblog__original', '<p><a href="{permalink}">Permalink</a></p>');
		Options::set('linkblog__atom_permalink', false);
		
	}
	
	public function action_plugin_deactivation( $plugin_file )
	{
		Post::deactivate_post_type( 'link' );
	}
	
	/**
	 * Register templates
	 **/
	public function action_init()
	{
		// Create templates
		$this->add_template('link.single', dirname(__FILE__) . '/link.single.php');
	}
	
	/**
	 * Create name string
	 **/
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
			return $post->info->url;
		}
		else {
			return $permalink;
		}
	}
	
	public function filter_post_permalink_atom($permalink, $post) {
		if($post->content_type == Post::type('link')) {
			if(Options::get('linkblog__atom_permalink') == TRUE) {
				return $post->info->url;
			}
		}
		return $permalink;
	}
	
	public function filter_post_content_atom($content, $post) {
		if($post->content_type == Post::type('link')) {
			$text= Options::get('linkblog__original');
			$text= str_replace('{original}', $post->info->url, $text);
			$text= str_replace('{permalink}', $post->permalink, $text);
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
	 * Filter the parameters passed to Posts::get()  in the Atomhandler.
	 * @param $content_type. mixed. content types being passed.
	 * @return array. content types with link type added.
	 */
	public function filter_atom_get_collection_content_type( $content_type )
	{
		$content_type = Utils::single_array( $content_type );
		$content_type[] = Post::type( 'link' );
		return $content_type;
	}
	

}	

?>