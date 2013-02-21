<?php
function em_wpml_create_translations_table(){
	global  $wpdb;
	$table_name = $wpdb->prefix.'em_wpml_events';
	// Creating the events table
	$sql = "CREATE TABLE ".$table_name." (
		em_wpml_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		post_id bigint(20) unsigned NOT NULL,
		event_id bigint(20) unsigned NOT NULL,
		master_event_id bigint(20) unsigned NOT NULL,
		PRIMARY KEY  (em_wpml_id)
		) DEFAULT CHARSET=utf8 ";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	em_wpml_sort_out_table_nu_keys($table_name, array('post_id','event_id'));
	
	if( !get_option('em_wpml_version') ){
		//update event master table from wpml translations table first time round
		if( $wpdb->get_var("SELECT COUNT(*) FROM $table_name") == 0 ){
		    global $sitepress;
			//first we copy all the current events into the table
			if( EM_MS_GLOBAL ){
				$wpdb->query("INSERT INTO $table_name (post_id, event_id, master_event_id) SELECT post_id, event_id, event_id FROM ".EM_EVENTS_TABLE." WHERE blog_id=".get_current_blog_id());
			}else{
			    $wpdb->query("INSERT INTO $table_name (post_id, event_id, master_event_id) SELECT post_id, event_id, event_id FROM ".EM_EVENTS_TABLE);
			}
			//then we find all translations, and update the table so translated events point to the original event
			$results = $wpdb->get_results("SELECT element_id,trid, language_code, source_language_code, event_id, event_name FROM {$wpdb->prefix}icl_translations t LEFT JOIN ".EM_EVENTS_TABLE." e ON t.element_id=e.post_id WHERE `element_type`='post_".EM_POST_TYPE_EVENT."' AND source_language_code IS NOT NULL;", ARRAY_A);
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
	}
}



/**
 * Magic function that takes a table name and cleans all non-unique keys not present in the $clean_keys array. if no array is supplied, all but the primary key is removed.
 * @param string $table_name
 * @param array $clean_keys
 */
function em_wpml_sort_out_table_nu_keys($table_name, $clean_keys = array()){
	global $wpdb;
	//sort out the keys
	$new_keys = $clean_keys;
	$table_key_changes = array();
	$table_keys = $wpdb->get_results("SHOW KEYS FROM $table_name WHERE Key_name != 'PRIMARY'", ARRAY_A);
	foreach($table_keys as $table_key_row){
		if( !in_array($table_key_row['Key_name'], $clean_keys) ){
			$table_key_changes[] = "ALTER TABLE $table_name DROP INDEX ".$table_key_row['Key_name'];
		}elseif( in_array($table_key_row['Key_name'], $clean_keys) ){
			foreach($clean_keys as $key => $clean_key){
				if($table_key_row['Key_name'] == $clean_key){
					unset($new_keys[$key]);
				}
			}
		}
	}
	//delete duplicates
	foreach($table_key_changes as $sql){
		$wpdb->query($sql);
	}
	//add new keys
	foreach($new_keys as $key){
		$wpdb->query("ALTER TABLE $table_name ADD INDEX ($key)");
	}
}

//install tables
em_wpml_create_translations_table();
update_option('em_wpml_version', EM_WPML_VERSION);