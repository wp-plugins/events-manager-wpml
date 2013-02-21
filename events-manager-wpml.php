<?php
/*
Plugin Name: Events Manager and WPML Compatibility
Version: 0.3
Plugin URI: http://wp-events-plugin.com
Description: Integrates the Events Manager and WPML plugins together to provide a smoother multilingual experience (EM and WPML also needed)
Author: Marcus Sykes
Author URI: http://wp-events-plugin.com
*/

/*
Copyright (c) 2012, Marcus Sykes

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/*
NUANCES

Certain things can't happen without twaks or just can't happen at all
- Recurring events
	- Recurring Events can't be translated when editing the recurrence template, they must be done one by one i.e. at single event level
	- Recurring events are disabled by default due to the above
- Taxonomies
	- Taxonomy translation links aren't going to show in the language switcher unless WPML decides to make SitePress::$wp_query a public property
	- Translation switcher options aren't all going to be reflected in taxonomies as $teplate_args isn't passed to filter icl_ls_languages
- Bookings
	- customn booking forms aren't translated
- Location Searching
	- currently autocompleter forces searches for current languages, we may want to change this in the future to search all languages but give precedence to showing the translated version if available
- Slugs
	- not translated, but this might not be something we should control
- Ical?
- RSS feeds?
- More unforseen issues
	- Both Events Manager and WPML are very complex plugin which hook into loads of places within WordPress to make things happen. That means a big combination of things to test, therefore many combinations may have been missed which result in unexpected behaviour. Please let us know of any other nuances you come across and we'll do our best to fix them as time permits.

EXTRA INSTALLATION STEPS

To close some gaps, extra steps are needed
- Pages
	- You should translate pages that EM overrides into different languages, meaning the pages you choose from our Events > Settings > Pages tab in the various panels, such as:
		- events page
		- locations page
		- categories page
		- edit events page
		- edit locations page
		- edit bookings page
		- my bookings page
 */

//TODO booking form override for translated languages
//TODO remove meta boxes on translations, times, locations, ect. remain the same across events
//TODO think about what to do with booking form content r.e. translations

//TODO better way of linking translated events to translations of locations

//TODO what happens if you create a language first in a second languge?

define('EM_WPML_VERSION','0.3');

//stores all master event info within a script run, to save repetitive db calls e.g. within an event format output operation.
$em_wpml_translation_index = array();
$em_wpml_master_event_ids_cache = array();
$em_wpml_master_events_cache = array();

class EM_WPML{
    public static function init(){
	    if( !class_exists('SitePress') || !defined('EM_VERSION') ) return; //only continue of both EM and WPML are activated
	    
	    //check installation
	    if( version_compare(EM_WPML_VERSION, get_option('em_wpml_version')) > 0 ){
	        em_wpml_activate();
	    }
	    
	    //continue initialization
		self::init_pages();
		self::init_saves();
		self::init_searches();
		self::init_recurring();
		self::init_master_events();
		self::init_options();
		
		//force disable recurring events
		if( !defined('EM_WMPL_FORCE_RECURRENCES') || !EM_WMPL_FORCE_RECURRENCES ){
			add_filter('option_dbem_recurrence_enabled', create_function('$value', 'return false;'));
			if( !get_option('em_wpml_disable_recurrence_notice') && is_admin() && current_user_can('activate_plugins') ){
			    global $pagenow;
			    if( !empty($_REQUEST['em_wpml_disable_recurrence_notice']) ){
			        update_option('em_wpml_disable_recurrence_notice',true);
			    }else{
				    add_action('admin_notices','EM_WPML::disable_recurrence_notice');
			    }
			}
		}		
		//rewrite language switcher links for our taxonomies if overriden with formats, because EM forces use of a page template by modifying the WP_Query object
		//this confuses WPML since it checks whether WP_Query is structured to show a taxonomy page
		add_filter('icl_ls_languages','EM_WPML::icl_ls_languages');
		//change some localized script vars
		add_filter('em_wp_localize_script', 'EM_WPML::em_wp_localize_script');
    }
    
    public static function em_wp_localize_script($em_localized_js){
        $em_localized_js['ajaxurl'] = admin_url('admin-ajax.php?lang='.ICL_LANGUAGE_CODE);
        $em_localized_js['locationajaxurl'] = admin_url('admin-ajax.php?action=locations_search&lang='.ICL_LANGUAGE_CODE);
        return $em_localized_js;
    }
    
