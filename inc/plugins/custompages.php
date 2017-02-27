<?php

/*
Custom Pages Plugin for MyBB
Copyright (C) 2014 Kamil Krupa

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License v3 as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if(!defined("IN_MYBB")) die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");

$plugins -> add_hook('index_start', 'custompages_show');
$plugins -> add_hook('build_friendly_wol_location_end','custompages_setonlinestate');
$plugins -> add_hook('admin_config_menu', 'custompages_acpnav');
$plugins -> add_hook('admin_config_action_handler', 'custompages_actionhandler');
$plugins -> add_hook("admin_load", "custompages_admin");

function custompages_info() {
	return array(
		"name"			=> "Custom Pages",
		"description"	=> "Add custom pages (based on your own templates) to your forum.",
		"website"		=> "http://krupson.eu/mybb/custompages/",
		"author"		=> "Kamil \"Krupson\" Krupa",
		"authorsite"	=> "http://krupson.eu/",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility" => "1*"
	);
}

function custompages_install() {
	global $db;
	$db->query("CREATE TABLE `".TABLE_PREFIX."custompages` (
		`pid` int(10) unsigned NOT NULL auto_increment,
		`title` varchar(120) NOT NULL default '',
		`url` varchar(30) NOT NULL default '',
		`content` text NOT NULL,
		`bbcode` int(1) NOT NULL default '1',
		`template` text NOT NULL,
		`allowed_usergroups` text NOT NULL,
		`enabled` int(1) NOT NULL default '1',
		`parse_variables` int(1) NOT NULL default '1',
		`show_in_online` int(1) NOT NULL default '1',
		PRIMARY KEY (`pid`),
		UNIQUE KEY `url` (`url`)
	) ENGINE=MyISAM");
	
	$template = '<html>
	<head>
		<title>{$mybb->settings[\'bbname\']} - {$custompages_title}</title>
		{$headerinclude}
		<script type="text/javascript">
		<!--
			lang.no_new_posts = "{$lang->no_new_posts}";
			lang.click_mark_read = "{$lang->click_mark_read}";
		// -->
		</script>
	</head>
	<body>
		{$header}

			<h1>{$custompages_title}</h1>
			{$custompages_content}

		<br style="clear: both" />
		{$footer}
	</body>
</html>';
	
	$template = array(
		'title' => 'custompages_default',
		'template' => $db->escape_string($template),
		'sid' => '-1',
		'version' => '',
		'dateline' => time()
	);

	$db->insert_query('templates', $template);
}

function custompages_is_installed() {
	global $db;
	if($db->table_exists("custompages")) return true;
	return false;
}

function custompages_uninstall() {
	global $db;
	$db->drop_table('custompages');
	$db->delete_query("templates", "title = 'custompages_default'");
}

function custompages_show() {
	global $templates, $mybb, $db;
	
	if(isset($mybb -> input['page'])) {
		if(preg_match('/^[a-z0-9_]*$/i', $mybb -> input['page'])) {
			$q = $db -> simple_select("custompages", "*", "url='{$mybb -> input['page']}'");
			if($db -> num_rows($q) > 0) {
				$result = $db -> fetch_array($q);
				if($result['enabled']) {
				
					if($result['allowed_usergroups']) {
						$isAllowed = false;
						if($mybb -> user['additionalgroups']) {
							$usergroups = explode(",", $mybb -> user['usergroup'] . "," . $mybb -> user['additionalgroups']);
						} else {
							$usergroups = array($mybb -> user['usergroup']);
						}
						
						$allowedUsergroups = explode(",", $result['allowed_usergroups']);
						
						if(count(array_intersect($usergroups, $allowedUsergroups)) > 0) {
							$isAllowed = true;
						}
						
					} else {
						$isAllowed = true;
					}
					
					if($result['template']) {
						$custompages_template = $result['template'];
					} else {
						$custompages_template = "custompages_default";
					}
					
					$custompages_title = $result['title'];
					
					if($isAllowed) {
						$custompages_content = $result['content'];
					
						if($result['bbcode']) {
							global $parser;
							$custompages_content = $parser->parse_message($custompages_content, array('allow_mycode' => 1, 'allow_smilies' => 1));
						}
						if($result['parse_variables']) {
							$custompages_content = custompages_parsevariables($custompages_content, $result['bbcode']);
						}
					} else {
						$custompages_title = "Permission denied";
						$custompages_content = "You are not allowed to view this page.";
					}

				}
				unset($q, $result, $usergroups, $isAllowed, $allowedUsergroups);
			}
		}
	}
	
	if(isset($custompages_content) && isset($custompages_title) && isset($custompages_template)) {
		if(preg_match_all('/\{(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}|\{(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)->.*}|\{(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\[.*}/iU', $templates -> get($custompages_template), $variables)) {
			$variables = array_merge($variables[1],$variables[2],$variables[3]);
			$variables = array_unique($variables);
			foreach($variables as $id => $variable) {
				if($variable == '$custompages_content' || $variable == '$custompages_title' || $variable == '') {
					unset($variables[$id]);
				}
			}
			$variables = implode(",", $variables);
			eval("global {$variables};");
		}
		
		add_breadcrumb($custompages_title);
		
		eval("\$custompages = \"".$templates -> get($custompages_template)."\";");
		output_page($custompages);
		die();
	}
}

function custompages_acpnav(&$sub_menu) {
	$sub_menu[] = array(
		"id" => "custompages",
		"title" => "Custom Pages",
		"link" => "index.php?module=config-custompages"
	);
}

function custompages_actionhandler(&$action) {
	$action['custompages'] = array('active' => 'custompages');
}

function custompages_admin() {
	global $page, $mybb;
	if($page->active_action != "custompages") return 0;
	
	$page->add_breadcrumb_item("Custom Pages");
	
	$sub_tabs['list'] = array(
		'title' => "List Custom Pages",
		'link' => "index.php?module=config-custompages",
		'description' => "This section allows you to manage custom pages on your board."
	);
	
	$sub_tabs['add'] = array(
		'title' => "Add Custom Page",
		'link' => "index.php?module=config-custompages&amp;action=add",
		'description' => "This section allows you to add a new custom page to your board."
	);

	
	switch($mybb -> input['action']) {
		case 'add': custompages_add($sub_tabs); break;
		case 'edit': custompages_edit($sub_tabs); break;
		case 'delete': custompages_delete($sub_tabs); break;
		case 'switch_state': custompages_switch_state($sub_tabs); break;
		
		default: custompages_list($sub_tabs);
	}
	
	$page->output_footer();
	die();
}

	function custompages_add($sub_tabs) {
		global $db, $page, $mybb;
		
		if($mybb -> input['submitbutton']) {
			$errors = custompages_verifydata($mybb -> input);
			if($errors) {
				flash_message($errors, 'error');
			} else {
				$items = array(
					"title" => $db -> escape_string( $mybb -> input['title'] ),
					"url" => $db -> escape_string( $mybb -> input['url'] ),
					"allowed_usergroups" => $db -> escape_string( $mybb -> input['allowed_usergroups'] ),
					"template" => $db -> escape_string( $mybb -> input['template'] ),
					"content" => $db -> escape_string( $mybb -> input['content'] ),
					"bbcode" => (int) $mybb -> input['bbcode'],
					"enabled" => (int) $mybb -> input['enabled'],
					"parse_variables" => (int) $mybb -> input['parse_variables'],
					"show_in_online" => (int) $mybb -> input['show_in_online']
				);
			
				$q = $db -> insert_query("custompages", $items);
		
				if($db->error_number($q)) {
					flash_message("An error occured when trying to add custom page", 'error');
				} else {
					flash_message("Successfully added custom page", 'success');
				}
				
				admin_redirect("index.php?module=config-custompages");
			}
		}
		
		$page->add_breadcrumb_item("Add Custom Page");
		$page->output_header("Custom Pages");
		$page->output_nav_tabs($sub_tabs, "add");
		
		custompages_addedit_form($mybb -> input, "Add", "add");
	}
	
	function custompages_edit($sub_tabs) {
		global $db, $page, $mybb;
		
		$pid = (int) $mybb -> input['id'];
		
		if($mybb -> input['submitbutton']) {
			$errors = custompages_verifydata($mybb -> input);
			if($errors) {
				flash_message($errors, 'error');
			} else {
				$items = array(
					"title" => $db -> escape_string( $mybb -> input['title'] ),
					"url" => $db -> escape_string( $mybb -> input['url'] ),
					"allowed_usergroups" => $db -> escape_string( $mybb -> input['allowed_usergroups'] ),
					"template" => $db -> escape_string( $mybb -> input['template'] ),
					"content" => $db -> escape_string( $mybb -> input['content'] ),
					"bbcode" => (int) $mybb -> input['bbcode'],
					"enabled" => (int) $mybb -> input['enabled'],
					"parse_variables" => (int) $mybb -> input['parse_variables'],
					"show_in_online" => (int) $mybb -> input['show_in_online']
				);
			
				$q = $db -> update_query("custompages", $items, "pid = '{$pid}'");
		
				if($db->error_number($q)) {
					flash_message("An error occured when trying to edit custom page with ID: {$pid}", 'error');
				} else {
					flash_message("Successfully edited custom page with ID: {$pid}", 'success');
				}
				
				admin_redirect("index.php?module=config-custompages");
			}
		}
		
		if($mybb -> input['submitbutton']) {
			$result = $mybb -> input;
		} else {
			$q = $db -> simple_select("custompages", "*", "pid='{$pid}'");
			$result = $db -> fetch_array($q);
		}
		
		$sub_tabs['edit'] = array(
			'title' => "Edit Custom Page",
			'link' => "index.php?module=config-custompages&amp;action=add",
			'description' => "This section allows you to edit custom pages."
		);
		
		$page->add_breadcrumb_item("Edit Custom Page");
		$page->output_header("Custom Pages");
		$page->output_nav_tabs($sub_tabs, "edit");
		
		custompages_addedit_form($result, "Edit", "edit&amp;id=".$pid);
	}
	
	function custompages_delete($sub_tabs) {
		global $db, $mybb;
		$pid = (int) $mybb->input['id'];
		
		$q = $db -> delete_query("custompages", "pid = '{$pid}'");
		
		if($db->error_number($q)) {
			flash_message("An error occured when trying to delete custom page with ID: {$pid}", 'error');
		} else {
			flash_message("Successfully deleted custom page with ID: {$pid}", 'success');
		}
		
		admin_redirect("index.php?module=config-custompages");
	}
	
	function custompages_switch_state($sub_tabs) {
		global $db, $mybb;
		$pid = (int) $mybb->input['id'];
		
		$q = $db -> query("UPDATE `".TABLE_PREFIX."custompages` SET enabled = 1 - enabled WHERE pid = '{$pid}'");
		
		if($db->error_number($q)) {
			flash_message("An error occured when trying to edit custom page with ID: {$pid}", 'error');
		} else {
			flash_message("Successfully switched state of custom page with ID: {$pid}", 'success');
		}
		
		admin_redirect("index.php?module=config-custompages");
	}
	
	function custompages_list($sub_tabs) {
		global $db, $page, $mybb;
		
		$page->add_breadcrumb_item("List Custom Pages");
		$page->output_header("Custom Pages");
		$page->output_nav_tabs($sub_tabs, "list");
		
		$table = new Table;     
    	$table->construct_header("ID", array("width" => "50px"));
    	$table->construct_header("Name");
    	$table->construct_header("Current settins", array("width" => "350px"));
    	$table->construct_header("Action", array("width" => "150px"));
		
		$q = $db -> simple_select("custompages", "*");
		while($result = $db -> fetch_array($q)) {
			$table->construct_cell($result['pid'], array("style" => "text-align: center"));
		
			$table->construct_cell("<p><strong>{$result['title']}</strong></p>
			<p><small><a href=\"{$mybb->settings['bburl']}/index.php?page={$result['url']}\" target=\"_blank\">{$mybb->settings['bburl']}/index.php?page={$result['url']}</a></small></p>");
			
				if(!$result['template']) $result['template'] = "custompages_default";
				
				if(!$result['allowed_usergroups']) $result['allowed_usergroups'] = "All";
				
				if($result['enabled']) {
					$result['enabled'] = "<span style=\"color: green\">Enabled</span>";
					$isEnabled = true;
				} else {
					$result['enabled'] = "<span style=\"color: red\">Disabled</span>";
					$isEnabled = false;
				}
					
				if($result['bbcode'])
					$result['bbcode'] = "BBCode";
				else
					$result['bbcode'] = "HTML";
					
				if($result['parse_variables'])
					$result['parse_variables'] = "On";
				else
					$result['parse_variables'] = "Off";
					
				if($result['show_in_online'])
					$result['show_in_online'] = "Yes";
				else
					$result['show_in_online'] = "No";
			
			$table->construct_cell("<strong>Template:</strong> {$result['template']}<br/>
			<strong>Allowed usergroups:</strong> {$result['allowed_usergroups']}<br/>
			<strong>PHP variable parser:</strong> {$result['parse_variables']}<br/>
			<strong>Show in \"Who is Online\":</strong> {$result['show_in_online']}<br/>
			<strong>Type:</strong> {$result['bbcode']}<br/>
			<strong>State:</strong> {$result['enabled']}");
			
			$table->construct_cell("<ul>
				<li><a href=\"?module=config-custompages&amp;action=edit&amp;id={$result['pid']}\">Edit</a></li>
				<li><a href=\"?module=config-custompages&amp;action=delete&amp;id={$result['pid']}\">Delete</a></li>
				<li><a href=\"?module=config-custompages&amp;action=switch_state&amp;id={$result['pid']}\">".($isEnabled ? "Disable" : "Enable")."</a></li>
			</ul>");
			
			$table->construct_row();
		}
    	
		$table->output("List Custom Pages");
    }
	
	function custompages_addedit_form($values, $title, $action) {
		global $mybb;
	
		$form = new Form("index.php?module=config-custompages&amp;action=".$action, "post");
		$form_container = new FormContainer($title." Custom Page");
		
		$form_container -> output_row("Title <em>*</em>", "Title of your custom page.", $form->generate_text_box('title', $values['title']));
		
		$form_container -> output_row("URL <em>*</em>", "URL of your custom page (for example if you specify URL as <strong>mypage</strong>, you can see its content on <strong>{$mybb->settings['bburl']}/index.php?page=mypage</strong>).", $form->generate_text_box('url', $values['url']));
		
		$form_container -> output_row("Allowed usergroups", "Comma-separated list of usergroups which users can access this custom page. Leave it blank for no usergroup restrictions.", $form->generate_text_box('allowed_usergroups', $values['allowed_usergroups']));
		
		$form_container -> output_row("Template", "Template used by your custom page. Leave it blank if you want to use default template (custompages_default).", $form->generate_text_box('template', $values['template']));
		
		$form_container -> output_row("Page content <em>*</em>", "Content of your custom page.", $form->generate_text_area('content', $values['content'], array("style" => "width: 100%; height: 300px;")));
		
		$form_container -> output_row("Use BBCode instead of HTML? <em>*</em>", "Select 'Yes' if you want to use BBCode instead of HTML in content of your custom page. Select 'No' otherwise.", $form->generate_yes_no_radio('bbcode', $values['bbcode']));
		
		$form_container -> output_row("Parse PHP variables? <em>*</em>", "Select 'Yes' if you want to parse PHP variables in page content (for example if you write {\$mybb->settings['bburl']} in page content it will print your forum URL). Select 'No' otherwise.<br/><span style='color: red;'>This is an experimental option, so be careful.</span>", $form->generate_yes_no_radio('parse_variables', $values['parse_variables']));
		
		$form_container -> output_row("Show in \"Who is Online\"? <em>*</em>", "Select 'Yes' if you want to show this page in \"Who is Online\" and in user profiles. Select 'No' otherwise (it will be seen as \"Unknown\" then).", $form->generate_yes_no_radio('show_in_online', $values['show_in_online']));
		
		$form_container -> output_row("Custom page enabled <em>*</em>", "Select 'Yes' if you want enable your custom page. Select 'No' otherwise.", $form->generate_yes_no_radio('enabled', $values['enabled']));
		
		$form_container -> end();

		$buttons[] = $form -> generate_submit_button($title." page", array("name" => "submitbutton"));
		$form -> output_submit_wrapper($buttons);
		
		$form->end();
	}
	
	function custompages_verifydata($data) {
		$errors = array();
		
			if(!$data['title']) $errors[] = "Set the title of your page";
			if(!$data['url']) $errors[] = "Set the URL of your page";
			elseif(!preg_match('/^[a-z0-9_]*$/i', $data['url'])) $errors[] = "URL must consist of latin alphabet letters, digits and _";
			if(!$data['content']) $errors[] = "Set the content of your page";
		
		if(count($errors) > 0) {
			$message = "Correct following errors and try again:";
			$message .= "<ul>";
			foreach($errors as $error) {
				$message .= "<li>". $error ."</li>";
			}
			
			$message .= "</ul>";
			
			return $message;
		}
		
		return false;
	}
	
function custompages_setonlinestate(&$plugin_array) {
	global $lang, $db;
	
	if($plugin_array['user_activity']['activity'] == 'index' && preg_match('/.*page=([a-z0-9_]+)/i', $plugin_array['user_activity']['location'], $url)) {
		$url = $url[1];
		$q = $db -> simple_select("custompages", "*", "url='{$url}'");
		if($db -> num_rows($q) > 0) {
			$data = $db -> fetch_array($q);
			if($data['show_in_online']) {
				$plugin_array['location_name'] = "Browsing <a href=\"index.php?page={$url}\">{$data['title']}</a>";
			} else {
				$plugin_array['location_name'] = "Unknown";
			}
		}
	}
}

function custompages_parsevariables($content, $bbcode) { // UNSTABLE!
	if($bbcode) {
		$dollarchar = '&#36;';
	} else {
		$dollarchar = '\$';
	}

	if(preg_match_all('/\{'.$dollarchar.'(.*)}/iU', $content, $result)) {
		$result = $result[1];
		$replacement = array();
		foreach($result as $var) {
			eval('global $'.$var.';');
			$content = preg_replace('/\{'.$dollarchar.'('.$var.')}/iU', eval('return $'.$var.';'), $content);
		}
	}

	return $content;
}
?>