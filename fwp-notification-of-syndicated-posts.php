<?php
/*
Plugin Name: FWP+: Notification of Syndicated Posts 
Plugin URI: 
Description: Add-on enabling FeedWordPress to send an e-mail to a designated address notifying the recipient of posts syndicated in the most recent FeedWordPress update.
Version: 2013.0522
Author: Charles Johnson
Author URI: http://projects.radgeek.com/
*/

define(FNOPS_NOTIFICATION_POSTMETA, '_fwp_notification_queued');

/**
 * FWPNotificationOfSyndicatedPosts: singleton class to initialize plugin event
 * hooks and maintain state for our update-notifier.
 */
class FWPNotificationOfSyndicatedPosts {
	private $posts_to_notify;
	
	public function __construct () {
		// Set up filter action hooks.
		add_action('feedwordpress_update_complete', array($this, 'feedwordpress_update_complete'), 1, 1000);
		add_action('post_syndicated_item', array($this, 'post_syndicated_item'), 2, 1000);
			
	} /* FWPNotificationOfSyndicatedPosts::__construct () */
	
	public function post_syndicated_item ($id, $post) {
		// We are in an update process and just inserted a new post.
		// Let's see if we need to tag it for notification at the end
		// of the update process.
		if ($this->shouldNotify($post)) :
			$link = $post->link;
			add_post_meta($id, FNOPS_NOTIFICATION_POSTMETA, $link->id());
		endif;
	} /* FWPNotificationOfSyndicatedPosts::post_syndicated_item () */

	public function feedwordpress_update_complete ($delta) {
		// Let's do this.
		$q = new WP_Query(array(
		'post_type' => 'any',
		'meta_key' => FNOPS_NOTIFICATION_POSTMETA,
		'posts_per_page' => 10,
		));

		global $post;

		if ($q->post_count > 0) :
			$this->add_shortcodes();

			$pre = get_option('fnosp_email_prefix', '');
			$pre = do_shortcode($pre);
			
			$inter = get_option('fnosp_email_inter', "\n");
			$inter = do_shortcode($inter);
			
			$mid = '';
			while ($q->have_posts()) : $q->the_post();
				$link_id = get_post_meta(
					$post->ID, FNOPS_NOTIFICATION_POSTMETA,
					/*single=*/ true
				);
				$sub = new SyndicatedLink($link_id);
				
				$tmpl = $sub->setting('fnosp email template');
				$mid .= do_shortcode($tmpl);
				
				delete_post_meta(
					$post->ID, FNOPS_NOTIFICATION_POSTMETA
				);
				
			endwhile;
			wp_reset_query();
			
			$suf = get_option('fnosp_email_suffix', '');
			$suf = do_shortcode($suf);
			
			if (strlen($mid) > 0) :
				/* STUB: Send message. */
			endif;
			
			$this->remove_shortcodes();

		endif;
	} /* FWPNotificationOfSyndicatedPosts::feedwordpress_update_complete () */
	
	public function get_shortcodes () {
		return array(
		"wpadmin",
		"wplogin",
		"post_id",
		"post_status",
		"post_title",
		"post_content",
		"post_excerpt",
		"post_date",
		"post_link",
		"edit_link",
		/* STUB: Other shortcodes */
		);
	} /* FWPNotificationOfSyndicatedPosts::get_shortcodes () */

	public function add_shortcodes () {
		$codes = $this->get_shortcodes();
		foreach ($codes as $code) :
			add_shortcode($code, array($this, 'shortcode_'.$code));
		endforeach;
	} /* FWPNotificationOfSyndicatedPosts::add_shortcodes () */

	public function remove_shortcodes () {
		$codes = $this->get_shortcodes();
		foreach ($codes as $code) :
			remove_shortcode($code);
		endforeach;
	} /* FWPNotificationOfSyndicatedPosts::remove_shortcodes () */
	
	public function shortcode_link_to ($atts, $content = '') {
		$p = shortcode_atts(array(
			"href" => NULL
		), $atts);
		
		$link = $p['href'];
		if (strlen($content) > 0) :
			$link = '<a href="' . esc_url($link) . '">' . do_shortcode($content) . '</a>'; 
		endif;
		
		return $link;
	}
	public function shortcode_post_status ($atts, $content = '') {
		global $post;
		return $post->post_status;
	}
	
	public function shortcode_wpadmin ($atts, $content = '') {
		$p = shortcode_atts(array(
			"path" => NULL,
			"scheme" => NULL,
		), $atts);
		
		return $this->shortcode_link_to(array(
			"href" => admin_url($atts['path'], $atts['scheme']),
		), $content);
	} /* FWPNotificationOfSyndicatedPosts::shortcode_wpadmin () */
	
	public function shortcode_wplogin ($atts, $content = '') {
		return $this->shortcode_link_to(array(
			"href" => wp_login_url(admin_url()),
		), $content);
	} /* FWPNotificationOfSyndicatedPosts:shortcode_wplogin () */
	
	public function shortcode_post_title ($atts, $content = '') {
		global $post;
		return get_the_title($post->ID);
	} /* FWPNotificationOfSyndicatedPosts::shortcode_post_title () */
	
	public function shortcode_post_id ($atts, $content = '') {
		global $post;
		return $post->ID;
	} /* FWPNotificationOfSyndicatedPosts::shortcode_post_id () */
	
	public function shortcode_post_content ($atts, $content = '') {
		return get_the_content();
	}
	
	public function shortcode_post_excerpt ($atts, $content = '') {
		return get_the_excerpt();
	} /* FWPNotificationOfSyndicatedPosts::shortcode_post_excerpt () */
	
	public function shortcode_post_date ($atts, $content = '') {
		$atts = shortcode_atts(array(
			"format" => "r",
		), $atts);
		return get_the_time($atts['format']);
	} /* FWPNotificationOfSyndicatedPosts::shortcode_post_date () */
	
	public function shortcode_post_link ($atts, $content = '') {
		global $post;
		
		return $this->shortcode_link_to(array(
			"href" => get_permalink($post->ID),
		), $content);
	}
	
	public function shortcode_edit_link ($atts, $content = '') {
		global $post;
		
		return $this->shortcode_link_to(array(
			"href" => get_edit_post_link($post->ID),
		), $content);
	} /* FWPNotificationOfSyndicatedPosts::edit_link () */
	
	public function shouldNotify ($post) {
		/*STUB*/ return true;
	} /* FWPNotificationOfSyndicatedPosts::shouldNotify () */
} /* class FWPNotificationOfSyndicatedPosts */

// Initialize singleton object.
global $fwpNotify;
$fwpNotify = new FWPNotificationOfSyndicatedPosts;