    /**
     * Hooks into function SitePress::get_ls_languages() and accounts for EM overriding the event category/tag taxonomy pages. 
     * This function tweaks the $wp_query global, reruns SitePress::get_ls_languages() without hookinng in again, and then resets the $wp_query to what EM originally tweaks it to
     * Requires that you make SitePress::$wp_query a public property
     * @param unknown_type $langs
     */
    public static function icl_ls_languages($langs){
        global $wpdb, $sitepress, $wp_query;
        //TODO ask wpml to add $template_args to the icl_ls_languages filter
        //TODO ask wpml to make SitePress::$wp_query visible, otherwise this can't happen
        if( has_filter('the_content', array('EM_Tag_Taxonomy','the_content')) || has_filter('the_content', array('EM_Category_Taxonomy','the_content')) ){
			if( array_key_exists('wp_query', get_object_vars($sitepress)) ){
	            //we need to trick WPML into thinking this is a taxonomy, so jig the $wp_query values and re-run this function without running this filter the second time
	            $wp_query->is_tax = 1;
				$wp_query->is_page = 0;
				$wp_query->is_single = 1;
				$wp_query->is_singular = 0;
				$wp_query->is_archive = 1;
				$sitepress->wp_query = $wp_query;
	            remove_filter('icl_ls_languages','EM_WPML::icl_ls_languages');
	            //run function again
	            $langs = $sitepress->get_ls_languages();
	            //set everything back to "normal"
	            $wp_query->is_tax = 0;
				$wp_query->is_page = 1;
				$wp_query->is_single = 0;
				$wp_query->is_singular = 1;
				$wp_query->is_archive = 0;
	            add_filter('icl_ls_languages','EM_WPML::icl_ls_languages');
			}
        }
        return $langs;
    }
    
    /**
     * Notifies user of the fact that recurrences are disabled by default with this plugin activated 
     */
    public static function disable_recurrence_notice(){
		?>
		<div id="message" class="updated">
			<p><?php echo sprintf(__('Since you are using WPML, we have automatically disabled recurring events, your recurrences already created will act as normal single events. This is because recurrences are not compatible with WPML at the moment. If you really still want recurrences enabled, then you should add %s to your wp-config.php file. <a href="%s">Dismiss</a>','em-wpml'),'<code>define(\'EM_WMPL_FORCE_RECURRENCES\',true);</code>', add_query_arg(array('em_wpml_disable_recurrence_notice'=>1))); ?></p>
		</div>
		<?php
    }
    
	/*
	 * PAGES
	 * Pages overriden in EM won't be overriden if viewing their translated versions, these functions fix the problem
	 */    
    public static function init_pages(){
        add_filter('option_dbem_events_page','EM_WPML::get_translated_page');
        add_filter('option_dbem_locations_page','EM_WPML::get_translated_page');
        add_filter('option_dbem_categories_page','EM_WPML::get_translated_page');
        add_filter('option_dbem_edit_events_page','EM_WPML::get_translated_page');
        add_filter('option_dbem_edit_locations_page','EM_WPML::get_translated_page');
        add_filter('option_dbem_edit_bookings_page','EM_WPML::get_translated_page');
        add_filter('option_dbem_my_bookings_page','EM_WPML::get_translated_page');
    }
    
    /**
     * There's probably a WPML function somewhere that does this, but this returns the tranlated post id in current language of the supplied post_id, otherwise returns false.
     * @param int $post_id
     */
    public static function get_translation_source($post_id){
    	global $wpdb, $sitepress, $em_wpml_tranlsation_index;
    	if( !empty($em_wpml_tranlsation_index[$sitepress->get_current_language()][$post_id]) ) return $em_wpml_tranlsation_index[$sitepress->get_current_language()][$post_id];
    	//find translation of current post if exists
    	$trid = $sitepress->get_element_trid($post_id, 'post_page');
    	$translations = $sitepress->get_element_translations($trid, 'post_page');
    	if( count($translations) > 0 ){
    		$current_lang = $sitepress->get_current_language();
    		foreach( $translations as $translation ){
    			if( $translation->language_code == $current_lang ){
    			    $translated_id = $translation->element_id;
    			    break;
    			}
    		}
    	}
    	//$translated_id = $wpdb->get_var("SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id=$post_id AND language_code='{$sitepress->get_current_language()}'");
    	if( !empty($translated_id) ){
    		$em_wpml_tranlsation_index[$sitepress->get_current_language()][$post_id] = $translated_id;
    		return $translated_id;
    	}
    	return $post_id;
    }

