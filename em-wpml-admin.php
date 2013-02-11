<?php
class EM_WPML_Admin{
	public static function init(){
	    if( !class_exists('SitePress') || !defined('EM_VERSION') ) return; //only continue of both EM and WPML are activated
		global $pagenow;
		add_filter('em_event_save_meta_pre','EM_WPML_Admin::event_save_meta_pre',10,2);
		add_filter('em_location_save_pre','EM_WPML_Admin::location_save_pre',10,2);
		add_action('add_meta_boxes', 'EM_WPML_Admin::meta_boxes',100);
	}
	
	public static function meta_boxes(){
	    global $EM_Event, $sitepress;
	    //decide if it's a master event, if not then hide the meta boxes
	    if( !EM_WPML::is_master_event($EM_Event) ){
		    remove_meta_box('em-event-when', EM_POST_TYPE_EVENT, 'side');
	    	remove_meta_box('em-event-where', EM_POST_TYPE_EVENT, 'normal');
		    remove_meta_box('em-event-bookings', EM_POST_TYPE_EVENT, 'normal');
		    remove_meta_box('em-event-bookings-stats', EM_POST_TYPE_EVENT, 'side');
		    add_meta_box('em-event-wpml', __('Translated Event Information','dbem'), array('EM_WPML_Admin','event_meta_box'),EM_POST_TYPE_EVENT, 'side','high');
	    }
	}
	
	public static function event_meta_box(){
	    global $EM_Event;
	    ?>
	    <input type="hidden" name="_emnonce" value="<?php echo wp_create_nonce('edit_event'); ?>" />
	    <p>
	    	<?php
	    	if( !empty($EM_Event->event_id) ){
		    	$master_event_link = em_get_event(EM_WPML::get_master_event_id($EM_Event->event_id))->get_edit_url();
		    }elseif( !empty($_REQUEST['trid']) ){
				$post_id = EM_WPML::get_trid_original_post_id($_REQUEST['trid']);
				$master_event_link = em_get_event($post_id,'post_id')->get_edit_url();
			}
			echo sprintf(__('This is a translated event, therefore your time, location and booking information is handled by your <a href="%s">originally created event</a>.', 'em-wpml'), $master_event_link);
	    	?>
	    </p>
	    <?php
	}

	/**
	 * Saves a new location index record if this is a new languge addition
	 * @param boolean $result
	 * @param EM_Location $EM_Location
	 */
	public static function location_save_pre($EM_Location){
		global $wpdb;
	}

	/**
	 * Saves a new location index record if this is a new languge addition
	 * @param boolean $result
	 * @param EM_Event $EM_Event
	 */
	public static function event_save_meta_pre($EM_Event){
		global $wpdb, $post;
		if( $EM_Event->post_id != $post->ID ){
			//different language, make sure we don't have the same event_id as the original language
			$event = em_get_event($EM_Event->event_id); //gets the true event
			if( $EM_Event->post_id == $event->post_id ){
				//we have a dupe, so we need to reset the event id and the post id here
				$EM_Event->post_id = $post->ID;
				$EM_Event->event_id = null;
				update_post_meta($post->ID, '_post_id', $post->ID);
				update_post_meta($post->ID, '_event_id', '');
				$EM_Event->load_postdata($post,'post_id');
			}
		}
	}
}
add_action('plugins_loaded', 'EM_WPML_Admin::init');