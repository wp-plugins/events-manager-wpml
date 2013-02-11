<?php
//install tables
global  $wpdb;
$table_name = $wpdb->prefix.'em_wpml_events';

// Creating the events table
$sql = "CREATE TABLE ".$table_name." (
	em_wpml_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	post_id bigint(20) unsigned NOT NULL,
	event_id bigint(20) unsigned NOT NULL,
	master_event_id bigint(20) unsigned NOT NULL,
	PRIMARY KEY  (em_wpml_id),
	UNIQUE posts_index (post_id,event_id)
	) DEFAULT CHARSET=utf8 ";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

//update event master table from wpml translations table
if( $wpdb->get_var("SELECT COUNT(*) FROM $table_name") == 0 ){
    global $sitepress;
	//first we copy all the current events into the table
	$wpdb->query("INSERT INTO $table_name (post_id, event_id, master_event_id) SELECT post_id, event_id, event_id FROM {$wpdb->prefix}em_events");
	//then we find all translations, and update the table so translated events point to the original event
	$results = $wpdb->get_results("SELECT element_id,trid, language_code, source_language_code, event_id, event_name FROM {$wpdb->prefix}icl_translations t LEFT JOIN {$wpdb->prefix}em_events e ON t.element_id=e.post_id WHERE `element_type`='post_".EM_POST_TYPE_EVENT."' AND source_language_code IS NOT NULL;", ARRAY_A);
	foreach ($results as $translation){
	    //going through each translation of another post (assuming source_language is not null, therefore a translation)
	   	$post_id = EM_WPML::get_trid_original_post_id($translation['trid']);
	   	if( $post_id != $translation['element_id'] ){
	   	    //get the event id of the original post id
	   	    $event_id = $wpdb->get_var("SELECT event_id FROM ".EM_EVENTS_TABLE." WHERE post_id='$post_id'");
	   	    if(!empty($event_id)){
				$wpdb->query("UPDATE $table_name SET master_event_id='{$event_id}' WHERE post_id='{$translation['element_id']}'");
	   	    }
	   	}
	}
}