    /**
     * Takes a post id, checks if the current language isn't the default language and returns a translated post id if it exists, used to switch our overriding pages
     * @param int $post_id
     * @return int
     */
    public static function get_translated_page($post_id){
    	global $sitepress;
    	if( $sitepress->get_current_language() != $sitepress->get_default_language() ){
    		$translated_id = self::get_translation_source($post_id);
    		if( $translated_id ){
    			return $translated_id;
    		}
    	}
    	return $post_id;
    }  


    /*
     * SAVE/EDIT FUNCTIONS
     * When an event or location is saved, we need to perform certain options depending whether saved on the front-end editor, or if saved/translated in the backend using WPML, since events share information across translations.
    */
    public static function init_saves(){
        add_filter('em_location_save','EM_WPML::location_save',10,2);
        add_filter('em_event_get_post','EM_WPML::event_get_post',10,2);
        add_filter('em_event_save','EM_WPML::event_save',10,2);
        add_filter('em_event_duplicate','EM_WPML::event_duplicate',10,2);
    }
    
    /**
     * Writes a record into the WPML translation tables if non-existent when a location has been added on the front-end
     * @param boolean $result
     * @param EM_Location $EM_Location
     * @return boolean
    */
    public static function location_save($result, $EM_Location){
    	global $wpdb;
    	if( !$wpdb->get_var("SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_id={$EM_Location->post_id}") ){
    		//save a value into WPML table
    		$wpdb->insert($wpdb->prefix.'icl_translations', array('element_type'=>"post_".EM_POST_TYPE_LOCATION, 'trid'=>$EM_Location->post_id, 'element_id'=>$EM_Location->post_id, 'language_code'=>ICL_LANGUAGE_CODE));
    	}
    	return $result;
    }
    
    /**
     * Hooks into em_event_get_post and writes the original event translation data into the current event, to avoid validation errors and correct data saving.
     * @param boolean $result
     * @param EM_Event $EM_Event
     * @return boolean
     */
    public static function event_get_post($result, $EM_Event){
        //check if this is a master event, if not then we need to get the relevant master event info and populate this object with it so it passes validation and saves correctly.
        $master_event_info = self::get_master_event_info($EM_Event->post_id, 'post_id');
        if( empty($master_event_info) && is_admin() && !empty($_REQUEST['icl_trid']) ){
            $post_id = self::get_trid_original_post_id($_REQUEST['icl_trid']);
            $M_EM_Event = em_get_event($post_id,'post_id'); //Grab POST data from the original event
            $master_event_info = array('post_id'=>$M_EM_Event->post_id, 'event_id'=>$M_EM_Event->event_id);
        }
        $EM_Event->master_event_id = $master_event_info['event_id'];
        if( $EM_Event->event_id != $master_event_info['event_id'] ){
            if( empty($M_EM_Event) ) $M_EM_Event = em_get_event($master_event_info['post_id'],'post_id'); //Grab POST data from the original event	
			$EM_Event->event_start_date  = $M_EM_Event->event_start_date ;
			$EM_Event->event_end_date  = $M_EM_Event->event_end_date ;
			$EM_Event->recurrence  = $M_EM_Event->recurrence ;
			$EM_Event->post_type  = $M_EM_Event->post_type ;
			$EM_Event->location_id  = $M_EM_Event->location_id ;
			if( $EM_Event->location_id == 0 ) $_POST['no_location'] = 1;
			$EM_Event->event_all_day  = $M_EM_Event->event_all_day ;
			$EM_Event->event_start_time  = $M_EM_Event->event_start_time ;
			$EM_Event->event_end_time  = $M_EM_Event->event_end_time ;
			$EM_Event->start  = $M_EM_Event->start ;
			$EM_Event->end  = $M_EM_Event->end ;
			$EM_Event->event_rsvp_date  = $M_EM_Event->event_rsvp_date ;
				
			$EM_Event->event_rsvp  = $M_EM_Event->event_rsvp ;
			$EM_Event->event_rsvp_time  = $M_EM_Event->event_rsvp_time ;
							
			$EM_Event->blog_id  = $M_EM_Event->blog_id ;
			$EM_Event->group_id  = $M_EM_Event->group_id ;
			$EM_Event->recurrence  = $M_EM_Event->recurrence ;
			$EM_Event->recurrence_freq  = $M_EM_Event->recurrence_freq ;
			$EM_Event->recurrence_byday  = $M_EM_Event->recurrence_byday ;
			$EM_Event->recurrence_interval  = $M_EM_Event->recurrence_interval ;
			$EM_Event->recurrence_byweekno  = $M_EM_Event->recurrence_byweekno ;
			$EM_Event->recurrence_days  = $M_EM_Event->recurrence_days ;
        }
        return $result;
    }
    
