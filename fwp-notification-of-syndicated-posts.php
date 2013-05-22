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
		"post_title",
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
	
	public function shortcode_wpadmin ($atts, $content = '') {
		$p = shortcode_atts(array(
			"path" => NULL,
			"scheme" => NULL,
		), $atts);
		
		return admin_url($atts['path'], $atts['scheme']);
	} /* FWPNotificationOfSyndicatedPosts::shortcode_wpadmin () */
	
	public function shortcode_post_title ($atts, $content = '') {
		global $post;
		return get_the_title($post->ID);
	}
	
	public function shouldNotify ($post) {
		/*STUB*/ return true;
	} /* FWPNotificationOfSyndicatedPosts::shouldNotify () */
} /* class FWPNotificationOfSyndicatedPosts */

// Initialize singleton object.
global $fwpNotify;
$fwpNotify = new FWPNotificationOfSyndicatedPosts;

