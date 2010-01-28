<?php
class SimpleTagsAdmin extends SimpleTagsBase {
	var $posts_base_url = '';
	var $options_base_url = '';
	
	// Error management
	var $message = '';
	var $status = '';
	
	// Tags list (management)
	var $nb_tags = 50;
	
	/**
	 * PHP4 Constructor - Intialize Admin
	 *
	 * @return SimpleTagsAdmin
	 */
	function SimpleTagsAdmin() {
		parent::initOptions();
		
		// Admin URL for Pagination and target
		$this->posts_base_url 	= admin_url('edit.php')  . '?page=';
		$this->options_base_url = admin_url('options-general.php') . '?page=';
		
		// Admin Capabilities
		add_action('init', array(&$this, 'initRoles'));
		
		// Admin menu
		add_action('admin_menu', array(&$this, 'adminMenu'));
		add_action('admin_notices', array(&$this, 'displayMessage'));
		
		// Ajax action, JS Helper and admin action
		add_action('admin_init', array(&$this, 'ajaxCheck'));
		add_action('admin_init', array(&$this, 'checkFormMassEdit'));
		
		// Embedded Tags
		if ( $this->options['use_embed_tags'] == 1 ) {
			add_actions( array('save_post', 'publish_post', 'post_syndicated_item'), array(&$this, 'saveEmbedTags') );
		}
		
		// Auto tags
		if ( $this->options['use_auto_tags'] == 1 ) {
			add_actions( array('save_post', 'publish_post', 'post_syndicated_item'), array(&$this, 'saveAutoTags') );
		}
		
		// Save tags from advanced input
		if ( $this->options['use_autocompletion'] == 1 ) {
			add_actions( array('save_post', 'publish_post'), array(&$this, 'saveAdvancedTagsInput') );
			add_action('do_meta_boxes', array(&$this, 'removeOldTagsInput'), 1 );
		}
		
		// Box for post
		add_action('admin_menu', array(&$this, 'helperClickTags_Post'), 1);
		add_action('admin_menu', array(&$this, 'helperSuggestTags_Post'), 1);
		add_action('admin_menu', array(&$this, 'helperAdvancedTags_Post'), 1);
		
		// Box for Page
		if ( $this->options['use_tag_pages'] == 1 ) {
			add_action('admin_menu', array(&$this, 'helperClickTags_Page'), 1);
			add_action('admin_menu', array(&$this, 'helperSuggestTags_Page'), 1);
			add_action('admin_menu', array(&$this, 'helperAdvancedTags_Page'), 1);
		}
		
		// Load JavaScript and CSS
		$this->initJavaScript();
	}
	
	function removeOldTagsInput() {
		remove_meta_box('tagsdiv-post_tag', 'post', 'side');
	}
	
	function initJavaScript() {
		global $pagenow, $wp_locale;
		
		// Library JS
		wp_register_script('jquery-bgiframe',			STAGS_URL.'/inc/js/jquery.bgiframe.min.js', array('jquery'), '2.1.1');
		wp_register_script('jquery-autocomplete',		STAGS_URL.'/inc/js/jquery.autocomplete.min.js', array('jquery', 'jquery-bgiframe'), '1.1');
		wp_register_script('jquery-cookie', 			STAGS_URL.'/inc/js/jquery.cookie.min.js', array('jquery'), '1.0.0');

		// Helper simple tags
		wp_register_script('st-helper-autocomplete', 	STAGS_URL.'/inc/js/helper-autocomplete.min.js', array('jquery', 'jquery-autocomplete'), $this->version);
		wp_register_script('st-helper-add-tags', 		STAGS_URL.'/inc/js/helper-add-tags.min.js', array('jquery'), $this->version);
		wp_register_script('st-helper-options', 		STAGS_URL.'/inc/js/helper-options.min.js', array('jquery'), $this->version);
		wp_register_script('st-helper-click-tags', 		STAGS_URL.'/inc/js/helper-click-tags.min.js', array('jquery', 'st-helper-add-tags'), $this->version);
		wp_localize_script('st-helper-click-tags', 'stHelperClickTagsL10n', array( 'site_url' => admin_url('admin.php'), 'show_txt' => __('Display click tags', 'simpletags'), 'hide_txt' => __('Hide click tags', 'simpletags') ) );
		wp_register_script('st-helper-suggested-tags', 	STAGS_URL.'/inc/js/helper-suggested-tags.min.js', array('jquery', 'st-helper-add-tags'), $this->version);
		wp_localize_script('st-helper-suggested-tags', 'stHelperSuggestedTagsL10n', array( 'site_url' => admin_url('admin.php'), 'title_bloc' => $this->getSuggestTagsTitle(), 'content_bloc' => __('Choose a provider to get suggested tags (local, yahoo or tag the net).', 'simpletags') ) );
		
		// Register CSS
		wp_register_style('st-admin', 				STAGS_URL.'/inc/css/admin.css', array(), $this->version, 'all' );
		wp_register_style('jquery-autocomplete', 	STAGS_URL.'/inc/css/jquery.autocomplete.css', array(), '1.1', 'all' );
		
		// Register pages
		$st_pages = array('st_manage', 'st_mass_tags', 'st_auto', 'st_options');
		$wp_post_pages = array('post.php', 'post-new.php');
		$wp_page_pages = array('page.php', 'page-new.php');
		
		// Common Helper for Post, Page and Plugin Page
		if (
			in_array($pagenow, $wp_post_pages) ||
			( in_array($pagenow, $wp_page_pages) && $this->options['use_tag_pages'] == 1 ) ||
			( isset($_GET['page']) && in_array($_GET['page'], $st_pages) )
		) {
			wp_enqueue_style ('st-admin');
		}
		
		// Helper for posts/pages
		if ( in_array($pagenow, $wp_post_pages) || (in_array($pagenow, $wp_page_pages) && $this->options['use_tag_pages'] == 1 ) ) {
			if ( $this->options['use_autocompletion'] == 1 ) {
				wp_enqueue_script('jquery-autocomplete');
				wp_enqueue_script('st-helper-autocomplete');
				wp_enqueue_style ('jquery-autocomplete');
			}
			
			if ( $this->options['use_click_tags'] == 1 )
				wp_enqueue_script('st-helper-click-tags');
			
			if ( $this->options['use_suggested_tags'] == 1 )
				wp_enqueue_script('st-helper-suggested-tags');
		}
		
		// add jQuery tabs for options page. Use jQuery UI Tabs from WP
		if ( isset($_GET['page']) && $_GET['page'] == 'st_options' ) {
			wp_enqueue_script('jquery-ui-tabs');
			wp_enqueue_script('jquery-cookie');
			wp_enqueue_script('st-helper-options');
		}
		
		// add JS for Auto Tags, Mass Edit Tags and Manage tags !
		if ( isset($_GET['page']) && in_array( $_GET['page'], array('st_auto', 'st_mass_tags', 'st_manage') ) ) {
			wp_enqueue_script('jquery-autocomplete');
			wp_enqueue_script('st-helper-autocomplete');
			wp_enqueue_style ('jquery-autocomplete');
		}
	}
	
	function initRoles() {
		if ( function_exists('get_role') ) {
			$role = get_role('administrator');
			if( $role != null && !$role->has_cap('simple_tags') ) {
				$role->add_cap('simple_tags');
			}
			if( $role != null && !$role->has_cap('admin_simple_tags') ) {
				$role->add_cap('admin_simple_tags');
			}
			
			$role = get_role('editor');
			if( $role != null && !$role->has_cap('simple_tags') ) {
				$role->add_cap('simple_tags');
			}
			// Clean var
			unset($role);
		}
	}
	
	/**
	 * Add WP admin menu for Tags
	 *
	 */
	function adminMenu() {
		add_posts_page( __('Simple Terms: Manage Terms', 'simpletags'), __('Manage Terms', 'simpletags'), 'simple_tags', 'st_manage', array(&$this, 'pageManageTags'));
		add_posts_page( __('Simple Terms: Mass Edit Terms', 'simpletags'), __('Mass Edit Terms', 'simpletags'), 'simple_tags', 'st_mass_tags', array(&$this, 'pageMassEditTags'));
		add_posts_page( __('Simple Terms: Auto Terms', 'simpletags'), __('Auto Terms', 'simpletags'), 'simple_tags', 'st_auto', array(&$this, 'pageAutoTags'));
		add_options_page( __('Simple Tags: Options', 'simpletags'), __('Simple Tags', 'simpletags'), 'admin_simple_tags', 'st_options', array(&$this, 'pageOptions'));
	}
	