    /**
     * Writes a record into the WPML translation tables if non-existent when an has been added on the front-end, also decides whether this is the 'master event'
     * @param boolean $result
     * @param EM_Event $EM_Event
     * @return boolean
     */
    public static function event_save($result, $EM_Event){
		global $wpdb, $sitepress;
		if( !empty($EM_Event->post_id) && !empty($EM_Event->event_id) ){ //save this if a post id is saved, regarldess of whether the event is valid for publication
			//firstly, save a translation record if needed, e.g. via the front-end
			$trid = $sitepress->get_element_trid($EM_Event->post_id, 'post_'.EM_POST_TYPE_EVENT);
			if( empty($trid) ){
				//save a value into WPML table
				$wpdb->insert($wpdb->prefix.'icl_translations', array('element_type'=>"post_".EM_POST_TYPE_EVENT, 'trid'=>$EM_Event->post_id, 'element_id'=>$EM_Event->post_id, 'language_code'=>ICL_LANGUAGE_CODE));
			}
			$original_post_id = self::get_trid_original_post_id($trid);
			//check if this is a translation or the 'master' event and update the em_wpml_events table accordingly
			$master_event_id = self::get_master_event_id($EM_Event->event_id);
			//first get any info from the master events table related to this post
			if( empty($master_event_id) ){
				//if no master info exists, this is either a new event i.e. the master, or a new translation of another event, let's figure out which one				    
			    if( !empty($original_post_id) ){
					//this is a translation, get the master info and add a new record for this sub-event
				    $sql = $wpdb->prepare("SELECT master_event_id FROM {$wpdb->prefix}em_wpml_events WHERE post_id=%d", $original_post_id);
				    $master_event_id = $wpdb->get_var($sql);
				    if( !empty($master_event_id) ){
				        //translation with an established master, so insert/update the record making this the slave event
					    $sql = $wpdb->prepare("SELECT em_wpml_id FROM {$wpdb->prefix}em_wpml_events WHERE post_id=%d", $EM_Event->post_id);
					    $em_wpml_id = $wpdb->get_var($sql, ARRAY_A);
				        if( empty($em_wpml_id) ){
							$wpdb->insert($wpdb->prefix.'em_wpml_events', array('post_id'=>$EM_Event->post_id,'event_id'=>$EM_Event->event_id, 'master_event_id'=>$master_event_id));
				        }else{
				            $wpdb->update($wpdb->prefix.'em_wpml_events', array('post_id'=>$EM_Event->post_id,'event_id'=>$EM_Event->event_id, 'master_event_id'=>$master_event_id), array('em_wpml_id'=>$em_wpml_id));
				        }
				    }
			    }
				if( empty($master_event_id) ){
				    //not a translation, still no master event id, so add a new record, this is the master
				    $wpdb->insert($wpdb->prefix.'em_wpml_events', array('post_id'=>$EM_Event->post_id,'event_id'=>$EM_Event->event_id, 'master_event_id'=>$EM_Event->event_id));
				    $master_id = $EM_Event->event_id;
				}			
			}
			//if there is a master id, that means a record already exists
			//TODO update the event master info in case user has changed what event this is a translation of
			
			//if this is the master event, we should update the sub-events with the relevant information so they are correctly found in searches
			if( $master_event_id == $EM_Event->event_id ){
			    
			}
		}		
		return $result;
	}
    
