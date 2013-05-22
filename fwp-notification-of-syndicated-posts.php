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
		
		// Set up interface hooks for configuration UI
		add_action('feedwordpress_admin_page_feeds_meta_boxes', array($this, 'add_settings_box'));
		add_action('feedwordpress_admin_page_feeds_save', array($this, 'save_settings'), 10, 2);
		add_action('feedwordpress_diagnostics', array($this, 'diagnostics'), 10, 2);
		
	} /* FWPNotificationOfSyndicatedPosts::__construct () */
	
	function add_settings_box ($page) {
		add_meta_box(
			/*id=*/ "feedwordpress_".__CLASS__."_box",
			/*title=*/ __("Syndication Activity Notifications"),
			/*callback=*/ array(&$this, 'display_settings'),
			/*page=*/ $page->meta_box_context(),
			/*context=*/ $page->meta_box_context()
		);
	} /* FWPNotificationOfSyndicatedPosts::add_settings_box() */
	
	public function diagnostics ($diag, $page) {
		$diag['Syndicated Post Details']['notify:test'] = 'when we test whether an e-mail notification should be sent for a post';
		return $diag;
	} /* FWPNotificationOfSyndicatedPosts::diagnostics () */
	
	public function display_settings ($page, $box = NULL) {
		$stati = get_post_stati(array(), 'objects');
		$activeStati = maybe_unserialize(
			$page->setting(
				'fnosp notify on status',
				/*default=*/ array(),
				array(
					"fallback" => false,
				)
			)
		);
		$globalStati = maybe_unserialize(get_option('feedwordpress_fnosp_notify_on_status', array()));
		
?>
<table class="edit-form narrow">
<tr><th scope="row"><?php _e('Email notifications:'); ?></th>
<td><p>Send out e-mail notifications to: <input type="email" name="fnosp_notify_email" value="<?php print esc_attr($page->setting('fnosp notify email')); ?>" <?php if ($page->for_feed_settings()) : ?>disabled="disabled"<?php endif; ?> /><?php if ($page->for_feed_settings()) : ?>(global setting)<?php endif; ?></p>
<?php if ($page->for_feed_settings()) : ?>
<table class="twofer">
<tbody>
<tr>
<td class="primary">
<?php endif; ?>

<p>for incoming syndicated posts that are given the status of:</p>
<ul>
<?php
foreach ($stati as $status) :
	if (!$status->internal) :
?>
<li><input type="checkbox" name="fnosp_notify_on_status[<?php print esc_attr($status->name); ?>]" value="yes" <?php if (in_array($status->name, $activeStati)) : ?>
 checked="checked"
<?php endif; ?> /> <?php print esc_html($status->label); ?></li>
<?php
	endif;
endforeach;
?>
</ul>

<?php
	$fmt = $page->setting('fnosp email format', 'text/plain');
	$fmtSelected = array("text/plain" => '', "text/html" => '');
	$fmtSelected[$fmt] = ' selected="selected"';

if ($page->for_feed_settings()) :
	$addGlobalStati = $page->setting('fnosp add global stati', 'yes');
	$agsChecked = array("yes" => '', "no" => '');
	$agsChecked[$addGlobalStati] = ' checked="checked"';
	
?>
</td>

<td class="secondary">
<h4>Site-wide Notifications</h4>
<?php if (count($globalStati) > 0) : ?>
<p>By default, we will send out notifications on posts from any feed set as:</p>
<ul class="current-setting">
<li><?php print implode("</li>\n<li>", $globalStati); ?></li>
</ul>
<?php else : ?>
<p>Site-wide settings may also send out notifications on syndicated posts with specific status settings.</p>
<?php endif; ?>

<p>Should <?php print $page->these_posts_phrase(); ?> trigger notifications when
assigned to these statuses from the site-wide settings?</p>

<ul class="settings">
<li><p><label><input type="radio" name="fnosp_add_global_stati" value="yes" <?php print $agsChecked['yes']; ?> /> Yes. Use both sets of statuses to trigger notifications for posts from this feed.</label></p></li>
<li><p><label><input type="radio" name="fnosp_add_global_stati" value="no" <?php print $agsChecked['no']; ?> /> No. With posts from <em>this</em> feed, only send out notifications for the statuses I set up on the left. Do not use the global defaults.</label></li>
</ul>

</td>
</tr>
</tbody>
</table>

<?php endif; ?>
</td></tr>

<tr><th scope="row">Notification E-mail Templates:</th>
<td>
<?php if ($page->for_default_settings()) : ?>
<p><label>Format:</label> <select name="fnosp_email_format" size="1">
  <option value="text/plain"<?php print $fmtSelected['text/plain']; ?>>Text</option>
  <option value="text/html"<?php print $fmtSelected['text/html']; ?>>HTML</option>
</select></p>

<p><label>Subject:</label> <input type="text" name="fnosp_email_subject" size="127" value="<?php print esc_html($this->get_email_subject()); ?>" /></label></p>

<h4>(e-mail opening)</h4>
<textarea name="fnosp_email_prefix" rows="3" cols="80" style="width: auto"><?php print esc_html($this->get_email_prefix()); ?></textarea>
<?php endif; ?>

<h4>(post listing)</h4>
<textarea name="fnosp_email_template" rows="5" cols="80" style="width: auto"><?php print esc_html($this->get_email_post_listing(NULL, $page)); ?></textarea>
<?php if ($page->for_feed_settings()) : ?>
<p class="explanation">(leave blank to use default from site-wide settings)</p>
<?php endif; ?>

<?php if ($page->for_default_settings()) : ?>
<h4>(in between each post)</h4>
<textarea name="fnosp_email_inter" rows="1" cols="80" style="width: auto"<?php print esc_html($this->get_email_interstitial()); ?>></textarea>

<h4>(e-mail closing)</h4>
<textarea name="fnosp_email_suffix" rows="3" cols="80" style="width: auto"><?php print esc_html($this->get_email_suffix()); ?></textarea>
<?php endif; ?>
</td>
</tr>
</table>
<?php
	} /* FWPNotificationOfSyndicatedPosts::display_settings () */
	
	public function save_settings ($params, $page) {
		// Some of these are global-only settings.
		if ($page->for_default_settings()) :
			$page->update_setting('fnosp notify email', $params['fnosp_notify_email']);
			$page->update_setting('fnosp email prefix', $params['fnosp_email_prefix']);
			$page->update_setting('fnosp email inter', $params['fnosp_email_inter']);
			$page->update_setting('fnosp email suffix', $params['fnosp_email_suffix']);
			$page->update_setting('fnosp email subject', $params['fnosp_email_subject']);
			$page->update_setting('fnosp email format', $params['fnosp_email_format']);
			
		// And some are local-only settings
		else :
			$page->update_setting('fnosp add global stati', $params['fnosp_add_global_stati']);
		endif;
		
		if (strlen(trim($params['fnosp_email_template'])) > 0) :
			$page->update_setting('fnosp email template', $params['fnosp_email_template']);
		else :
			$page->update_setting('fnosp email template', $params['fnosp_email_template'], NULL);
		endif;
		
		if (!isset($params['fnosp_notify_on_status'])) :
			$fNOS = array();
		else :
			$fNOS = $params['fnosp_notify_on_status'];
		endif;
		
		$stati = array();
		foreach ($fNOS as $status => $on) :
			if ('yes' == $on) :
				$stati[] = $status;
			endif;
		endforeach;
			
		$page->update_setting('fnosp notify on status', serialize($stati));
		
	} /* FWPNotificationOfSyndicatedPosts::save_settings () */
	
	public function post_syndicated_item ($id, $post) {
		// We are in an update process and just inserted a new post.
		// Let's see if we need to tag it for notification at the end
		// of the update process.
		if ($this->shouldNotify($post)) :
			$link = $post->link;
			$foo = add_post_meta($id, FNOPS_NOTIFICATION_POSTMETA, $link->id());
		endif;
	} /* FWPNotificationOfSyndicatedPosts::post_syndicated_item () */

	public function feedwordpress_update_complete ($delta) {
		// Let's do this.
		$q = new WP_Query(array(
		'ignore_sticky_posts' => true,
		'post_type' => 'any',
		'post_status' => 'any',
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