	/**
	 * WP Page - Auto Tags
	 *
	 */
	function pageAutoTags() {
		$action = false;
		if ( isset($_POST['update_auto_list']) ) {
			// Tags list
			$tags_list = stripslashes($_POST['auto_list']);
			$tags = explode(',', $tags_list);
			
			// Remove empty and duplicate elements
			$tags = array_filter($tags, array(&$this, 'deleteEmptyElement'));
			$tags = array_unique($tags);
			
			parent::setOption( 'auto_list', maybe_serialize($tags) );
			
			// Active auto tags ?
			if ( isset($_POST['use_auto_tags']) && $_POST['use_auto_tags'] == '1' ) {
				parent::setOption( 'use_auto_tags', '1' );
			} else {
				parent::setOption( 'use_auto_tags', '0' );
			}
			
			// All tags ?
			if ( isset($_POST['at_all']) && $_POST['at_all'] == '1' ) {
				parent::setOption( 'at_all', '1' );
			} else {
				parent::setOption( 'at_all', '0' );
			}
			
			// Empty only ?
			if ( isset($_POST['at_empty']) && $_POST['at_empty'] == '1' ) {
				parent::setOption( 'at_empty', '1' );
			} else {
				parent::setOption( 'at_empty', '0' );
			}
			
			parent::saveOptions();
			$this->message = __('Auto tags options updated !', 'simpletags');
		} elseif ( isset($_GET['action']) && $_GET['action'] == 'auto_tag' ) {
			$action = true;
			$n = ( isset($_GET['n']) ) ? intval($_GET['n']) : 0;
		}
		
		$tags_list = '';
		$tags = maybe_unserialize($this->options['auto_list']);
		if ( is_array($tags) ) {
			$tags_list = implode(', ', $tags);
		}
		$this->displayMessage();
		?>
		<script type="text/javascript">
			<!--
			initAutoComplete( '#auto_list', '<?php echo admin_url('admin.php') .'?st_ajax_action=helper_js_collection'; ?>', 300 );
			-->
		</script>
			
		<div class="wrap st_wrap">
			<h2><?php _e('Auto Terms', 'simpletags'); ?></h2>
			<p><?php _e('Visit the <a href="http://redmine.beapi.fr/projects/show/simple-tags/">plugin\'s homepage</a> for further details. If you find a bug, or have a fantastic idea for this plugin, <a href="mailto:amaury@wordpress-fr.net">ask me</a> !', 'simpletags'); ?></p>
			
			<?php if ( $action === false ) : ?>
				
				<h3><?php _e('Auto terms list', 'simpletags'); ?></h3>
				<p><?php _e('This feature allows Wordpress to look into post content and title for specified terms when saving posts. If your post content or title contains the word "WordPress" and you have "wordpress" in auto terms list, Simple Tags will add automatically "wordpress" as term for this post.', 'simpletags'); ?></p>
				
				<h3><?php _e('Options', 'simpletags'); ?></h3>
				<form action="<?php echo $this->posts_base_url.'st_auto'; ?>" method="post">
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e('Activation', 'simpletags'); ?></th>
							<td>
								<input type="checkbox" id="use_auto_tags" name="use_auto_tags" value="1" <?php echo ( $this->options['use_auto_tags'] == 1 ) ? 'checked="checked"' : ''; ?>  />
								<label for="use_auto_tags"><?php _e('Active Auto Tags.', 'simpletags'); ?></label>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Terms database', 'simpletags'); ?></th>
							<td>
								<input type="checkbox" id="at_all" name="at_all" value="1" <?php echo ( $this->options['at_all'] == 1 ) ? 'checked="checked"' : ''; ?>  />
								<label for="at_all"><?php _e('Use also local terms database with auto tags. (Warning, this option can increases the CPU consumption a lot if you have many terms)', 'simpletags'); ?></label>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Target', 'simpletags'); ?></th>
							<td>
								<input type="checkbox" id="at_empty" name="at_empty" value="1" <?php echo ( $this->options['at_empty'] == 1 ) ? 'checked="checked"' : ''; ?>  />
								<label for="at_empty"><?php _e('Autotag only posts without terms.', 'simpletags'); ?></label>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="auto_list"><?php _e('Keywords list', 'simpletags'); ?></label></th>
							<td>
								<textarea id="auto_list" class="auto_list" name="auto_list"><?php echo $tags_list; ?></textarea> 
								<br /><?php _e('Separated with a comma', 'simpletags'); ?>
							</td>
						</tr>
					</table>
					
					<p class="submit">
						<input class="button-primary" type="submit" name="update_auto_list" value="<?php _e('Update options &raquo;', 'simpletags'); ?>" />
					</p>
				</form>
				
				<h3><?php _e('Auto terms old content', 'simpletags'); ?></h3>
				<p>
					<?php _e('Simple Tags can also tag all existing contents of your blog. This feature use auto terms list above-mentioned.', 'simpletags'); ?>
				</p>
				<p class="submit">
					<a class="button-primary" href="<?php echo $this->posts_base_url.'st_auto'; ?>&amp;action=auto_tag"><?php _e('Auto terms all content &raquo;', 'simpletags'); ?></a>
				</p>
			
			<?php else:
				// Counter
				if ( $n == 0 ) {
					update_option('tmp_auto_tags_st', 0);
				}
				
				// Page or not ?
				$post_type_sql = ( $this->options['use_tag_pages'] == '1' ) ? "post_type IN('page', 'post')" : "post_type = 'post'";
				
				// Get objects
				global $wpdb;
				$objects = (array) $wpdb->get_results("SELECT p.ID, p.post_title, p.post_content FROM {$wpdb->posts} p WHERE {$post_type_sql} ORDER BY ID DESC LIMIT {$n}, 20");
				
				if( !empty($objects) ) {
					echo '<ul>';
					foreach( $objects as $object ) {
						$this->autoTagsPost( $object );
						
						echo '<li>#'. $object->ID .' '. $object->post_title .'</li>';
						unset($object);
					}
					echo '</ul>';
					?>
					<p><?php _e("If your browser doesn't start loading the next page automatically click this link:", 'simpletags'); ?> <a href="<?php echo $this->posts_base_url.'st_auto'; ?>&amp;action=auto_tag&amp;n=<?php echo ($n + 20) ?>"><?php _e('Next content', 'simpletags'); ?></a></p>
					<script type="text/javascript">
						// <![CDATA[
						function nextPage() {
							location.href = '<?php echo $this->posts_base_url.'st_auto'; ?>&action=auto_tag&n=<?php echo ($n + 20) ?>';
						}
						window.setTimeout( 'nextPage()', 300 );
						// ]]>
					</script>
					<?php
				} else {
					$counter = get_option('tmp_auto_tags_st');
					delete_option('tmp_auto_tags_st');
					echo '<p><strong>'.sprintf(__('All done! %s terms added.', 'simpletags'), $counter).'</strong></p>';
				}
			
			endif;
			$this->printAdminFooter(); ?>
		</div>
		<?php
	}
	