    /**
     * Writes a record into the WPML translation tables if non-existent when an event has been added on the front-end
     * @param boolean $result
     * @param EM_Event $EM_Event
     * @return boolean
     */
    public static function event_duplicate($result, $EM_Event){
    	global $wpdb;
    	if( $result && !$wpdb->get_var("SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_id={$EM_Event->post_id}") ){
    		//save a value into WPML table
    		$wpdb->insert($wpdb->prefix.'icl_translations', array('element_type'=>"post_".EM_POST_TYPE_EVENT, 'trid'=>$EM_Event->post_id, 'element_id'=>$EM_Event->post_id, 'language_code'=>ICL_LANGUAGE_CODE));
    	}
    	return $result;
    }
    
	/*
	* MODIFYING EVENT/LOCATION LIST SEARCHES
	*/
    
    public static function init_searches(){
        add_filter('em_event','EM_WPML::event_load');
        add_filter('em_events_build_sql_conditions','EM_WPML::event_searches',10,2);
        add_filter('em_locations_build_sql_conditions','EM_WPML::location_searches',10,2);
		add_filter('em_actions_locations_search_cond','EM_WPML::location_searches_autocompleter'); //will work as of EM 5.3.3
    }
    
    /**
     * Changes necessary event properties when an event is instantiated.
     * Specifically, modifies the location id to the currently translated location if applicable.
     * @param EM_Event $EM_Event
     */
    public static function event_load( $EM_Event ){
        if( $EM_Event->location_id ){
            $translated_post_id = self::get_translated_location_post_id($EM_Event->location_id);
            if( $translated_post_id && $EM_Event->get_location()->post_id != $translated_post_id ){
                $EM_Event->location = em_get_location($translated_post_id, 'post_id');
                $EM_Event->location_id = $EM_Event->location->location_id;
            }
        }
    }
	
	/**
	 * Adds an extra condition to filter out events translated in the current language
	 * @param array $conditions
	 * @param array $args
	 * @return string
	 */
	public static function event_searches($conditions, $args){
		global $wpdb;
		if( defined('ICL_LANGUAGE_CODE') ){
			$conditions['wpml'] = EM_EVENTS_TABLE.'.post_id IN (SELECT element_id FROM '.$wpdb->prefix."icl_translations WHERE language_code ='".ICL_LANGUAGE_CODE."' AND element_type='post_".EM_POST_TYPE_EVENT."')";
		}
		return $conditions;
	}
	
	
	/**
	 * Adds an extra condition to filter out locations translated in the current language
	 * @param array $conditions
	 * @param array $args
	 * @return string
	 */
	public static function location_searches($conditions, $args){
		global $wpdb;
		if( defined('ICL_LANGUAGE_CODE') ){
			$conditions['wpml'] = EM_LOCATIONS_TABLE.'.post_id IN (SELECT element_id FROM '.$wpdb->prefix."icl_translations WHERE language_code ='".ICL_LANGUAGE_CODE."' AND element_type='post_".EM_POST_TYPE_LOCATION."')";
		}
		return $conditions;
	}
	
	/**
	 * Checks location search according  
	 * @param unknown_type $location_conds
	 * @return string
	 */
	public static function location_searches_autocompleter($location_conds){
		global $wpdb;
		if( defined('ICL_LANGUAGE_CODE') ){
			$location_conds .= " AND ".EM_LOCATIONS_TABLE.'.post_id IN (SELECT element_id FROM '.$wpdb->prefix."icl_translations WHERE language_code ='".ICL_LANGUAGE_CODE."' AND element_type='post_".EM_POST_TYPE_LOCATION."')";
		}
		return $location_conds;
	}
	
	/*
	 * EVENT DATA SHARING
	 * Event translations will assign one event to be the 'master' event, meaning bookings and event times will be managed by the 'master' event.
	 * Since WPML can change default languages and you can create events in non-default languages first, the first language will be the 'master' event.
	 * If an event is deleted and is the master event, but there are still other translated, the master event is reassigned to the default language translation, or whichever other event is found first
	 */
	

