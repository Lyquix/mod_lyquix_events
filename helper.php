<?php
/**
 * @license GNU/GPL v2
 * @copyright  Copyright (c) Lyquix. All rights reserved.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

class modLyquixEventsHelper {
	static function getList(&$params) {
		
		// Load libraries
		require_once("components/com_flexicontent/classes/flexicontent.helper.php");
		require_once("components/com_flexicontent/helpers/permission.php");
		
		// Initialize
		global $mainframe;
		$mainframe = JFactory::getApplication();
		$db = JFactory::getDBO();
		$user = JFactory::getUser();
				
		// get null date and now
		$nullDate	= $db->getNullDate();
		$date = JFactory::getDate();
		$now = $date->toSql();
		$query = $db -> getQuery(true);
		// build query
		$query ->select ("c.id");
		$query ->from ("#__content AS c, #__flexicontent_cats_item_relations as fcir, #__flexicontent_fields_item_relations as ffir, #__flexicontent_items_ext as fie"); 
		$query-> where ("c.id = fcir.itemid AND c.id = fie.item_id AND c.state = 1 AND c.publish_up < ".$db->quote($now)." AND (c.publish_down = '0000-00-00 00:00:00' OR c.publish_down > ".$db->quote($now).")");
		
		// category scope
		if($params->get('cats')) {
			$query -> where("fcir.catid ".($params->get('cats_scope',0) ? "NOT " : "")."IN (".implode(",",$params->get('cats')).")");
		}
		
		// types scope
		if($params->get('types')) {
			$query -> where("fie.type_id ".($params->get('types_scope',0) ? "NOT " : "")."IN (".implode(",",$params->get('types')).")");
		}
		
		// current item scope
		if($params->get('item_scope',1) && JRequest::getVar('option') == 'com_flexicontent' && JRequest::getVar('id') > 0) {
			$query -> where("c.id <> ".current(explode(":", JRequest::getVar('id'))));
		}
		
		// language scope
		if($params->get('lang_scope',1)) {
			$lang = flexicontent_html::getUserCurrentLang();
			$query -> where("(fie.language = '*' OR fie.language LIKE '".$lang."%')");
		}
		
		// remove unauthorized items
		$gid = !FLEXI_J16GE ? (int)$user->get('aid') : max($user->getAuthorisedViewLevels());
		if(FLEXI_ACCESS && class_exists('FAccess')) {
			$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
			if (isset($readperms['item']) && count($readperms['item']) ) {
				$query -> where("(c.access <= ".$gid." OR c.id IN (".implode(",", $readperms['item'])."))");
			} 
			else {
				$query -> where("c.access <= ".$gid);
			}
		} 
		else {
			$query -> where("c.access <= ".$gid);
		}
		
		//Narrow by date fields
		$date_fields = $params->get('event_date_fields');
		if (isset($date_fields)){
			$query -> where("c.id = ffir.item_id");
			$x = 0;
			$lastchild = end($date_fields);
			foreach ($date_fields as $date_field){
				if ($x == 0){
					$date_query = ("(ffir.field_id = ".$date_fields[$x]);
				}else{
					$date_query .=(" OR ffir.field_id = ".$date_fields[$x]);	
				}
				$x++;
			}
			$date_query.=")";
			$query-> where($date_query);
			//Get either the current date, or a date set in the url
			$url_param = $params->get('url_parameter','date');
			
			$page_date = JRequest::getString($url_param);
			if (empty($page_date)){
				$page_date = JFactory::getDate();
				$page_date= DateTime::createFromFormat('Y-m-d H:i:s', $date)->format('Y-m-d');
			}
			//Select scope and range for dates
			$range_start = $params->get('range_start',0);
			print_r($range_start);
			$date_range = $params->get('date_range',0);
			$date_scope = $params->get('range_length',1);
			if ($range_start == 1){
				$week_start = $params->get('week_start',0);
				if ($week_start ==0){
					$page_date = date("Y-m-d", strtotime($page_date.' monday this week'));
				}else{
					$page_date = date("Y-m-d", strtotime($page_date.' sunday last week'));
				}	 
			}else if($range_start == 2){
				$page_date= DateTime::createFromFormat('Y-m-d', $page_date)->format('Y-m');
				$page_date = $page_date."-01";
			}
			$query -> where("ffir.value >= ".$db->quote($page_date));
			if ($date_range == 1){
				$date = date("Y-m-d", strtotime($page_date." +".$date_scope." day"));
				$query -> where("ffir.value <= ".$db->quote($date));
			}else if ($date_range == 2){
				$date = date("Y-m-d", strtotime($page_date." +".$date_scope." week"));
				$query -> where("ffir.value <= ".$db->quote($date));
			}else if ($date_range == 3){
				$date = date("Y-m-d", strtotime($page_date." +".$date_scope." month"));
				$query ->where("ffir.value <= ".$db->quote($date));
			}
			$query -> order("ffir.value ASC");		
			// Execute query
		}
		$db->setQuery($query, 0);
		$items = $db->loadColumn();
		return array_slice($items, 0, $params->get('count',5), true);
	}
}