	/**
	 * WP Page - Tags options
	 *
	 */
	function pageOptions() {
		$option_data = array(
			'general' => array(
				array('use_tag_pages', __('Active tags for page:', 'simpletags'), 'checkbox', '1',
					__('This feature allow page to be tagged. This option add pages in tags search. Also this feature add tag management in write page.', 'simpletags')),
				array('allow_embed_tcloud', __('Allow tag cloud in post/page content:', 'simpletags'), 'checkbox', '1',
					__('Enabling this will allow Wordpress to look for tag cloud marker <code>&lt;!--st_tag_cloud--&gt;</code> or <code>[st_tag_cloud]</code> or <code>[st-tag-cloud]</code> when displaying posts. WP replace this marker by a tag cloud.', 'simpletags')),
				array('no_follow', __('Add the rel="nofollow" on each tags link ?', 'simpletags'), 'checkbox', '1',
					__("Nofollow is a non-standard HTML attribute value used to instruct search engines that a hyperlink should not influence the link target's ranking in the search engine's index.",'simpletags'))
			),
			'administration' => array(
				array('use_click_tags', __('Activate click tags feature:', 'simpletags'), 'checkbox', '1',
					__('This feature add a link allowing you to display all the tags of your database. Once displayed, you can click over to add tags to post.', 'simpletags')),
				array('use_autocompletion', __('Activate autocompletion feature with old input:', 'simpletags'), 'checkbox', '1',
					__('This feature displays a visual help allowing to enter tags more easily. As well add tags is easier than the autocompletion default of WordPress', 'simpletags')),
				array('use_suggested_tags', __('Activate suggested tags feature: (Yahoo! Term Extraction API, Tag The Net, Local DB)', 'simpletags'), 'checkbox', '1',
					__('This feature add a box allowing you get suggested tags, by comparing post content and various sources of tags. (external and internal)', 'simpletags'))
			),
			'auto-links' => array(
				array('auto_link_tags', __('Active auto link tags into post content:', 'simpletags'), 'checkbox', '1',
					__('Example: You have a tag called "WordPress" and your post content contains "wordpress", this feature will replace "wordpress" by a link to "wordpress" tags page. (http://myblog.net/tag/wordpress/)', 'simpletags')),
				array('auto_link_min', __('Min usage for auto link tags:', 'simpletags'), 'text', '1',
					__('This parameter allows to fix a minimal value of use of tags. Default: 1.', 'simpletags')),
				array('auto_link_max_by_post', __('Maximum number of links per article:', 'simpletags'), 'text', '10',
					__('This setting determines the maximum number of links created by article. Default: 10.', 'simpletags')),
				array('auto_link_case', __('Ignore case for auto link feature ?', 'simpletags'), 'checkbox', '1',
					__('Example: If you ignore case, auto link feature will replace the word "wordpress" by the tag link "WordPress".', 'simpletags')),
				array('auto_link_exclude', __('Exclude some terms from tag link. For Ads Link subtition, etc.', 'simpletags'), 'checkbox', '1',
					__('Example: If you enter the term "Paris", the auto link tags feature will never replace this term by this link.', 'simpletags'))
			
			),
			'metakeywords' => array(
				array('meta_autoheader', __('Automatically include in header:', 'simpletags'), 'checkbox', '1',
					__('Includes the meta keywords tag automatically in your header (most, but not all, themes support this). These keywords are sometimes used by search engines.<br /><strong>Warning:</strong> If the plugin "All in One SEO Pack" is installed and enabled. This feature is automatically disabled.', 'simpletags')),
				array('meta_always_include', __('Always add these keywords:', 'simpletags'), 'text', 80),
				array('meta_keywords_qty', __('Max keywords display:', 'simpletags'), 'text', 10,
					__('You must set zero (0) for display all keywords in HTML header.', 'simpletags'))
			),
			'embeddedtags' => array(
				array('use_embed_tags', __('Use embedded tags:', 'simpletags'), 'checkbox', '1',
					__('Enabling this will allow Wordpress to look for embedded tags when saving and displaying posts. Such set of tags is marked <code>[tags]like this, and this[/tags]</code>, and is added to the post when the post is saved, but does not display on the post.', 'simpletags')),
				array('start_embed_tags', __('Prefix for embedded tags:', 'simpletags'), 'text', 40),
				array('end_embed_tags', __('Suffix for embedded tags:', 'simpletags'), 'text', 40)
			),
			'tagspost' => array(
				array('tt_feed', __('Automatically display tags list into feeds', 'simpletags'), 'checkbox', '1'),
				array('tt_embedded', __('Automatically display tags list into post content:', 'simpletags'), 'dropdown', 'no/all/blogonly/feedonly/homeonly/singularonly/pageonly/singleonly',
					'<ul>
						<li>'.__('<code>no</code> &ndash; Nowhere (default)', 'simpletags').'</li>
						<li>'.__('<code>all</code> &ndash; On your blog and feeds.', 'simpletags').'</li>
						<li>'.__('<code>blogonly</code> &ndash; Only on your blog.', 'simpletags').'</li>
						<li>'.__('<code>homeonly</code> &ndash; Only on your home page.', 'simpletags').'</li>
						<li>'.__('<code>singularonly</code> &ndash; Only on your singular view (single & page).', 'simpletags').'</li>
						<li>'.__('<code>singleonly</code> &ndash; Only on your single view.', 'simpletags').'</li>
						<li>'.__('<code>pageonly</code> &ndash; Only on your page view.', 'simpletags').'</li>
					</ul>'),
				array('tt_separator', __('Post tag separator string:', 'simpletags'), 'text', 10),
				array('tt_before', __('Text to display before tags list:', 'simpletags'), 'text', 40),
				array('tt_after', __('Text to display after tags list:', 'simpletags'), 'text', 40),
				array('tt_number', __('Max tags display:', 'simpletags'), 'text', 10,
					__('You must set zero (0) for display all tags.', 'simpletags')),
				array('tt_inc_cats', __('Include categories in result ?', 'simpletags'), 'checkbox', '1'),
				array('tt_xformat', __('Tag link format:', 'simpletags'), 'text', 80,
					__('You can find markers and explanations <a href="http://redmine.beapi.fr/projects/show/simple-tags/wiki/ThemeIntegration">in the online documentation.</a>', 'simpletags')),
				array('tt_notagstext', __('Text to display if no tags found:', 'simpletags'), 'text', 80),
				array('tt_adv_usage', __('<strong>Advanced usage</strong>:', 'simpletags'), 'text', 80,
					__('You can use the same syntax as <code>st_the_tags()</code> function to customize display. See <a href="http://redmine.beapi.fr/projects/show/simple-tags/wiki/ThemeIntegration">documentation</a> for more details.', 'simpletags'))
			),
			'relatedposts' => array(
				array('rp_feed', __('Automatically display related posts into feeds', 'simpletags'), 'checkbox', '1'),
				array('rp_embedded', __('Automatically display related posts into post content', 'simpletags'), 'dropdown', 'no/all/blogonly/feedonly/homeonly/singularonly/pageonly/singleonly',
					'<ul>
						<li>'.__('<code>no</code> &ndash; Nowhere (default)', 'simpletags').'</li>
						<li>'.__('<code>all</code> &ndash; On your blog and feeds.', 'simpletags').'</li>
						<li>'.__('<code>blogonly</code> &ndash; Only on your blog.', 'simpletags').'</li>
						<li>'.__('<code>homeonly</code> &ndash; Only on your home page.', 'simpletags').'</li>
						<li>'.__('<code>singularonly</code> &ndash; Only on your singular view (single & page).', 'simpletags').'</li>
						<li>'.__('<code>singleonly</code> &ndash; Only on your single view.', 'simpletags').'</li>
						<li>'.__('<code>pageonly</code> &ndash; Only on your page view.', 'simpletags').'</li>
					</ul>'),
				array('rp_order', __('Related Posts Order:', 'simpletags'), 'dropdown', 'count-asc/count-desc/date-asc/date-desc/name-asc/name-desc/random',
					'<ul>
						<li>'.__('<code>date-asc</code> &ndash; Older Entries.', 'simpletags').'</li>
						<li>'.__('<code>date-desc</code> &ndash; Newer Entries.', 'simpletags').'</li>
						<li>'.__('<code>count-asc</code> &ndash; Least common tags between posts', 'simpletags').'</li>
						<li>'.__('<code>count-desc</code> &ndash; Most common tags between posts (default)', 'simpletags').'</li>
						<li>'.__('<code>name-asc</code> &ndash; Alphabetical.', 'simpletags').'</li>
						<li>'.__('<code>name-desc</code> &ndash; Inverse Alphabetical.', 'simpletags').'</li>
						<li>'.__('<code>random</code> &ndash; Random.', 'simpletags').'</li>
					</ul>'),
				array('rp_xformat', __('Post link format:', 'simpletags'), 'text', 80,
					__('You can find markers and explanations <a href="http://redmine.beapi.fr/projects/show/simple-tags/wiki/ThemeIntegration">in the online documentation.</a>', 'simpletags')),
				array('rp_limit_qty', __('Maximum number of related posts to display: (default: 5)', 'simpletags'), 'text', 10),
				array('rp_notagstext', __('Enter the text to show when there is no related post:', 'simpletags'), 'text', 80),
				array('rp_title', __('Enter the positioned title before the list, leave blank for no title:', 'simpletags'), 'text', 80),
				array('rp_adv_usage', __('<strong>Advanced usage</strong>:', 'simpletags'), 'text', 80,
					__('You can use the same syntax as <code>st_related_posts()</code>function to customize display. See <a href="http://redmine.beapi.fr/projects/show/simple-tags/wiki/ThemeIntegration">documentation</a> for more details.', 'simpletags'))
			),
			'relatedtags' => array(
				array('rt_number', __('Maximum number of related tags to display: (default: 5)', 'simpletags'), 'text', 10),
				array('rt_order', __('Order related tags:', 'simpletags'), 'dropdown', 'count-asc/count-desc/name-asc/name-desc/random',
					'<ul>
						<li>'.__('<code>count-asc</code> &ndash; Least used.', 'simpletags').'</li>
						<li>'.__('<code>count-desc</code> &ndash; Most popular. (default)', 'simpletags').'</li>
						<li>'.__('<code>name-asc</code> &ndash; Alphabetical.', 'simpletags').'</li>
						<li>'.__('<code>name-desc</code> &ndash; Inverse Alphabetical.', 'simpletags').'</li>
						<li>'.__('<code>random</code> &ndash; Random.', 'simpletags').'</li>
					</ul>'),
				array('rt_format', __('Related tags type format:', 'simpletags'), 'dropdown', 'list/flat',
					'<ul>
						<li>'.__('<code>list</code> &ndash; Display a formatted list (ul/li).', 'simpletags').'</li>
						<li>'.__('<code>flat</code> &ndash; Display inline (no list, just a div)', 'simpletags').'</li>
					</ul>'),
				array('rt_method', __('Method of tags intersections and unions used to build related tags link:', 'simpletags'), 'dropdown', 'OR/AND',
					'<ul>
						<li>'.__('<code>OR</code> &ndash; Fetches posts with either the "Tag1" <strong>or</strong> the "Tag2" tag. (default)', 'simpletags').'</li>
						<li>'.__('<code>AND</code> &ndash; Fetches posts with both the "Tag1" <strong>and</strong> the "Tag2" tag.', 'simpletags').'</li>
					</ul>'),
				array('rt_xformat', __('Related tags link format:', 'simpletags'), 'text', 80,
					__('You can find markers and explanations <a href="http://redmine.beapi.fr/projects/show/simple-tags/wiki/ThemeIntegration">in the online documentation.</a>', 'simpletags')),
				array('rt_separator', __('Related tags separator:', 'simpletags'), 'text', 10,
					__('Leave empty for list format.', 'simpletags')),
				array('rt_notagstext', __('Enter the text to show when there is no related tags:', 'simpletags'), 'text', 80),
				array('rt_title', __('Enter the positioned title before the list, leave blank for no title:', 'simpletags'), 'text', 80),
				array('rt_adv_usage', __('<strong>Advanced usage</strong>:', 'simpletags'), 'text', 80,
					__('You can use the same syntax as <code>st_related_tags()</code>function to customize display. See <a href="http://redmine.beapi.fr/projects/show/simple-tags/wiki/ThemeIntegration">documentation</a> for more details.', 'simpletags')),
				// Remove related tags
				array('text_helper', 'text_helper', 'helper', '', '<h3>'.__('Remove related Tags', 'simpletags').'</h3>'),
				array('rt_format', __('Remove related Tags type format:', 'simpletags'), 'dropdown', 'list/flat',
					'<ul>
						<li>'.__('<code>list</code> &ndash; Display a formatted list (ul/li).', 'simpletags').'</li>
						<li>'.__('<code>flat</code> &ndash; Display inline (no list, just a div)', 'simpletags').'</li>
					</ul>'),
				array('rt_remove_separator', __('Remove related tags separator:', 'simpletags'), 'text', 10,
					__('Leave empty for list format.', 'simpletags')),
				array('rt_remove_notagstext', __('Enter the text to show when there is no remove related tags:', 'simpletags'), 'text', 80),
				array('rt_remove_xformat', __('Remove related tags  link format:', 'simpletags'), 'text', 80,
					__('You can find markers and explanations <a href="http://redmine.beapi.fr/projects/show/simple-tags/wiki/ThemeIntegration">in the online documentation.</a>', 'simpletags')),
			),
			'tagcloud' => array(
				array('text_helper', 'text_helper', 'helper', '', __('Which difference between <strong>&#8216;Order tags selection&#8217;</strong> and <strong>&#8216;Order tags display&#8217;</strong> ?<br />', 'simpletags')
					. '<ul style="list-style:square;">
						<li>'.__('<strong>&#8216;Order tags selection&#8217;</strong> is the first step during tag\'s cloud generation, corresponding to collect tags.', 'simpletags').'</li>
						<li>'.__('<strong>&#8216;Order tags display&#8217;</strong> is the second. Once tags choosen, you can reorder them before display.', 'simpletags').'</li>
					</ul>'.
					__('<strong>Example:</strong> You want display randomly the 100 tags most popular.<br />', 'simpletags').
					__('You must set &#8216;Order tags selection&#8217; to <strong>count-desc</strong> for retrieve the 100 tags most popular and &#8216;Order tags display&#8217; to <strong>random</strong> for randomize cloud.', 'simpletags')),
				array('cloud_selectionby', __('Order by for tags selection:', 'simpletags'), 'dropdown', 'count/name/random',
					'<ul>
						<li>'.__('<code>count</code> &ndash; Counter.', 'simpletags').'</li>
						<li>'.__('<code>name</code> &ndash; Name.', 'simpletags').'</li>
						<li>'.__('<code>random</code> &ndash; Random. (default)', 'simpletags').'</li>
					</ul>'),
				array('cloud_selection', __('Order tags selection:', 'simpletags'), 'dropdown', 'asc/desc',
					'<ul>
						<li>'.__('<code>asc</code> &ndash; Ascending.', 'simpletags').'</li>
						<li>'.__('<code>desc</code> &ndash; Descending.', 'simpletags').'</li>
					</ul>'),
				array('cloud_orderby', __('Order by for tags display:', 'simpletags'), 'dropdown', 'count/name/random',
					'<ul>
						<li>'.__('<code>count</code> &ndash; Counter.', 'simpletags').'</li>
						<li>'.__('<code>name</code> &ndash; Name.', 'simpletags').'</li>
						<li>'.__('<code>random</code> &ndash; Random. (default)', 'simpletags').'</li>
					</ul>'),
				array('cloud_order', __('Order tags display:', 'simpletags'), 'dropdown', 'asc/desc',
					'<ul>
						<li>'.__('<code>asc</code> &ndash; Ascending.', 'simpletags').'</li>
						<li>'.__('<code>desc</code> &ndash; Descending.', 'simpletags').'</li>
					</ul>'),
				array('cloud_inc_cats', __('Include categories in tag cloud ?', 'simpletags'), 'checkbox', '1'),
				array('cloud_format', __('Tags cloud type format:', 'simpletags'), 'dropdown', 'list/flat',
					'<ul>
						<li>'.__('<code>list</code> &ndash; Display a formatted list (ul/li).', 'simpletags').'</li>
						<li>'.__('<code>flat</code> &ndash; Display inline (no list, just a div)', 'simpletags').'</li>
					</ul>'),
				array('cloud_xformat', __('Tag link format:', 'simpletags'), 'text', 80,
					__('You can find markers and explanations <a href="http://redmine.beapi.fr/projects/show/simple-tags/wiki/ThemeIntegration">in the online documentation.</a>', 'simpletags')),
				array('cloud_limit_qty', __('Maximum number of tags to display: (default: 45)', 'simpletags'), 'text', 10),
				array('cloud_notagstext', __('Enter the text to show when there is no tag:', 'simpletags'), 'text', 80),
				array('cloud_title', __('Enter the positioned title before the list, leave blank for no title:', 'simpletags'), 'text', 80),
				array('cloud_max_color', __('Most popular color:', 'simpletags'), 'text-color', 10,
					__("The colours are hexadecimal colours,  and need to have the full six digits (#eee is the shorthand version of #eeeeee).", 'simpletags')),
				array('cloud_min_color', __('Least popular color:', 'simpletags'), 'text-color', 10),
				array('cloud_max_size', __('Most popular font size:', 'simpletags'), 'text', 10,
					__("The two font sizes are the size of the largest and smallest tags.", 'simpletags')),
				array('cloud_min_size', __('Least popular font size:', 'simpletags'), 'text', 10),
				array('cloud_unit', __('The units to display the font sizes with, on tag clouds:', 'simpletags'), 'dropdown', 'pt/px/em/%',
					__("The font size units option determines the units that the two font sizes use.", 'simpletags')),
				array('cloud_adv_usage', __('<strong>Advanced usage</strong>:', 'simpletags'), 'text', 80,
					__('You can use the same syntax as <code>st_tag_cloud()</code> function to customize display. See <a href="http://redmine.beapi.fr/projects/show/simple-tags/wiki/ThemeIntegration">documentation</a> for more details.', 'simpletags'))
			),
		);
		
		// Update or reset options
		if ( isset($_POST['updateoptions']) ) {
			foreach((array) $this->options as $key => $value) {
				$newval = ( isset($_POST[$key]) ) ? stripslashes($_POST[$key]) : '0';
				if ( $newval != $value && !in_array($key, array('use_auto_tags', 'auto_list')) ) {
					parent::setOption( $key, $newval );
				}
			}
			parent::saveOptions();
			$this->message = __('Options saved', 'simpletags');
			$this->status = 'updated';
		} elseif ( isset($_POST['reset_options']) ) {
			parent::resetToDefaultOptions();
			$this->message = __('Simple Tags options resetted to default options!', 'simpletags');
		}
		
		$this->displayMessage();
	    ?>
		<div class="wrap st_wrap">
			<h2><?php _e('Simple Tags: Options', 'simpletags'); ?></h2>
			<p><?php _e('Visit the <a href="http://redmine.beapi.fr/projects/show/simple-tags/">plugin\'s homepage</a> for further details. If you find a bug, or have a fantastic idea for this plugin, <a href="mailto:amaury@wordpress-fr.net">ask me</a> !', 'simpletags'); ?></p>
			<form action="<?php echo $this->options_base_url.'st_options'; ?>" method="post">
				<p>
					<input class="button" type="submit" name="updateoptions" value="<?php _e('Update options &raquo;', 'simpletags'); ?>" />
					<input class="button" type="submit" name="reset_options" onclick="return confirm('<?php _e('Do you really want to restore the default options?', 'simpletags'); ?>');" value="<?php _e('Reset Options', 'simpletags'); ?>" /></p>
				
				<div id="printOptions">
					<ul class="st_submenu">
						<?php foreach ( $option_data as $key => $val ) {
							echo '<li><a href="#'. sanitize_title ( $key ) .'">'.$this->getNiceTitleOptions($key).'</a></li>';
						} ?>
					</ul>
					
					<?php echo $this->printOptions( $option_data ); ?>
				</div>
				
				<p>
					<input class="button-primary" type="submit" name="updateoptions" value="<?php _e('Update options &raquo;', 'simpletags'); ?>" />
					<input class="button" type="submit" name="reset_options" onclick="return confirm('<?php _e('Do you really want to restore the default options?', 'simpletags'); ?>');" value="<?php _e('Reset Options', 'simpletags'); ?>" />
				</p>
			</form>
	    <?php $this->printAdminFooter(); ?>
	    </div>
	    <?php
	}
	
	/**
	 * WP Page - Manage tags
	 *
	 */
	function pageManageTags() {
		// Control Post data
		if ( isset($_POST['tag_action']) ) {
			// Origination and intention
			if ( !wp_verify_nonce($_POST['tag_nonce'], 'simpletags_admin') ) {
				$this->message = __('Security problem. Try again. If this problem persist, contact <a href="mailto:amaury@wordpress-fr.net">plugin author</a>.', 'simpletags');
				$this->status = 'error';
			}
			elseif ( $_POST['tag_action'] == 'renametag' ) {
				$oldtag = (isset($_POST['renametag_old'])) ? $_POST['renametag_old'] : '';
				$newtag = (isset($_POST['renametag_new'])) ? $_POST['renametag_new'] : '';
				$this->renameTags( $oldtag, $newtag );
			}
			elseif ( $_POST['tag_action'] == 'deletetag' ) {
				$todelete = (isset($_POST['deletetag_name'])) ? $_POST['deletetag_name'] : '';
				$this->deleteTagsByTagList( $todelete );
			}
			elseif ( $_POST['tag_action'] == 'addtag'  ) {
				$matchtag = (isset($_POST['addtag_match'])) ? $_POST['addtag_match'] : '';
				$newtag   = (isset($_POST['addtag_new'])) ? $_POST['addtag_new'] : '';
				$this->addMatchTags( $matchtag, $newtag );
			}
			elseif ( $_POST['tag_action'] == 'editslug'  ) {
				$matchtag = (isset($_POST['tagname_match'])) ? $_POST['tagname_match'] : '';
				$newslug   = (isset($_POST['tagslug_new'])) ? $_POST['tagslug_new'] : '';
				$this->editTagSlug( $matchtag, $newslug );
			} elseif ( $_POST['tag_action'] == 'cleandb'  ) {
				$this->cleanDatabase();
			}
		}
		
		$this->displayMessage();
		?>
		<script type="text/javascript">
			<!--
			initAutoComplete( '.autocomplete-input', '<?php echo admin_url('admin.php') .'?st_ajax_action=helper_js_collection'; ?>', 300 );
			-->
		</script>
			
		<div class="wrap st_wrap">
			<h2><?php _e('Simple Tags: Manage Terms', 'simpletags'); ?></h2>
			<p><?php _e('Visit the <a href="http://redmine.beapi.fr/projects/show/simple-tags/wiki/ThemeIntegration">plugin\'s homepage</a> for further details. If you find a bug, or have a fantastic idea for this plugin, <a href="mailto:amaury@wordpress-fr.net">ask me</a> !', 'simpletags'); ?></p>
	
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><strong><?php _e('Rename Term', 'simpletags'); ?></strong></th>
					<td>
						<p><?php _e('Enter the term to rename and its new value. You can use this feature to merge terms too. Click "Rename" and all posts which use this term will be updated.', 'simpletags'); ?></p>
						<p><?php _e('You can specify multiple terms to rename by separating them with commas.', 'simpletags'); ?></p>
						
						<fieldset>
							<form action="<?php echo $action_url; ?>" method="post">
								<input type="hidden" name="tag_action" value="renametag" />
								<input type="hidden" name="tag_nonce" value="<?php echo wp_create_nonce('simpletags_admin'); ?>" />
								
								<p>
									<label for="renametag_old"><?php _e('Term(s) to rename:', 'simpletags'); ?></label>
									<br />
									<input type="text" class="autocomplete-input" id="renametag_old" name="renametag_old" value="" size="40" />
								</p>
								
								<p>
									<label for="renametag_new"><?php _e('New term name(s):', 'simpletags'); ?>
									<br />
									<input type="text" class="autocomplete-input" id="renametag_new" name="renametag_new" value="" size="40" />
								</p>
								
								<input class="button-primary" type="submit" name="rename" value="<?php _e('Rename', 'simpletags'); ?>" />
							</form>
						</fieldset>
					</td>
				</tr>
				
				<tr valign="top">
					<th scope="row"><strong><?php _e('Delete Term', 'simpletags'); ?></strong></th>
					<td>
						<p><?php _e('Enter the name of the term to delete.  This term will be removed from all posts.', 'simpletags'); ?></p>
						<p><?php _e('You can specify multiple terms to delete by separating them with commas', 'simpletags'); ?>.</p>
						
						<fieldset>
							<form action="<?php echo $action_url; ?>" method="post">
								<input type="hidden" name="tag_action" value="deletetag" />
								<input type="hidden" name="tag_nonce" value="<?php echo wp_create_nonce('simpletags_admin'); ?>" />
								
								<p>
									<label for="deletetag_name"><?php _e('Term(s) to delete:', 'simpletags'); ?></label>
									<br />
									<input type="text" class="autocomplete-input" id="deletetag_name" name="deletetag_name" value="" size="40" />
								</p>
								
								<input class="button-primary" type="submit" name="delete" value="<?php _e('Delete', 'simpletags'); ?>" />
							</form>
						</fieldset>
					</td>
				</tr>
				
				<tr valign="top">
					<th scope="row"><strong><?php _e('Add Term', 'simpletags'); ?></strong></th>
					<td>
						<p><?php _e('This feature lets you add one or more new terms to all posts which match any of the terms given.', 'simpletags'); ?></p>
						<p><?php _e('You can specify multiple terms to add by separating them with commas.  If you want the term(s) to be added to all posts, then don\'t specify any terms to match.', 'simpletags'); ?></p>

						<fieldset>
							<form action="<?php echo $action_url; ?>" method="post">
								<input type="hidden" name="tag_action" value="addtag" />
								<input type="hidden" name="tag_nonce" value="<?php echo wp_create_nonce('simpletags_admin'); ?>" />
								
								<p>
									<label for="addtag_match"><?php _e('Term(s) to match:', 'simpletags'); ?></label>
									<br />
									<input type="text" class="autocomplete-input" id="addtag_match" name="addtag_match" value="" size="40" />
								</p>
								
								<p>
									<label for="addtag_new"><?php _e('Term(s) to add:', 'simpletags'); ?></label>
									<br />
									<input type="text" class="autocomplete-input" id="addtag_new" name="addtag_new" value="" size="40" />
								</p>
								
								<input class="button-primary" type="submit" name="Add" value="<?php _e('Add', 'simpletags'); ?>" />
							</form>
						</fieldset>
					</td>
				</tr>
				
				<tr valign="top">
					<th scope="row"><strong><?php _e('Edit Term Slug', 'simpletags'); ?></strong></th>
					<td>
						<p><?php _e('Enter the term name to edit and its new slug. <a href="http://codex.wordpress.org/Glossary#Slug">Slug definition</a>', 'simpletags'); ?></p>
						<p><?php _e('You can specify multiple terms to rename by separating them with commas.', 'simpletags'); ?></p>
						
						<fieldset>
							<form action="<?php echo $action_url; ?>" method="post">
								<input type="hidden" name="tag_action" value="editslug" />
								<input type="hidden" name="tag_nonce" value="<?php echo wp_create_nonce('simpletags_admin'); ?>" />
								
								<p>
									<label for="tagname_match"><?php _e('Term(s) to match:', 'simpletags'); ?></label>
									<br />
									<input type="text" class="autocomplete-input" id="tagname_match" name="tagname_match" value="" size="40" />
								</p>
								
								<p>
									<label for="tagslug_new"><?php _e('Slug(s) to set:', 'simpletags'); ?></label>
									<br />
									<input type="text" class="autocomplete-input" id="tagslug_new" name="tagslug_new" value="" size="40" />
								</p>
								
								<input class="button-primary" type="submit" name="edit" value="<?php _e('Edit', 'simpletags'); ?>" />
							</form>
						</fieldset>
					</td>
				</tr>
				
				<tr valign="top">
					<th scope="row"><strong><?php _e('Remove empty terms', 'simpletags'); ?></strong></th>
					<td>
						<p><?php _e('Old WordPress versions have a small bug and allow to create empty terms. Remove it !', 'simpletags'); ?></p>
						
						<fieldset>
							<form action="<?php echo $action_url; ?>" method="post">
								<input type="hidden" name="tag_action" value="cleandb" />
								<input type="hidden" name="tag_nonce" value="<?php echo wp_create_nonce('simpletags_admin'); ?>" />
								
								<p>
									<input class="button-primary" type="submit" name="clean" value="<?php _e('Clean !', 'simpletags'); ?>" />
								</p>
							</form>
						</fieldset>
					</td>
				</tr>
			</table>
					
			<?php $this->printAdminFooter(); ?>
		</div>
		<?php
	}
	
	function edit_data_query( $q = false ) {
		if ( false === $q ) {
			$q = $_GET;
		}
		
		// Date
		if ( isset($q['m']) )
			$q['m'] = (int) $q['m'];
		
		// Category
		if ( isset($q['cat']) )
			$q['cat'] = (int) $q['cat'];
		
		// Quantity
		$q['posts_per_page'] = ( isset($q['posts_per_page']) ) ? (int) $q['posts_per_page'] : 0;
		if ( $q['posts_per_page'] == 0 )
			$q['posts_per_page'] = 15;
		
		// Content type
		$q['post_type'] = ( isset($q['post_type']) && $q['post_type'] == 'page' ) ? 'page' : 'post';
		
		// Post status
		$post_stati  = array(	//	array( adj, noun )
			'publish' => array(_x('Published', 'post'), __('Published posts'), _n_noop('Published <span class="count">(%s)</span>', 'Published <span class="count">(%s)</span>')),
			'future' => array(_x('Scheduled', 'post'), __('Scheduled posts'), _n_noop('Scheduled <span class="count">(%s)</span>', 'Scheduled <span class="count">(%s)</span>')),
			'pending' => array(_x('Pending Review', 'post'), __('Pending posts'), _n_noop('Pending Review <span class="count">(%s)</span>', 'Pending Review <span class="count">(%s)</span>')),
			'draft' => array(_x('Draft', 'post'), _x('Drafts', 'manage posts header'), _n_noop('Draft <span class="count">(%s)</span>', 'Drafts <span class="count">(%s)</span>')),
			'private' => array(_x('Private', 'post'), __('Private posts'), _n_noop('Private <span class="count">(%s)</span>', 'Private <span class="count">(%s)</span>')),
		);
		
		$post_stati = apply_filters('post_stati', $post_stati);
		$avail_post_stati = get_available_post_statuses('post');
		
		$post_status_q = '';
		if ( isset($q['post_status']) && in_array( $q['post_status'], array_keys($post_stati) ) ) {
			$post_status_q = '&post_status=' . $q['post_status'];
			$post_status_q .= '&perm=readable';
		} elseif( !isset($q['post_status']) ) {
			$q['post_status'] = '';
		}
		
		if ( 'pending' === $q['post_status'] ) {
			$order = 'ASC';
			$orderby = 'modified';
		} elseif ( 'draft' === $q['post_status'] ) {
			$order = 'DESC';
			$orderby = 'modified';
		} else {
			$order = 'DESC';
			$orderby = 'date';
		}
		
		wp("post_type={$q['post_type']}&what_to_show=posts$post_status_q&posts_per_page={$q['posts_per_page']}&order=$order&orderby=$orderby");
		
		return array($post_stati, $avail_post_stati);
	}
	
	/**
	 * WP Page - Mass edit tags
	 *
	 */
	function pageMassEditTags() {
		global $wpdb, $wp_locale, $wp_query;
		list($post_stati, $avail_post_stati) = $this->edit_data_query();
		
		if ( !isset( $_GET['paged'] ) ) {
			$_GET['paged'] = 1;
		}
		?>
		<script type="text/javascript">
			<!--
			initAutoComplete( '.autocomplete-input', '<?php echo admin_url('admin.php') .'?st_ajax_action=helper_js_collection'; ?>', 300 );
			-->
		</script>
		
		<div class="wrap">
			<form id="posts-filter" action="" method="get">
				<input type="hidden" name="page" value="st_mass_tags" />
				<h2><?php _e('Mass edit terms', 'simpletags'); ?></h2>
				
				<ul class="subsubsub">
					<?php
					$status_links = array();
					$num_posts = wp_count_posts('post', 'readable');
					$class = (empty($_GET['post_status']) && empty($_GET['post_type'])) ? ' class="current"' : '';
					$status_links[] = "<li><a href=\"".admin_url('edit.php')."?page=st_mass_tags\"$class>".__('All Posts', 'simpletags')."</a>";
					foreach ( $post_stati as $status => $label ) {
						$class = '';
						
						if ( !in_array($status, $avail_post_stati) ) {
							continue;
						}
						
						if ( empty($num_posts->$status) )
							continue;
						if ( isset($_GET['post_status']) && $status == $_GET['post_status'] )
							$class = ' class="current"';
						
						$status_links[] = "<li><a href=\"".admin_url('edit.php')."?page=st_mass_tags&amp;post_status=$status\"$class>" . sprintf(_n($label[2][0], $label[2][1], (int) $num_posts->$status), number_format_i18n( $num_posts->$status )) . '</a>';
					}
					echo implode(' |</li>', $status_links) . ' |</li>';
					unset($status_links);
					
					$class = (!empty($_GET['post_type'])) ? ' class="current"' : '';
					?>
					<li><a href="<?php echo admin_url('edit.php'); ?>?page=st_mass_tags&amp;post_type=page" <?php echo $class; ?>><?php _e('All Pages', 'simpletags'); ?></a>
				</ul>
				
				<?php if ( isset($_GET['post_status'] ) ) : ?>
					<input type="hidden" name="post_status" value="<?php echo esc_attr($_GET['post_status']) ?>" />
				<?php endif; ?>
				
				<p class="search-box">
					<input type="text" id="post-search-input" name="s" value="<?php the_search_query(); ?>" />
					<input type="submit" value="<?php _e( 'Search Posts', 'simpletags' ); ?>" class="button" />
				</p>
				
				<div class="tablenav">
					<?php
					$posts_per_page = ( isset($_GET['posts_per_page']) ) ? (int) $_GET['posts_per_page'] : 0;
					if ( (int) $posts_per_page == 0 ) {
						$posts_per_page = 15;
					}
					
					$page_links = paginate_links( array(
						'base' => add_query_arg( 'paged', '%#%' ),
						'format' => '',
						'total' => ceil($wp_query->found_posts / $posts_per_page ),
						'current' => ((int) $_GET['paged'])
					));
					
					if ( $page_links )
						echo "<div class='tablenav-pages'>$page_links</div>";
					?>
					
					<div style="float: left">
						<?php
						if ( !is_singular() ) {
						$arc_query = "SELECT DISTINCT YEAR(post_date) AS yyear, MONTH(post_date) AS mmonth FROM $wpdb->posts WHERE post_type = 'post' ORDER BY post_date DESC";
						
						$arc_result = $wpdb->get_results( $arc_query );
						
						$month_count = count($arc_result);
						
						if ( $month_count && !( 1 == $month_count && 0 == $arc_result[0]->mmonth ) ) { ?>
							<select name='m'>
							<option<?php selected( @$_GET['m'], 0 ); ?> value='0'><?php _e('Show all dates', 'simpletags'); ?></option>
							<?php
							foreach ($arc_result as $arc_row) {
								if ( $arc_row->yyear == 0 )
									continue;
								$arc_row->mmonth = zeroise( $arc_row->mmonth, 2 );
								
								if ( $arc_row->yyear . $arc_row->mmonth == $_GET['m'] )
									$default = ' selected="selected"';
								else
									$default = '';
								
								echo "<option$default value='$arc_row->yyear$arc_row->mmonth'>";
								echo $wp_locale->get_month($arc_row->mmonth) . " $arc_row->yyear";
								echo "</option>\n";
							}
							?>
							</select>
						<?php } ?>
						
						<?php
						$_GET['cat'] = ( isset($_GET['cat']) ) ? stripslashes($_GET['cat']) : '';
						wp_dropdown_categories('show_option_all='.__('View all categories', 'simpletags').'&hide_empty=1&hierarchical=1&show_count=1&selected='.$_GET['cat']);
						?>
						
						<select name="posts_per_page" id="posts_per_page">
							<option <?php if ( !isset($_GET['posts_per_page']) ) echo 'selected="selected"'; ?> value=""><?php _e('Quantity&hellip;', 'simpletags'); ?></option>
							<option <?php if ( $posts_per_page == 10 ) echo 'selected="selected"'; ?> value="10">10</option>
							<option <?php if ( $posts_per_page == 20 ) echo 'selected="selected"'; ?> value="20">20</option>
							<option <?php if ( $posts_per_page == 30 ) echo 'selected="selected"'; ?> value="30">30</option>
							<option <?php if ( $posts_per_page == 40 ) echo 'selected="selected"'; ?> value="40">40</option>
							<option <?php if ( $posts_per_page == 50 ) echo 'selected="selected"'; ?> value="50">50</option>
							<option <?php if ( $posts_per_page == 100 ) echo 'selected="selected"'; ?> value="100">100</option>
							<option <?php if ( $posts_per_page == 200 ) echo 'selected="selected"'; ?> value="200">200</option>
						</select>
						
						<input type="submit" id="post-query-submit" value="<?php _e('Filter', 'simpletags'); ?>" class="button-secondary" />
						<?php } ?>
					</div>
					
					<br style="clear:both;" />
				</div>
			</form>
			
			<br style="clear:both;" />
			
			<?php if ( have_posts() ) :
				add_filter('the_title','esc_html');
				?>
				<form name="post" id="post" method="post">
					<table class="widefat post fixed">
						<thead>
							<tr>
								<th class="manage-column"><?php _e('Post title', 'simpletags'); ?></th>
								<th class="manage-column"><?php _e('Terms', 'simpletags'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$class = 'alternate';
							while (have_posts()) {
								the_post();
								$class = ( $class == 'alternate' ) ? '' : 'alternate';
								?>
								<tr valign="top" class="<?php echo $class; ?>">
									<th scope="row"><a href="<?php echo admin_url('post.php'); ?>?action=edit&amp;post=<?php the_ID(); ?>" title="<?php _e('Edit', 'simpletags'); ?>"><?php the_title(); ?></a></th>
									<td><input id="tags-input<?php the_ID(); ?>" class="autocomplete-input tags_input" type="text" size="100" name="tags[<?php the_ID(); ?>]" value="<?php echo $this->getTagsToEdit( get_the_ID() ); ?>" /></td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
					
					<p class="submit">
						<input type="hidden" name="secure_mass" value="<?php echo wp_create_nonce('st_mass_tags'); ?>" />
						<input class="button-primary" type="submit" name="update_mass" value="<?php _e('Update all &raquo;', 'simpletags'); ?>" />
					</p>
				</form>
			
			<?php else: ?>
				
				<p><?php _e('No content to edit.', 'simpletags'); ?>
			
			<?php endif; ?>
			<p><?php _e('Visit the <a href="http://redmine.beapi.fr/projects/show/simple-tags/">plugin\'s homepage</a> for further details. If you find a bug, or have a fantastic idea for this plugin, <a href="mailto:amaury@wordpress-fr.net">ask me</a> !', 'simpletags'); ?></p>
			<?php $this->printAdminFooter(); ?>
		</div>
    <?php
	}
	
	function getTagsToEdit( $post_id ) {
		$post_id = (int) $post_id;
		if ( !$post_id )
			return false;
		
		$tags = wp_get_post_tags($post_id);
		
		if ( !$tags )
			return false;
		
		foreach ( $tags as $tag )
			$tag_names[] = $tag->name;
		$tags_to_edit = join( ', ', $tag_names );
		$tags_to_edit = esc_attr( $tags_to_edit );
		$tags_to_edit = apply_filters( 'tags_to_edit', $tags_to_edit );
		
		return $tags_to_edit;
	}
	
	function saveAdvancedTagsInput( $post_id = null, $post_data = null ) {
		$object = get_post($post_id);
		if ( $object == false || $object == null ) {
			return false;
		}
		
		if ( isset($_POST['adv-tags-input']) ) {
			// Post data
			$tags = stripslashes($_POST['adv-tags-input']);
			
			// Trim data
			$tags = trim(stripslashes($tags));
			
			// String to array
			$tags = explode( ',', $tags );
			
			// Remove empty and trim tag
			$tags = array_filter($tags, array(&$this, 'deleteEmptyElement'));
			
			// Add new tag (no append ! replace !)
			wp_set_object_terms( $post_id, $tags, 'post_tag' );
			
			// Clean cache
			if ( 'page' == $object->post_type ) {
				clean_page_cache($post_id);
			} else {
				clean_post_cache($post_id);
			}
			
			return true;
		}
		return false;
	}
	
	/**
	 * Save embedded tags
	 *
	 * @param integer $post_id
	 * @param array $post_data
	 */
	function saveEmbedTags( $post_id = null, $post_data = null ) {
		$object = get_post($post_id);
		if ( $object == false || $object == null ) {
			return false;
		}
		
		// Return Tags
		$matches = $tags = array();
		preg_match_all('/(' . parent::regexEscape($this->options['start_embed_tags']) . '(.*?)' . parent::regexEscape($this->options['end_embed_tags']) . ')/is', $object->post_content, $matches);
		
		foreach ( $matches[2] as $match) {
			foreach( (array) explode(',', $match) as $tag) {
				$tags[] = $tag;
			}
		}
		
		if( !empty($tags) ) {
			// Remove empty and duplicate elements
			$tags = array_filter($tags, array(&$this, 'deleteEmptyElement'));
			$tags = array_unique($tags);
			
			wp_set_post_tags( $post_id, $tags, true ); // Append tags
			
			// Clean cache
			if ( 'page' == $object->post_type ) {
				clean_page_cache($post_id);
			} else {
				clean_post_cache($post_id);
			}
			
			return true;
		}
		return false;
	}
	
	/**
	 * Check post/page content for auto tags
	 *
	 * @param integer $post_id
	 * @param array $post_data
	 * @return boolean
	 */
	function saveAutoTags( $post_id = null, $post_data = null ) {
		$object = get_post($post_id);
		if ( $object == false || $object == null ) {
			return false;
		}
		
		$result = $this->autoTagsPost( $object );
		if ( $result == true ) {
			// Clean cache
			if ( 'page' == $object->post_type ) {
				clean_page_cache($post_id);
			} else {
				clean_post_cache($post_id);
			}
		}
		return true;
	}
	
	/**
	 * Automatically tag a post/page from the database tags
	 *
	 * @param object $object
	 * @return boolean
	 */
	function autoTagsPost( $object ) {
		if ( get_the_tags($object->ID) != false && $this->options['at_empty'] == 1 ) {
			return false; // Skip post with tags, if tag only empty post option is checked
		}
		
		$tags_to_add = array();
		
		// Merge title + content + excerpt to compare with tags
		$content = $object->post_content. ' ' . $object->post_title. ' ' . $object->post_excerpt;
		$content = trim($content);
		if ( empty($content) ) {
			return false;
		}
		
		// Auto tag with specifik auto tags list
		$tags = (array) maybe_unserialize($this->options['auto_list']);
		foreach ( $tags as $tag ) {
			if ( is_string($tag) && !empty($tag) && stristr($content, $tag) ) {
				$tags_to_add[] = $tag;
			}
		}
		unset($tags, $tag);
		
		// Auto tags with all posts
		if ( $this->options['at_all'] == 1 ) {
			// Get all terms
			global $wpdb;
			$terms = $wpdb->get_col("
				SELECT DISTINCT name
				FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = 'post_tag'
			");
			$terms = array_unique($terms);
			
			foreach ( $terms as $term ) {
				$term = stripslashes($term);
				if ( is_string($term) && !empty($term) && stristr($content, $term) ) {
					$tags_to_add[] = $term;
				}
			}
			
			// Clean memory
			$terms = array();
			unset($terms, $term);
		}
		
		// Append tags if tags to add
		if ( !empty($tags_to_add) ) {
			// Remove empty and duplicate elements
			$tags_to_add = array_filter($tags_to_add, array(&$this, 'deleteEmptyElement'));
			$tags_to_add = array_unique($tags_to_add);
			
			// Increment counter
			$counter = ((int) get_option('tmp_auto_tags_st')) + count($tags_to_add);
			update_option('tmp_auto_tags_st', $counter);
			
			// Add tags to posts
			wp_set_object_terms( $object->ID, $tags_to_add, 'post_tag', true );
			
			// Clean cache
			if ( 'page' == $object->post_type ) {
				clean_page_cache($object->ID);
			} else {
				clean_post_cache($object->ID);
			}
			
			return true;
		}
		return false;
	}
	
	############## Helper Advanced Tags ##############
	function helperAdvancedTags_Page() {
		if ( $this->options['use_autocompletion'] == 1 )
			add_meta_box('adv-tagsdiv', __('Tags (Simple Tags)', 'simpletags'), array(&$this, 'boxTags'), 'page', 'side', 'core');
	}
	
	function helperAdvancedTags_Post() {
		if ( $this->options['use_autocompletion'] == 1 )
			add_meta_box('adv-tagsdiv', __('Tags (Simple Tags)', 'simpletags'), array(&$this, 'boxTags'), 'post', 'side', 'core');
	}
	
	function boxTags( $post ) {
		?>
		<textarea name="adv-tags-input" id="adv-tags-input" tabindex="3" rows="3" cols="5"><?php echo $this->getTagsToEdit( $post->ID ); ?></textarea>
		<script type="text/javascript">
			<!--
			initAutoComplete( '#adv-tags-input', '<?php echo admin_url('admin.php') .'?st_ajax_action=helper_js_collection'; ?>', 300 );
			-->
		</script>		
		<?php _e('Separate tags with commas', 'simpletags');
	}
	
	############## Manages Tags Pages ##############
	/*
	 * Rename or merge tags
	 *
	 * @param string $old
	 * @param string $new
	 */
	function renameTags( $old = '', $new = '' ) {
		if ( trim( str_replace(',', '', stripslashes($new)) ) == '' ) {
			$this->message = __('No new tag specified!', 'simpletags');
			$this->status = 'error';
			return;
		}
		
		// String to array
		$old_tags = explode(',', $old);
		$new_tags = explode(',', $new);
		
		// Remove empty element and trim
		$old_tags = array_filter($old_tags, array(&$this, 'deleteEmptyElement'));
		$new_tags = array_filter($new_tags, array(&$this, 'deleteEmptyElement'));
		
		// If old/new tag are empty => exit !
		if ( empty($old_tags) || empty($new_tags) ) {
			$this->message = __('No new/old valid tag specified!', 'simpletags');
			$this->status = 'error';
			return;
		}
		
		$counter = 0;
		if( count($old_tags) == count($new_tags) ) { // Rename only
			foreach ( (array) $old_tags as $i => $old_tag ) {
				$new_name = $new_tags[$i];
				
				// Get term by name
				$term = get_term_by('name', $old_tag, 'post_tag');
				if ( !$term ) {
					continue;
				}
				
				// Get objects from term ID
				$objects_id = get_objects_in_term( $term->term_id, 'post_tag', array('fields' => 'all_with_object_id'));
				
				// Delete old term
				wp_delete_term( $term->term_id, 'post_tag' );
				
				// Set objects to new term ! (Append no replace)
				foreach ( (array) $objects_id as $object_id ) {
					wp_set_object_terms( $object_id, $new_name, 'post_tag', true );
				}
				
				// Clean cache
				clean_object_term_cache( $objects_id, 'post_tag');
				clean_term_cache($term->term_id, 'post_tag');
				
				// Increment
				$counter++;
			}
			
			if ( $counter == 0  ) {
				$this->message = __('No tag renamed.', 'simpletags');
			} else {
				$this->message = sprintf(__('Renamed tag(s) &laquo;%1$s&raquo; to &laquo;%2$s&raquo;', 'simpletags'), $old, $new);
			}
		}
		elseif ( count($new_tags) == 1  ) { // Merge
			// Set new tag
			$new_tag = $new_tags[0];
			if ( empty($new_tag) ) {
				$this->message = __('No valid new tag.', 'simpletags');
				$this->status = 'error';
				return;
			}
			
			// Get terms ID from old terms names
			$terms_id = array();
			foreach ( (array) $old_tags as $old_tag ) {
				$term = get_term_by('name', addslashes($old_tag), 'post_tag');
				$terms_id[] = (int) $term->term_id;
			}
			
			// Get objects from terms ID
			$objects_id = get_objects_in_term( $terms_id, 'post_tag', array('fields' => 'all_with_object_id'));
			
			// No objects ? exit !
			if ( !$objects_id ) {
				$this->message = __('No objects (post/page) found for specified old tags.', 'simpletags');
				$this->status = 'error';
				return;
			}
			
			// Delete old terms
			foreach ( (array) $terms_id as $term_id ) {
				wp_delete_term( $term_id, 'post_tag' );
			}
			
			// Set objects to new term ! (Append no replace)
			foreach ( (array) $objects_id as $object_id ) {
				wp_set_object_terms( $object_id, $new_tag, 'post_tag', true );
				$counter++;
			}
			
			// Test if term is also a category
			if ( is_term($new_tag, 'category') ) {
				// Edit the slug to use the new term
				$this->editTagSlug( $new_tag, sanitize_title($new_tag) );
			}
			
			// Clean cache
			clean_object_term_cache( $objects_id, 'post_tag');
			clean_term_cache($terms_id, 'post_tag');
			
			if ( $counter == 0  ) {
				$this->message = __('No tag merged.', 'simpletags');
			} else {
				$this->message = sprintf(__('Merge tag(s) &laquo;%1$s&raquo; to &laquo;%2$s&raquo;. %3$s objects edited.', 'simpletags'), $old, $new, $counter);
			}
		} else { // Error
			$this->message = sprintf(__('Error. No enough tags for rename. Too for merge. Choose !', 'simpletags'), $old);
			$this->status = 'error';
		}
		return;
	}
	
	/**
	 * trim and remove empty element
	 *
	 * @param string $element
	 * @return string
	 */
	function deleteEmptyElement( &$element ) {
		$element = stripslashes($element);
		$element = trim($element);
		if ( !empty($element) ) {
			return $element;
		}
	}
	
	/**
	 * Delete list of tags
	 *
	 * @param string $delete
	 */
	function deleteTagsByTagList( $delete ) {
		if ( trim( str_replace(',', '', stripslashes($delete)) ) == '' ) {
			$this->message = __('No tag specified!', 'simpletags');
			$this->status = 'error';
			return;
		}
		
		// In array + filter
		$delete_tags = explode(',', $delete);
		$delete_tags = array_filter($delete_tags, array(&$this, 'deleteEmptyElement'));
		
		// Delete tags
		$counter = 0;
		foreach ( (array) $delete_tags as $tag ) {
			$term = get_term_by('name', $tag, 'post_tag');
			$term_id = (int) $term->term_id;
			
			if ( $term_id != 0 ) {
				wp_delete_term( $term_id, 'post_tag');
				clean_term_cache( $term_id, 'post_tag');
				$counter++;
			}
		}
		
		if ( $counter == 0  ) {
			$this->message = __('No tag deleted.', 'simpletags');
		} else {
			$this->message = sprintf(__('%1s tag(s) deleted.', 'simpletags'), $counter);
		}
	}
	
	/**
	 * Add tags for all or specified posts
	 *
	 * @param string $match
	 * @param string $new
	 */
	function addMatchTags( $match, $new ) {
		if ( trim( str_replace(',', '', stripslashes($new)) ) == '' ) {
			$this->message = __('No new tag(s) specified!', 'simpletags');
			$this->status = 'error';
			return;
		}
		
		$match_tags = explode(',', $match);
		$new_tags = explode(',', $new);
		
		$match_tags = array_filter($match_tags, array(&$this, 'deleteEmptyElement'));
		$new_tags = array_filter($new_tags, array(&$this, 'deleteEmptyElement'));
		
		$counter = 0;
		if ( !empty($match_tags) ) { // Match and add
			// Get terms ID from old match names
			$terms_id = array();
			foreach ( (array) $match_tags as $match_tag ) {
				$term = get_term_by('name', $match_tag, 'post_tag');
				$terms_id[] = (int) $term->term_id;
			}
			
			// Get object ID with terms ID
			$objects_id = get_objects_in_term( $terms_id, 'post_tag', array('fields' => 'all_with_object_id') );
			
			// Add new tags for specified post
			foreach ( (array) $objects_id as $object_id ) {
				wp_set_object_terms( $object_id, $new_tags, 'post_tag', true ); // Append tags
				$counter++;
			}
			
			// Clean cache
			clean_object_term_cache( $objects_id, 'post_tag');
			clean_term_cache($terms_id, 'post_tag');
		} else { // Add for all posts
			// Page or not ?
			$post_type_sql = ( $this->options['use_tag_pages'] == '1' ) ? "post_type IN('page', 'post')" : "post_type = 'post'";
			
			// Get all posts ID
			global $wpdb;
			$objects_id = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE {$post_type_sql}");
			
			// Add new tags for all posts
			foreach ( (array) $objects_id as $object_id ) {
				wp_set_object_terms( $object_id, $new_tags, 'post_tag', true ); // Append tags
				$counter++;
			}
			
			// Clean cache
			clean_object_term_cache( $objects_id, 'post_tag');
		}
		
		if ( $counter == 0  ) {
			$this->message = __('No tag added.', 'simpletags');
		} else {
			$this->message = sprintf(__('Tag(s) added to %1s post(s).', 'simpletags'), $counter);
		}
	}
	
	/**
	 * Edit one or lots tags slugs
	 *
	 * @param string $names
	 * @param string $slugs
	 */
	function editTagSlug( $names = '', $slugs = '') {
		if ( trim( str_replace(',', '', stripslashes($slugs)) ) == '' ) {
			$this->message = __('No new slug(s) specified!', 'simpletags');
			$this->status = 'error';
			return;
		}
		
		$match_names = explode(',', $names);
		$new_slugs = explode(',', $slugs);
		
		$match_names = array_filter($match_names, array(&$this, 'deleteEmptyElement'));
		$new_slugs = array_filter($new_slugs, array(&$this, 'deleteEmptyElement'));
		
		if ( count($match_names) != count($new_slugs) ) {
			$this->message = __('Tags number and slugs number isn\'t the same!', 'simpletags');
			$this->status = 'error';
			return;
		} else {
			$counter = 0;
			foreach ( (array) $match_names as $i => $match_name ) {
				// Sanitize slug + Escape
				$new_slug = sanitize_title($new_slugs[$i]);
				
				// Get term by name
				$term = get_term_by('name', $match_name, 'post_tag');
				if ( !$term ) {
					continue;
				}
				
				// Increment
				$counter++;
				
				// Update term
				wp_update_term($term->term_id, 'post_tag', array('slug' => $new_slug));
				
				// Clean cache
				clean_term_cache($term->term_id, 'post_tag');
			}
		}
		
		if ( $counter == 0  ) {
			$this->message = __('No slug edited.', 'simpletags');
		} else {
			$this->message = sprintf(__('%s slug(s) edited.', 'simpletags'), $counter);
		}
		return;
	}
	
	/**
	 * Clean database - Remove empty terms
	 *
	 */
	function cleanDatabase() {
		global $wpdb;
		
		// Counter
		$counter = 0;
		
		// Get terms id empty
		$terms_id = $wpdb->get_col("SELECT term_id FROM {$wpdb->terms} WHERE name IN ('', ' ', '  ', '&nbsp;') GROUP BY term_id");
		if ( empty($terms_id) ) {
			$this->message = __('Nothing to muck. Good job !', 'simpletags');
			return;
		}
		
		// Prepare terms SQL List
		$terms_list = "'" . implode("', '", $terms_id) . "'";
		
		// Remove term empty
		$counter += $wpdb->query("DELETE FROM {$wpdb->terms} WHERE term_id IN ( {$terms_list} )");
		
		// Get term_taxonomy_id from term_id on term_taxonomy table
		$tts_id = $wpdb->get_col("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ( {$terms_list} ) GROUP BY term_taxonomy_id");
		
		if ( !empty($tts_id) ) {
			// Clean term_taxonomy table
			$counter += $wpdb->query("DELETE FROM {$wpdb->term_taxonomy} WHERE term_id IN ( {$terms_list} )");
			
			// Prepare terms SQL List
			$tts_list = "'" . implode("', '", $tts_id) . "'";
			
			// Clean term_relationships table
			$counter += $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ( {$tts_list} )");
		}
		
		// Delete cache
		clean_term_cache($terms_id, array('category', 'post_tag'));
		clean_object_term_cache($tts_list, 'post');
		
		$this->message = sprintf(__('%s rows deleted. WordPress DB is clean now !', 'simpletags'), $counter);
		return;
	}
	
	/**
	 * General features for tags
	 *
	 */
	function getDefaultContentBox() {
		if ( (int) wp_count_terms('post_tag', 'ignore_empty=true') == 0 ) {
			return __('This feature requires at least 1 tag to work. Begin by adding tags!', 'simpletags');
		} else {
			return __('This feature works only with activated JavaScript. Activate it in your Web browser so you can!', 'simpletags');
		}
	}
	
	/**
	 * Click tags
	 *
	 */
	function helperClickTags_Page() {
		if ( $this->options['use_click_tags'] == 1 )
			add_meta_box('st-clicks-tags', __('Click tags', 'simpletags'), array(&$this, 'boxClickTags'), 'page', 'advanced', 'core');
	}
	
	function helperClickTags_Post() {
		if ( $this->options['use_click_tags'] == 1 )
			add_meta_box('st-clicks-tags', __('Click tags', 'simpletags'), array(&$this, 'boxClickTags'), 'post', 'advanced', 'core');
	}
	
	function boxClickTags() {
		echo $this->getDefaultContentBox();
	}
	
	/**
	 * Suggested tags
	 *
	 */
	function getSuggestTagsTitle() {
		$title = '<img style="float:right; display:none;" id="st_ajax_loading" src="'.STAGS_URL.'/inc/images/ajax-loader.gif" alt="' .__('Ajax loading', 'simpletags').'" />';
		$title .=  __('Suggested tags from :', 'simpletags').'&nbsp;&nbsp;';
		$title .= '<a class="local_db" href="#suggestedtags">'.__('Local tags', 'simpletags').'</a>&nbsp;&nbsp;-&nbsp;&nbsp;';
		$title .= '<a class="yahoo_api" href="#suggestedtags">'.__('Yahoo', 'simpletags').'</a>&nbsp;&nbsp;-&nbsp;&nbsp;';
		$title .= '<a class="ttn_api" href="#suggestedtags">'.__('Tag The Net', 'simpletags').'</a>';
		return $title;
	}
	
	function helperSuggestTags_Post() {
		if ( $this->options['use_suggested_tags'] == 1 )
			add_meta_box('suggestedtags', __('Suggested tags', 'simpletags'), array(&$this, 'boxSuggestTags'), 'post', 'advanced', 'core');
	}
	
	function helperSuggestTags_Page() {
		if ( $this->options['use_suggested_tags'] == 1 )
			add_meta_box('suggestedtags', __('Suggested tags', 'simpletags'), array(&$this, 'boxSuggestTags'), 'page', 'advanced', 'core');
	}
	
	function boxSuggestTags() {
		?>
		<span class="container_clicktags">
			<?php echo $this->getDefaultContentBox(); ?>
			<div class="clear"></div>
		</span>
	    <?php
	}
	
	
	/**
	 * Control POST data for mass edit tags
	 *
	 * @param string $type
	 */
	function checkFormMassEdit() {
		if ( !current_user_can('simple_tags') ) {
			return false;
		}
		
		// Get GET data
		if ( isset($_GET['post_type']) )
			$type = stripslashes($_GET['post_type']);
		
		if ( isset($_POST['update_mass']) ) {
			// origination and intention
			if ( ! ( wp_verify_nonce($_POST['secure_mass'], 'st_mass_tags') ) ) {
				$this->message = __('Security problem. Try again. If this problem persist, contact <a href="mailto:amaury@wordpress-fr.net">plugin author</a>.', 'simpletags');
				$this->status = 'error';
				return false;
			}
			
			if ( isset($_POST['tags']) ) {
				$counter = 0;
				foreach ( (array) $_POST['tags'] as $object_id => $tag_list ) {
					// Trim data
					$tag_list = trim(stripslashes($tag_list));
					
					// String to array
					$tags = explode( ',', $tag_list );
					
					// Remove empty and trim tag
					$tags = array_filter($tags, array(&$this, 'deleteEmptyElement'));
					
					// Add new tag (no append ! replace !)
					wp_set_object_terms( $object_id, $tags, 'post_tag' );
					$counter++;
					
					// Clean cache
					if ( 'page' == $type ) {
						clean_page_cache($object_id);
					} else {
						clean_post_cache($object_id);
					}
				}
				
				if ( $type == 'page' ) {
					$this->message = sprintf(__('%s page(s) tags updated with success !', 'simpletags'), (int) $counter);
				} else {
					$this->message = sprintf(__('%s post(s) tags updated with success !', 'simpletags'), (int) $counter);
				}
				return true;
			}
		}
		return false;
	}
	
	############## Ajax ##############
	/**
	 * Ajax Dispatcher
	 *
	 */
	function ajaxCheck() {
		if ( isset($_GET['st_ajax_action']) )  {
			switch( $_GET['st_ajax_action'] ) {
				case 'get_tags' :
					$this->ajaxListTags();
				break;
				case 'tags_from_yahoo' :
					$this->ajaxYahooTermExtraction();
				break;
				case 'tags_from_tagthenet' :
					$this->ajaxTagTheNet();
				break;
				case 'helper_js_collection' :
					$this->ajaxLocalTags( 'js_collection' );
				break;
				case 'tags_from_local_db' :
					$this->ajaxSuggestLocal();
				break;
				case 'click_tags' :
					$this->ajaxLocalTags( 'html_span' );
				break;
			}
		}
	}
	
	/**
	 * Get tags list for manage tags page.
	 *
	 */
	function ajaxListTags() {
		status_header( 200 );
		header("Content-Type: text/javascript; charset=" . get_bloginfo('charset'));
		
		// Build param for tags
		$sort_order = esc_attr(stripslashes($_GET['order']));
		switch ($sort_order) {
			case 'natural' :
				$param = 'hide_empty=false&selectionby=name&selection=asc';
				break;
			case 'asc' :
				$param = 'hide_empty=false&selectionby=count&selection=asc';
				break;
			default :
				$param = 'hide_empty=false&selectionby=count&selection=desc';
				break;
		}
		
		// Build pagination
		$current_page = (int) $_GET['pagination'];
		$param .= '&number=LIMIT '. $current_page * $this->nb_tags . ', '.$this->nb_tags;
		
		// Get tags
		global $simple_tags;
		$tags = $simple_tags['client']->getTags($param, 'post_tag', true);
		
		// Build output
		echo '<ul class="ajax_list">';
		foreach( (array) $tags as $tag ) {
			echo '<li><span>'.$tag->name.'</span>&nbsp;<a href="'.(get_tag_link( $tag->term_id )).'" title="'.sprintf(__('View all posts tagged with %s', 'simpletags'), $tag->name).'">('.$tag->count.')</a></li>'."\n";
		}
		unset($tags);
		echo '</ul>';
		
		// Build pagination
		$ajax_url = admin_url('admin.php') . '?st_ajax_action=get_tags';
		
		// Order
		if ( isset($_GET['order']) ) {
			$ajax_url = $ajax_url . '&amp;order='.$sort_order ;
		}
		?>
		<div class="navigation">
			<?php if ( ($current_page * $this->nb_tags)  + $this->nb_tags > ((int) wp_count_terms('post_tag', 'ignore_empty=true')) ) : ?>
				<?php _e('Previous tags', 'simpletags'); ?>
			<?php else : ?>
				<a href="<?php echo $ajax_url. '&amp;pagination='. ($current_page + 1); ?>"><?php _e('Previous tags', 'simpletags'); ?></a>
			<?php endif; ?>
			|
			<?php if ( $current_page == 0 ) : ?>
				<?php _e('Next tags', 'simpletags'); ?>
			<?php else : ?>
			<a href="<?php echo $ajax_url. '&amp;pagination='. ($current_page - 1) ?>"><?php _e('Next tags', 'simpletags'); ?></a>
			<?php endif; ?>
		</div>
		<?php
		exit();
	}
	
	/**
	 * Suggest tags from Yahoo Term Extraction
	 *
	 */
	function ajaxYahooTermExtraction() {
		status_header( 200 );
		header("Content-Type: text/javascript; charset=" . get_bloginfo('charset'));
		
		// Get data
		$content = stripslashes($_POST['content']) .' '. stripslashes($_POST['title']);
		$content = trim($content);
		if ( empty($content) ) {
			echo '<p>'.__('No text was sent.', 'simpletags').'</p>';
			exit();
		}
		
		// Application entrypoint -> http://redmine.beapi.fr/projects/show/simple-tags/
		// Yahoo ID : h4c6gyLV34Fs7nHCrHUew7XDAU8YeQ_PpZVrzgAGih2mU12F0cI.ezr6e7FMvskR7Vu.AA--
		$yahoo_id = 'h4c6gyLV34Fs7nHCrHUew7XDAU8YeQ_PpZVrzgAGih2mU12F0cI.ezr6e7FMvskR7Vu.AA--';
		
		// Build params
		$param = 'appid='.$yahoo_id; // Yahoo ID
		$param .= '&context='.urlencode($content); // Post content
		if ( !empty($_POST['tags']) ) {
			$param .= '&query='.urlencode(stripslashes($_POST['tags'])); // Existing tags
		}
		$param .= '&output=php'; // Get PHP Array !
		
		$data = array();
		$reponse = wp_remote_post( 'http://search.yahooapis.com/ContentAnalysisService/V1/termExtraction?'.$param );
		if( !is_wp_error($reponse) && $reponse != null ) {
			$code = wp_remote_retrieve_response_code($reponse);
			if ( $code == 200 ) {
				$data = maybe_unserialize( wp_remote_retrieve_body($reponse) );
			}
		}
		
		if ( empty($data) || empty($data['ResultSet']) || is_wp_error($data) ) {
			echo '<p>'.__('No results from Yahoo! service.', 'simpletags').'</p>';
			exit();
		}
		
		// Get result value
		$data = (array) $data['ResultSet']['Result'];
		
		// Remove empty terms
		$data = array_filter($data, array(&$this, 'deleteEmptyElement'));
		$data = array_unique($data);
		
		foreach ( (array) $data as $term ) {
			echo '<span class="yahoo">'.$term.'</span>'."\n";
		}
		echo '<div class="clear"></div>';
		exit();
	}
	
	/**
	 * Suggest tags from Tag The Net
	 *
	 */
	function ajaxTagTheNet() {
		// Send good header HTTP
		status_header( 200 );
		header("Content-Type: text/javascript; charset=" . get_bloginfo('charset'));
		
		// Get data
		$content = stripslashes($_POST['content']) .' '. stripslashes($_POST['title']);
		$content = trim($content);
		if ( empty($content) ) {
			echo '<p>'.__('No text was sent.', 'simpletags').'</p>';
			exit();
		}
		
		$data = '';
		$reponse = wp_remote_post( 'http://tagthe.net/api/?text='.urlencode($content).'&view=json&count=200' );
		if( !is_wp_error($reponse) ) {
			$code = wp_remote_retrieve_response_code($reponse);
			if ( $code == 200 ) {
				$data = maybe_unserialize( wp_remote_retrieve_body($reponse) );
			}
		}
		
		require_once( dirname(__FILE__) . '/class/JSON.php' );
		$data = json_decode($data);
		$data = $data->memes[0];
		$data = $data->dimensions;
		
		if ( !isset($data->topic) && !isset($data->location) && !isset($data->person) ) {
			echo '<p>'.__('No results from Tag The Net service.', 'simpletags').'</p>';
			exit();
		}
		
		$terms = array();
		// Get all topics
		foreach ( (array) $data->topic as $topic ) {
			$terms[] = '<span class="ttn_topic">'.$topic.'</span>';
		}
		
		// Get all locations
		foreach ( (array) $data->location as $location ) {
			$terms[] = '<span class="ttn_location">'.$location.'</span>';
		}
		
		// Get all persons
		foreach ( (array) $data->person as $person ) {
			$terms[] = '<span class="ttn_person">'.$person.'</span>';
		}
		
		// Remove empty terms
		$terms = array_filter($terms, array(&$this, 'deleteEmptyElement'));
		$terms = array_unique($terms);
		
		echo implode("\n", $terms);
		echo '<div class="clear"></div>';
		exit();
	}
	
	/**
	 * Suggest tags from local database
	 *
	 */
	function ajaxSuggestLocal() {
		// Send good header HTTP
		status_header( 200 );
		header("Content-Type: text/javascript; charset=" . get_bloginfo('charset'));
		
		if ( ((int) wp_count_terms('post_tag', 'ignore_empty=true')) == 0) { // No tags to suggest
			echo '<p>'.__('No tags in your WordPress database.', 'simpletags').'</p>';
			exit();
		}
		
		// Get data
		$content = stripslashes($_POST['content']) .' '. stripslashes($_POST['title']);
		$content = trim($content);
		
		if ( empty($content) ) {
			echo '<p>'.__('No text was sent.', 'simpletags').'</p>';
			exit();
		}
		
		// Get all terms
		global $wpdb;
		$terms = $this->getTermsForAjax( '' );
		
		if ( empty($terms) || $terms == false ) {
			echo '<p>'.__('No results from your WordPress database.', 'simpletags').'</p>';
			exit();
		}
		
		$terms = array_unique($terms);
		foreach ( (array) $terms as $term ) {
			$term = stripslashes($term);
			if ( is_string($term) && !empty($term) && stristr($content, $term) ) {
				echo '<span class="local">'.$term.'</span>'."\n";
			}
		}
		
		echo '<div class="clear"></div>';
		exit();
	}
	
	/**
	 * Display a span list for click tags or a javascript collection for autocompletion script !
	 *
	 * @param string $format
	 */
	function ajaxLocalTags( $format = 'html_span' ) {
		// Send good header HTTP
		status_header( 200 );
		header("Content-Type: text/javascript; charset=" . get_bloginfo('charset'));
		
		if ( isset($_GET['id']) ) {
			$term = get_term( intval($_GET['id']), 'post_tag' );
			if ( $term != false ) {
				echo '[{"id":"'.$term->term_id.'","name":"'.$term->name.'"}]';
			} else {
				echo '';
			}
			exit();
		}
		
		if ((int) wp_count_terms('post_tag', 'ignore_empty=true') == 0 ) { // No tags to suggest
			if ( $format == 'html_span' ) {
				echo '<p>'.__('No tags in your WordPress database.', 'simpletags').'</p>';
			} else {
				echo '';
			}
			exit();
		}
		
		// Prepare search
		$search = trim(stripslashes($_GET['q']));
		
		// Get all terms, or filter with search
		$terms = $this->getTermsForAjax( $search );
		if ( empty($terms) || $terms == false ) {
			if ( $format == 'html_span' ) {
				echo '<p>'.__('No results from your WordPress database.', 'simpletags').'</p>';
			} else {
				echo '';
			}
			exit();
		}
		
		// Remove duplicate
		//$terms = array_unique($terms); // Todo work on name
		
		switch ($format) {
			case 'html_span' :
				
				foreach ( (array) $terms as $term ) {
					$term = stripslashes($term);
					echo '<span class="local">'.$term.'</span>'."\n";
				}
				echo '<div class="clear"></div>';
				break;
			
			case 'js_collection' :
			default:
				
				// Format terms
				$_terms = array();
				foreach ( (array) $terms as $_k => $term ) {
					$term->name = stripslashes($term->name);
					$term->name = str_replace( array("\r\n", "\r", "\n"), '', $term->name );
					
					echo "$term->term_id|$term->name\n";
				}
			
				break;
		
		}
		
		exit();
	}
	
	function getTermsForAjax( $search = '' ) {
		global $wpdb;
		
		if ( !empty($search) ) {
			return $wpdb->get_results( $wpdb->prepare("
				SELECT DISTINCT t.name, t.term_id
				FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = 'post_tag'
				AND name LIKE %s
			", '%'.$search.'%' ) );
		} else {
			return $wpdb->get_results("
				SELECT DISTINCT t.name, t.term_id
				FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = 'post_tag'
			");
		}
	}
	
	############## Admin WP Helper ##############
	/**
	 * Display plugin Copyright
	 *
	 */
	function printAdminFooter() {
		?>
		<p class="footer_st"><?php printf(__('&copy; Copyright 2010 <a href="http://www.herewithme.fr/" title="Here With Me">Amaury Balmer</a> | <a href="http://wordpress.org/extend/plugins/simple-tags">Simple Tags</a> | Version %s', 'simpletags'), $this->version); ?></p>
		<?php
	}
	
	/**
	 * Display WP alert
	 *
	 */
	function displayMessage() {
		if ( $this->message != '') {
			$message = $this->message;
			$status = $this->status;
			$this->message = $this->status = ''; // Reset
		}
		
		if ( isset($message) && !empty($message) ) {
		?>
			<div id="message" class="<?php echo ($status != '') ? $status :'updated'; ?> fade">
				<p><strong><?php echo $message; ?></strong></p>
			</div>
		<?php
		}
	}
	
	/**
	 * Ouput formatted options
	 *
	 * @param array $option_data
	 * @return string
	 */
	function printOptions( $option_data ) {
		// Get actual options
		$option_actual = (array) $this->options;
		
		// Generate output
		$output = '';
		foreach( $option_data as $section => $options) {
			$output .= "\n" . '<div id="'. sanitize_title($section) .'"><fieldset class="options"><legend>' . $this->getNiceTitleOptions($section) . '</legend><table class="form-table">' . "\n";
			foreach((array) $options as $option) {
				// Helper
				if (  $option[2] == 'helper' ) {
						$output .= '<tr style="vertical-align: middle;"><td class="helper" colspan="2">' . esc_html($option[4]) . '</td></tr>' . "\n";
						continue;
				}
				
				switch ( $option[2] ) {
					case 'checkbox':
						$input_type = '<input type="checkbox" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($option[3]) . '" ' . ( ($option_actual[ $option[0] ]) ? 'checked="checked"' : '') . ' />' . "\n";
						break;
					
					case 'dropdown':
						$selopts = explode('/', $option[3]);
						$seldata = '';
						foreach( (array) $selopts as $sel) {
							$seldata .= '<option value="' . esc_attr($sel) . '" ' .((isset($option_actual[ $option[0] ]) &&$option_actual[ $option[0] ] == $sel) ? 'selected="selected"' : '') .' >' . ucfirst($sel) . '</option>' . "\n";
						}
						$input_type = '<select id="' . $option[0] . '" name="' . $option[0] . '">' . $seldata . '</select>' . "\n";
						break;
					
					case 'text-color':
						$input_type = '<input type="text" ' . ((isset($option[3]) && $option[3]>50) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($option_actual[ $option[0] ]) . '" size="' . $option[3] .'" /><div class="box_color ' . $option[0] . '"></div>' . "\n";
						break;
					
					case 'text':
					default:
						$input_type = '<input type="text" ' . ((isset($option[3]) && $option[3]>50) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($option_actual[ $option[0] ]) . '" size="' . $option[3] .'" />' . "\n";
						break;
				}
				
				// Additional Information
				$extra = '';
				if( !empty($option[4]) ) {
					$extra = '<div class="stpexplan">' . __($option[4]) . '</div>' . "\n";
				}
				
				// Output
				$output .= '<tr style="vertical-align: top;"><th scope="row"><label for="'.$option[0].'">' . __($option[1]) . '</label></th><td>' . $input_type . '	' . $extra . '</td></tr>' . "\n";
			}
			$output .= '</table>' . "\n";
			$output .= '</fieldset></div>' . "\n";
		}
		return $output;
	}
	
	/**
	 * Get nice title for tabs title option
	 *
	 * @param string $id
	 * @return string
	 */
	function getNiceTitleOptions( $id = '' ) {
		switch ( $id ) {
			case 'administration':
				return __('Administration', 'simpletags');
				break;
			case 'auto-links':
				return __('Auto link', 'simpletags');
				break;
			case 'general':
				return __('General', 'simpletags');
				break;
			case 'metakeywords':
				return __('Meta Keyword', 'simpletags');
				break;
			case 'embeddedtags':
				return __('Embedded Tags', 'simpletags');
				break;
			case 'tagspost':
				return __('Tags for Current Post', 'simpletags');
				break;
			case 'relatedposts':
				return __('Related Posts', 'simpletags');
				break;
			case 'relatedtags':
				return __('Related Tags', 'simpletags');
				break;
			case 'tagcloud':
				return __('Tag cloud', 'simpletags');
				break;
		}
		return '';
	}
}
?>