	/*
	 * RECURRING EVENTS
	* WARNING - Given that recurrences create seperate individual events from a different post type, it's pretty much impossible as is to reliably target a translation since WPML requires a new recurring event post to be created. For that reason it's advised you disable recurring events.
	*/
	public static function init_recurring(){
	    add_filter('em_event_save_events','EM_WPML::em_event_save_events',10,4);
	    add_filter('delete_events','EM_WPML::delete_events', 10,3);
	}
	
	/**
	 * Adds records into WPML translation tables when a recurring event is created.
	 *
	 * @param boolean $result
	 * @param unknown_type $EM_Event
	 * @param unknown_type $event_ids
	 * @param unknown_type $post_ids
	 * @return unknown
	 */
	public static function em_event_save_events($result, $EM_Event, $event_ids, $post_ids){
		global $wpdb;
		if($result){
			$inserts = array();
			$sitepress_options = get_option('icl_sitepress_settings');
			$lang = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id={$EM_Event->post_id}");
			if( empty($lang) ) $lang = $sitepress_options['default_language'];
			foreach($post_ids as $post_id){
				if( !$wpdb->get_var("SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE (element_id={$post_id} OR trid={$post_id}) AND language_code='{$lang}'") ){
					//save a value into WPML table
					$inserts[] = $wpdb->prepare("('post_".EM_POST_TYPE_EVENT."', %d, %d, '%s')", array($post_id, $post_id, $lang));
				}
			}
			if( count($inserts) > 0 ){
				//$wpdb->insert($wpdb->prefix.'icl_translations', array('element_type'=>"post_".EM_POST_TYPE_EVENT, 'trid'=>$post_id, 'element_id'=>$post_id, 'language_code'=>$lang));
				$wpdb->query("INSERT INTO ".$wpdb->prefix."icl_translations (element_type, trid, element_id, language_code) VALUES ".implode(',', $inserts));
			}
		}
		return $result;
	}
	
	public static function delete_events($result, $EM_Event, $events){
		global $wpdb;
		if($result){
			$post_ids = array();
			foreach($events as $event){
				$post_ids[] = $event->post_id;
			}
			if( count($post_ids) > 0 ){
				//$wpdb->insert($wpdb->prefix.'icl_translations', array('element_type'=>"post_".EM_POST_TYPE_EVENT, 'trid'=>$post_id, 'element_id'=>$post_id, 'language_code'=>$lang));
				$wpdb->query("DELETE FROM ".$wpdb->prefix."icl_translations WHERE element_id IN (".implode(',',$post_ids).")");
			}
		}
		return $result;
	}
	
	/*
	 * EVENT time/booking data management
	 */
	public static function init_master_events(){
		add_filter('em_event_output_placeholder','EM_WPML::override_placeholders',100,3); //override bookign form
		add_filter('em_event_get_bookings','EM_WPML::override_bookings',100,2);
		add_action('em_event_delete_meta_event_pre', 'EM_WPML::check_event_deletions', 10, 1);
	}
	
	public static function check_event_deletions($EM_Event){
		global $wpdb, $sitepress;
		if( self::is_master_event($EM_Event) ){
			//check to see if there's any translations of this event
			$trid = $sitepress->get_element_trid($EM_Event->post_id, 'post_'.EM_POST_TYPE_EVENT);
			$translations = $sitepress->get_element_translations($trid, 'post_'.EM_POST_TYPE_EVENT);
			//if so check if the default language still exists
			if( count($translations) > 0 ){
				$default_lang = $sitepress->get_default_language();
				foreach( $translations as $translation ){
					if( $translation->element_id != $EM_Event->post_id ){
						//if not, use the first available translation we find
						$new_master_event_post_id =  $translation->element_id;
						if( $translation->original ) break;
						if( $translation->language_code == $translation->language_code ) break;
					}
				}
				//make that translation the master event by changing event ids of bookings, tickets etc. to the new master event
				if( !empty($new_master_event_post_id) ){
					$M_EM_Event = em_get_event($new_master_event_post_id,'post_id');
					if( !empty($M_EM_Event->event_id) ){
						$wpdb->update($wpdb->prefix.'em_wpml_events', array('master_event_id'=>$M_EM_Event->event_id), array('master_event_id'=>$EM_Event->event_id));
						$wpdb->update(EM_TICKETS_TABLE, array('event_id'=>$M_EM_Event->event_id), array('event_id'=>$EM_Event->event_id));
						$wpdb->update(EM_BOOKINGS_TABLE, array('event_id'=>$M_EM_Event->event_id), array('event_id'=>$EM_Event->event_id));
					}
				}
			}
		}
		$wpdb->delete($wpdb->prefix.'em_wpml_events', array('event_id'=>$EM_Event->event_id));
	}
	
	public static function override_bookings($EM_Bookings, $EM_Event){
		if( $EM_Bookings->event_id == $EM_Event->event_id ){ //no point doing extra work if event ids are different, since it means this was already done
			$master_event_id = self::get_master_event_id($EM_Event->event_id);
			if( $EM_Bookings->event_id != $EM_Event->event_id ){
				$M_EM_Event = em_get_event(self::get_master_event_id($EM_Event->event_id));
				return $M_EM_Event->get_bookings();
			}
		}
		return $EM_Bookings;
	}
	
	/**
	 * Certain placeholders, specifically booking placeholders, will take information from the original event, so we generate the 
	 * @param string $replace
	 * @param EM_Event $EM_Event
	 * @param string $full_result
	 * @return string
	 */
	public static function override_placeholders($replace, $EM_Event, $full_result){
		global $em_wpml_master_events_cache;
		if( in_array($full_result, array('#_BOOKINGFORM','#_BOOKINGFORMBUTTON', '#_ATTENDEES','#_ATTENDEESLIST','#_ATTENDEESPENDINGLIST','#_EVENTPRICEMIN','#_EVENTPRICEMAX','#_AVAILABLESPACES','#_BOOKEDSPACES','#_PENDINGSPACES','#_SPACES','#_EVENTPRICERANGE')) ){
			$master_event_info = self::get_master_event_info($EM_Event->ID,'post_id'); //get the master event info, for later use
			if( is_array($master_event_info) && $EM_Event->event_id != $master_event_info['event_id'] ){
				if( !empty($em_wpml_master_events_cache[$master_event_info['event_id']]) ){
					$M_EM_Event = $em_wpml_master_events_cache[$master_event_info['event_id']];
				}else{
					$M_EM_Event = em_get_event($master_event_info['post_id'],'post_id'); //use post id because we make use of WP caching of posts
					$em_wpml_master_events_cache[$master_event_info['event_id']] = $M_EM_Event;
				}
				return $M_EM_Event->output($full_result);
			}
		}
		return $replace;
	}
	
	/**
	 * Checks the master events table to see if the supplied event object has a master event id.
	 * Parameters require id and optionally the type of id to search e.g. post_id, event_id (default is event_id) 
	 * Returns false if no master id is identified.
	 * @param int $id
	 * @param string $type
	 * @return int|false
	 */
	public static function get_master_event_id($id, $type="event_id"){
		global $wpdb, $em_wpml_master_event_ids_cache;
		$type = ($type == 'post_id') ? $type:'event_id';
		if( isset($em_wpml_master_event_ids_cache[$type][$id]) ) return $em_wpml_master_event_ids_cache[$type][$id]; //retrieve cached version
		$sql = $wpdb->prepare("SELECT master_event_id FROM {$wpdb->prefix}em_wpml_events WHERE $type=%d", $id);
		$event_master_info = $wpdb->get_row($sql, ARRAY_A);
		if( is_array($event_master_info) ){
			$em_wpml_master_event_ids_cache[$type][$id] = $event_master_info['master_event_id'];
			return $event_master_info['master_event_id'];
		}
		$em_wpml_master_event_ids_cache[$type][$id] = false;
		return false;
	}
	
	/**
	 * Gets all the information about the master event stored in the wp_em_wpml_events table. 
	 * Parameters require id and optionally the type of id to search e.g. post_id, event_id (default is event_id)
	 * Returns an array containing post_id and event_id. Returns false if no info found.
	 * @param int $id
	 * @param string $type
	 * @return array|boolean
	 */
	public static function get_master_event_info($id, $type = 'event_id'){
		global $wpdb;
		$type = ($type == 'post_id') ? $type:'event_id';
		$sql = $wpdb->prepare("SELECT post_id, event_id FROM {$wpdb->prefix}em_wpml_events WHERE event_id=(SELECT master_event_id FROM {$wpdb->prefix}em_wpml_events WHERE $type=%d)", $id);
		$event_master_info = $wpdb->get_row($sql, ARRAY_A);
		if( is_array($event_master_info) ){
			return $event_master_info;
		}
		return false;
	}
	
	/**
	 * Gets the post id of the location into the current language, returns same id if current language is the same or if no translation exists.
	 * @param int $id
	 * @param string $type
	 * @return int
	 */
	public static function get_translated_location_post_id($id, $type = 'location_id'){
		global $wpdb, $sitepress;
		$type = ($type == 'post_id') ? $type:'location_id';
		$post_id = false;
		if( $type == 'location_id' ){
			//we need the post id for locations, since all is stored as meta
			$post_id = $wpdb->get_var($wpdb->prepare('SELECT post_id FROM '.EM_LOCATIONS_TABLE." WHERE location_id=%d",$id));
		}else{
			$post_id = $id;
		}
		if( !empty($post_id) ){
			$trid = $sitepress->get_element_trid($post_id, 'post_'.EM_POST_TYPE_LOCATION);
			$translations = $sitepress->get_element_translations($trid, 'post_'.EM_POST_TYPE_LOCATION, true);
			if( !empty($translations[ICL_LANGUAGE_CODE]) ){
				return $translations[ICL_LANGUAGE_CODE]->element_id;
			}
		}
		return $post_id;
	}
	
	/**
	 * Checks the WPML table for the trid identifier and returns the correct post id for the original post, meaning the first created post in this translation set.
	 * If an original isn't found the default language translation post id is returned. Returns false if nothing is found (meaning something is probably wrong, or this is a brand new location/event/post)
	 * @param int $trid
	 * @return int
	 */
	public static function get_trid_original_post_id($trid){
		global $sitepress;
		$original_post_id = false;
		$translations = $sitepress->get_element_translations($trid, 'post_'.EM_POST_TYPE_EVENT);
		//echo "<pre>"; print_r($translations); echo "</pre>"; die();
		foreach($translations as $translation){
			if( !empty($translation->original) ){
				$original_post_id = $translation->element_id;
			}
		}
		if( empty($original_post_id) ){
			//make the default language the true trid for master purposes
			if( !empty($translations[$sitepress->get_default_language()]->element_id) ){
				$original_post_id = $translations[$sitepress->get_default_language()]->element_id;
			}
		}
		return $original_post_id;
	}
	
	/**
	 * Checks if this EM_Event object is the 'master' event, accounts for empty/new event objects
	 * @param EM_Event $EM_Event
	 * @return boolean
	 */
	public static function is_master_event( $EM_Event ){
		global $wpdb;
		if( !empty($EM_Event->event_id) ){
			$event_id = self::get_master_event_id($EM_Event->event_id);
			return $EM_Event->event_id == $event_id;
		}elseif( is_admin() && !empty($_REQUEST['trid']) ){
			return false;
		}
		return true;
	}
	
	/*
	 * TRANSLATABLE OPTIONS
	 */
	public static function init_options(){
		add_filter('em_ml_langs','EM_WPML::em_ml_langs');
		add_filter('em_ml_wplang','EM_WPML::em_ml_wplang');
	}
	
	public static function em_ml_langs(){
		global $sitepress, $wpdb;
		$sitepress_langs = $sitepress->get_active_languages();
		$sitpress_full_langs = $wpdb->get_results("SELECT code, default_locale FROM {$wpdb->prefix}icl_languages WHERE code IN ('".implode("','",array_keys($sitepress_langs))."')", ARRAY_A);
		$langs = array();
		foreach($sitpress_full_langs as $lang){
			$langs[$lang['default_locale']] = $sitepress_langs[$lang['code']]['display_name'];
		}
		return $langs;
	}
	
	public static function em_ml_wplang(){
		global $sitepress,$wpdb;
		$sitepress_lang = $sitepress->get_default_language();
		return $wpdb->get_var("SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE code='$sitepress_lang'");
	}
}
add_action('plugins_loaded', 'EM_WPML::init');

if( is_admin() ){
	include_once(dirname( __FILE__ ).'/em-wpml-admin.php');
}

function em_wpml_activate() {
	include_once(dirname( __FILE__ ).'/em-wpml-install.php');
}
register_activation_hook( __FILE__,'em_wpml_activate');