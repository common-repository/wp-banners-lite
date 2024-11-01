<?php
/*
Plugin Name: WP Banners Lite
Plugin URI: http://www.icprojects.net/wp-banners-lite.html
Description: The plugin easily allows you to manage ad banners on your site. If you wish to manage and sell banner spots directly to advertisers, get <a href="http://www.icprojects.net/banner-manager.html">full version</a> of this plugin.
Version: 1.40
Author: Ivan Churakov
Author URI: http://www.freelancer.com/affiliates/ichurakov/
*/
include_once(ABSPATH."wp-content/plugins/wp-banners-lite/const.php");
wp_enqueue_script("jquery");
register_activation_hook(__FILE__, array("wpbannerslite_class", "install"));

class wpbannerslite_class
{
	var $options;
	var $error;
	var $info;
	
	var $exists;
	var $from_name;
	var $from_email;
	var	$stats_email_subject;
	var $stats_email_body;
	
	var $types = array(
		array (
			"id" => 1,
			"width" => 728,
			"height" => 90
		),
		array (
			"id" => 2,
			"width" => 468,
			"height" => 60
		),
		array (
			"id" => 3,
			"width" => 234,
			"height" => 60
		),
		array (
			"id" => 4,
			"width" => 125,
			"height" => 125
		),
		array (
			"id" => 5,
			"width" => 120,
			"height" => 90
		),
		array (
			"id" => 6,
			"width" => 120,
			"height" => 600
		),
		array (
			"id" => 7,
			"width" => 160,
			"height" => 600
		),
		array (
			"id" => 8,
			"width" => 300,
			"height" => 250
		),
		array (
			"id" => 0,
			"width" => 0,
			"height" => 0
		)
	);

	var $default_options;
	
	function __construct() {
		$this->options = array(
		"exists",
		"from_name",
		"from_email",
		"stats_email_subject",
		"stats_email_body"
		);
		$this->default_options = array (
			"exists" => 1,
			"from_name" => get_bloginfo("name"),
			"from_email" => "noreply@".str_replace("www.", "", $_SERVER["SERVER_NAME"]),
			"stats_email_subject" => "Statistics",
			"stats_email_body" => "Dear Sir or Madam,\r\n\r\nWe would like to inform you that we have finished showing your banner: {banner_title}.\r\n{statistics}\r\n\r\nPlease contact us if you wish to rotate your banner again.\r\n\r\nThanks,\r\nAdministration of ".get_bloginfo("name")
		);

		if (!empty($_COOKIE["wpbannerslite_error"]))
		{
			$this->error = stripslashes($_COOKIE["wpbannerslite_error"]);
			setcookie("wpbannerslite_error", "", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
		}
		if (!empty($_COOKIE["wpbannerslite_info"]))
		{
			$this->info = stripslashes($_COOKIE["wpbannerslite_info"]);
			setcookie("wpbannerslite_info", "", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
		}

		$this->get_settings();

		if (is_admin()) {
			if ($this->check_settings() !== true) add_action('admin_notices', array(&$this, 'admin_warning'));
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_action('init', array(&$this, 'admin_request_handler'));
			add_action('admin_head', array(&$this, 'admin_header'), 15);
		} else {
			add_action("wp_head", array(&$this, "front_header"));
			add_shortcode('wpbanners-show', array(&$this, "shortcode_show"));
		}
	}

	function install ()
	{
		global $wpdb;

		$table_name = $wpdb->prefix . "wpbl_banners";
		//if($wpdb->get_var("show tables like '".$table_name."'") != $table_name)
		//{
			$sql = "CREATE TABLE " . $table_name . " (
				id int(11) NOT NULL auto_increment,
				type_id int(11) NOT NULL,
				title varchar(255) collate utf8_unicode_ci NOT NULL,
				url varchar(255) collate utf8_unicode_ci NOT NULL,
				file varchar(255) collate utf8_unicode_ci NOT NULL,
				email varchar(255) collate utf8_unicode_ci NOT NULL,
				days_purchased int(11) NOT NULL,
				price float NOT NULL,
				currency varchar(15) collate utf8_unicode_ci NOT NULL,
				shows_displayed int(11) NOT NULL,
				clicks int(11) NOT NULL,
				status int(11) NOT NULL,
				id_str varchar(63) collate utf8_unicode_ci NOT NULL,
				registered int(11) NOT NULL,
				blocked int(11) NULL,
				deleted int(11) NULL,
				UNIQUE KEY  id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		//}
		$table_name = $wpdb->prefix . "wpbl_types";
		//if($wpdb->get_var("show tables like '".$table_name."'") != $table_name)
		//{
			$sql = "CREATE TABLE " . $table_name . " (
				id int(11) NOT NULL auto_increment,
				title varchar(255) collate utf8_unicode_ci NOT NULL,
				description text collate utf8_unicode_ci NOT NULL,
				width int(11) NOT NULL,
				height int(11) NOT NULL,
				price float NOT NULL,
				preview_url varchar(255) collate utf8_unicode_ci NOT NULL,
				created int(11) NOT NULL,
				deleted int(11) NULL,
				UNIQUE KEY  id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		//}
		if (!file_exists(ABSPATH.'wp-content/uploads/wp-banners-lite'))
		{
			wp_mkdir_p(ABSPATH.'wp-content/uploads/wp-banners-lite');
		}
	}

	function get_settings() {
		$exists = get_option('wpbannerslite_exists');
		if ($exists != 1)
		{
			foreach ($this->options as $option) {
				$this->$option = $this->default_options[$option];
			}
		}
		else
		{
			foreach ($this->options as $option) {
				$this->$option = get_option('wpbannerslite_'.$option);
			}
		}
	}

	function update_settings() {
		if (current_user_can('manage_options')) {
			foreach ($this->options as $option) {
				update_option('wpbannerslite_'.$option, $this->$option);
			}
		}
	}

	function populate_settings() {
		foreach ($this->options as $option) {
			if (isset($_POST['wpbannerslite_'.$option])) {
				$this->$option = stripslashes($_POST['wpbannerslite_'.$option]);
			}
		}
	}

	function check_settings() {
		$errors = array();
		if (!eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$", $this->from_email) || strlen($this->from_email) == 0) $errors[] = "Sender e-mail must be valid e-mail address";
		if (strlen($this->from_name) < 3) $errors[] = "Sender name is too short";
		if (strlen($this->stats_email_subject) < 3) $errors[] = "Statistics e-mail subject must contain at least 3 characters";
		else if (strlen($this->stats_email_subject) > 64) $errors[] = "Statistics e-mail subject must contain maximum 64 characters";
		if (strlen($this->stats_email_body) < 3) $errors[] = "Statistics e-mail body must contain at least 3 characters";
		if (empty($errors)) return true;
		return $errors;
	}

	function admin_menu() {
		if (get_bloginfo('version') >= 3.0) {
			define("wpbl_PERMISSION", "add_users");
		}
		else{
			define("wpbl_PERMISSION", "edit_themes");
		}	
		add_menu_page(
			"Banners Lite"
			, "Banners Lite"
			, wpbl_PERMISSION
			, "wp-banners-lite"
			, array(&$this, 'admin_settings')
		);
		add_submenu_page(
			"wp-banners-lite"
			, "Settings"
			, "Settings"
			, wpbl_PERMISSION
			, "wp-banners-lite"
			, array(&$this, 'admin_settings')
		);
		add_submenu_page(
			"wp-banners-lite"
			, "Banner Types"
			, "Banner Types"
			, wpbl_PERMISSION
			, "wp-banners-lite-types"
			, array(&$this, 'admin_banner_types')
		);
		add_submenu_page(
			"wp-banners-lite"
			, "Add Banner Type"
			, "Add Banner Type"
			, wpbl_PERMISSION
			, "wp-banners-lite-types-add"
			, array(&$this, 'admin_add_banner_type')
		);
		add_submenu_page(
			"wp-banners-lite"
			, "Banners"
			, "Banners"
			, wpbl_PERMISSION
			, "wp-banners-lite-banners"
			, array(&$this, 'admin_banners')
		);
		add_submenu_page(
			"wp-banners-lite"
			, "Add Banner"
			, "Add Banner"
			, wpbl_PERMISSION
			, "wp-banners-lite-add"
			, array(&$this, 'admin_add_banner')
		);
	}

	function admin_settings() {
		global $wpdb;
		$message = "";
		$errors = array();
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		else
		{
			$errors = $this->check_settings();
			if (is_array($errors)) echo "<div class='error'><p>The following error(s) exists:<br />- ".implode("<br />- ", $errors)."</p></div>";
		}
		if ($_GET["updated"] == "true")
		{
			$message = '<div class="updated"><p>Plugin settings successfully <strong>updated</strong>.</p></div>';
		}
		print ('
		<div class="wrap admin_wpbannerslite_wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>Banners Lite - Settings</h2>
			'.$message.'
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">
			
			<div class="postbox-container" style="width: 100%;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>General Settings</span></h3>
							<div class="inside">
								<table class="wpbannerslite_useroptions">
									<tr>
										<th>Sender name:</th>
										<td><input type="text" id="wpbannerslite_from_name" name="wpbannerslite_from_name" value="'.htmlspecialchars($this->from_name, ENT_QUOTES).'" style="width: 98%;"><br /><em>Please enter sender name. All messages are sent using this name as "FROM:" header value.</em></td>
									</tr>
									<tr>
										<th>Sender e-mail:</th>
										<td><input type="text" id="wpbannerslite_from_email" name="wpbannerslite_from_email" value="'.htmlspecialchars($this->from_email, ENT_QUOTES).'" style="width: 98%;"><br /><em>Please enter sender e-mail. All messages are sent using this e-mail as "FROM:" header value.</em></td>
									</tr>
									<tr>
										<th>Statistics e-mail subject:</th>
										<td><input type="text" id="wpbannerslite_stats_email_subject" name="wpbannerslite_stats_email_subject" value="'.htmlspecialchars($this->stats_email_subject, ENT_QUOTES).'" style="width: 98%;"><br /><em>Subject field for e-mail with statistis.</em></td>
									</tr>
									<tr>
										<th>Statistics e-mail body:</th>
										<td><textarea id="wpbannerslite_stats_email_body" name="wpbannerslite_stats_email_body" style="width: 98%; height: 120px;">'.htmlspecialchars($this->stats_email_body, ENT_QUOTES).'</textarea><br /><em>Statistics e-mail body. You can use the following keywords: {banner_title}, {statistics}.</em></td>
									</tr>
								</table>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="wpbannerslite_update_settings" />
								<input type="hidden" name="wpbannerslite_exists" value="1" />
								<input type="submit" class="button-primary" name="submit" value="Update Settings »">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
		</div>
		');
	}

	function admin_banner_types() {
		global $wpdb;

		if (isset($_GET["s"])) $search_query = trim(stripslashes($_GET["s"]));
		else $search_query = "";
		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."wpbl_types WHERE deleted = '0'".((strlen($search_query) > 0) ? " AND (title LIKE '%".addslashes($search_query)."%' OR url LIKE '%".addslashes($search_query)."%')" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/ROWS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (isset($_GET["p"])) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(get_bloginfo("wpurl")."/wp-admin/admin.php?page=wp-banners-lite-types".((strlen($search_query) > 0) ? "&s=".rawurlencode($search_query) : ""), $page, $totalpages);

		$sql = "SELECT * FROM ".$wpdb->prefix."wpbl_types WHERE deleted = '0'".((strlen($search_query) > 0) ? " AND (title LIKE '%".addslashes($search_query)."%' OR url LIKE '%".addslashes($search_query)."%')" : "")." ORDER BY created DESC LIMIT ".(($page-1)*ROWS_PER_PAGE).", ".ROWS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";

		print ('
			<div class="wrap admin_wpbannerslite_wrap">
				<div id="icon-upload" class="icon32"><br /></div><h2>Banners Lite - Banner Types</h2><br />
				'.$message.'
				<form action="'.get_bloginfo("wpurl").'/wp-admin/admin.php" method="get" style="margin-bottom: 10px;">
				<input type="hidden" name="page" value="wp-banners-lite-types" />
				Search: <input type="text" name="s" value="'.htmlspecialchars($search_query, ENT_QUOTES).'">
				<input type="submit" class="button-secondary action" value="Search" />
				'.((strlen($search_query) > 0) ? '<input type="button" class="button-secondary action" value="Reset search results" onclick="window.location.href=\''.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-types\';" />' : '').'
				</form>
				<div class="wpbannerslite_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-types-add">Create New Banner Type</a></div>
				<div class="wpbannerslite_pageswitcher">'.$switcher.'</div>
				<table class="wpbannerslite_strings">
				<tr>
					<th>Title</th>
					<th style="width: 70px;">Size</th>
					<th style="width: 70px;">Actions</th>
				</tr>
		');
		if (sizeof($rows) > 0)
		{
			foreach ($rows as $row)
			{
				$bg_color = "";
				print ('
				<tr'.(!empty($bg_color) ? ' style="background-color: '.$bg_color.';"': '').'>
					<td><strong>'.htmlspecialchars($row['title'], ENT_QUOTES).'</strong>'.(!empty($row['description']) ? '<br /><em style="font-size: 12px; line-height: 14px;">'.htmlspecialchars($row['description'], ENT_QUOTES) : '').(!empty($row['preview_url']) ? '<br />Live preview: <a href="'.$row['preview_url'].'" target="_blank">'.htmlspecialchars($this->cut_string($row['preview_url'], 60), ENT_QUOTES).'</a>' : '').'</em></td>
					<td style="text-align: right;">'.$row['width'].' x '.$row['height'].'</td>
					<td style="text-align: center;">
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-types-add&id='.$row['id'].'" title="Edit banner type details"><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/edit.png" alt="Edit banner type details" border="0"></a>
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-types-add&mode=embed&id='.$row['id'].'" title="Show embed code"><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/embed.png" alt="Show embed code" border="0"></a>
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=wpbannerslite_types_delete&id='.$row['id'].'" title="Delete banner type" onclick="return wpbannerslite_submitOperation();"><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/delete.png" alt="Delete banner type" border="0"></a>
					</td>
				</tr>');
			}
		}
		else
		{
			print ('
				<tr><td colspan="3" style="padding: 20px; text-align: center;">'.((strlen($search_query) > 0) ? 'No results found for "<strong>'.htmlspecialchars($search_query, ENT_QUOTES).'</strong>"' : 'List is empty. Click <a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-types-add">here</a> to create new banner type.').'</td></tr>
			');
		}
		print ('
				</table>
				<div class="wpbannerslite_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-types-add">Create New Bannner Type</a></div>
				<div class="wpbannerslite_pageswitcher">'.$switcher.'</div>
				<div class="wpbannerslite_legend">
				<strong>Legend:</strong>
					<p><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/edit.png" alt="Edit banner type details" border="0"> Edit banner type details</p>
					<p><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/embed.png" alt="Show embed code" border="0"> Show embed code</p>
					<p><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/delete.png" alt="Delete banner type" border="0"> Delete banner type</p>
				</div>
			</div>
		');
	}

	function admin_add_banner_type() {
		global $wpdb;
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";
		unset($id);
		if (isset($_GET["id"]) && !empty($_GET["id"])) {
			$id = intval($_GET["id"]);
			$type_details = $wpdb->get_row("SELECT t1.*, t2.total FROM ".$wpdb->prefix . "wpbl_types t1 LEFT JOIN (SELECT type_id, COUNT(*) AS total FROM ".$wpdb->prefix."wpbl_banners WHERE registered+24*3600*days_purchased >= '".time()."' AND deleted = '0' GROUP BY type_id) t2 ON t2.type_id = t1.id WHERE t1.id = '".$id."' AND t1.deleted = '0'", ARRAY_A);
			if (intval($type_details["id"]) == 0) unset($id);
		}
		if (isset($_GET["mode"]) && $_GET["mode"] == "embed" && !empty($id)) {
			// Show embed code for selected banner type
			print ('
			<div class="wrap admin_wpbannerslite_wrap">
				<div id="icon-options-general" class="icon32"><br /></div><h2>Banners Lite - Embed Code</h2>
				<div class="postbox-container" style="width: 100%;">
					<div class="metabox-holder">
						<div class="meta-box-sortables ui-sortable">
							<div class="postbox">
								<!--<div class="handlediv" title="Click to toggle"><br></div>-->
								<h3 class="hndle" style="cursor: default;"><span>Embed codes for "'.htmlspecialchars($type_details["title"], ENT_QUOTES).'" banner</span></h3>
								<div class="inside">
									<table class="wpbannerslite_useroptions">
										<tr>
											<th>PHP Code:</th>
											<td><textarea id="wpbannerslite_php" style="width: 98%; height: 50px;" onclick="this.focus();this.select();" readonly="readonly">'.htmlspecialchars('<?php if (function_exists("wpbanners_show")) wpbanners_show('.$type_details["id"].'); ?>', ENT_QUOTES).'</textarea><br /><em>This is PHP-code. You can insert it into desired place of your active theme by editing appropriate files (Ex.: header.php, single.php, or any other).</em></td>
										</tr>
										<tr>
											<th>HTML+JavaScript Code:</th>
											<td><textarea id="wpbannerslite_js" style="width: 98%; height: 50px;" onclick="this.focus();this.select();" readonly="readonly">'.htmlspecialchars('<a id="a_'.md5($type_details["id"]).'"></a>
<!--[if IE]>
<script type="text/javascript" src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/wpbanners_show.php?id='.$type_details["id"].'&cid=a_'.md5($type_details["id"]).'"></script>
<![endif]-->
<script defer="defer" type="text/javascript" src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/wpbanners_show.php?id='.$type_details["id"].'&cid=a_'.md5($type_details["id"]).'"></script>', ENT_QUOTES).'</textarea><br /><em>This is HTML+JavaScript code. You can insert it into desired place of your active theme by editing appropriate files or you can insert it into body of your posts, pages and text-widgets. You also can use this embed code with non-WordPress part of your website (it uses jQuery).</em></td>
										</tr>
										<tr>
											<th>Shortcode:</th>
											<td><textarea id="wpbannerslite_shortcode" style="width: 98%; height: 50px;" onclick="this.focus();this.select();" readonly="readonly">'.htmlspecialchars('[wpbanners-show id="'.$type_details["id"].'"]', ENT_QUOTES).'</textarea><br /><em>This is shortcode you can insert it into body of your posts and pages.</em></td>
										</tr>
									</table>
									<br class="clear">
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>');

			return;
		}
		// Show banner type details form
		print ('
		<div class="wrap admin_wpbannerslite_wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>Banners Lite - '.(!empty($id) ? 'Edit banner type details' : 'Create new banner type').'</h2>
			'.$message.'
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">
			
			<div class="postbox-container" style="width: 100%;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>'.(!empty($id) ? 'Edit banner type details' : 'Create new banner type').'</span></h3>
							<div class="inside">
								<table class="wpbannerslite_useroptions">
									<tr>
										<th>Title:</th>
										<td><input type="text" name="wpbannerslite_title" id="wpbannerslite_title" value="'.htmlspecialchars($type_details['title'], ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter banner type title. Make it as short as possible. Ex.: "All pages header banner", "Subpages right side tower", etc.</em></td>
									</tr>
									<tr>
										<th>Description:</th>
										<td><textarea id="wpbannerslite_description" name="wpbannerslite_description" style="width: 98%; height: 120px;">'.htmlspecialchars($type_details['description'], ENT_QUOTES).'</textarea><br /><em>Describe banner type. Ex.: "This banner will be displayed on all website pages at the header section", etc.</em></td>
									</tr>
									<tr>
										<th>Banner size:</th>
										<td>
											<select name="wpbannerslite_type" id="wpbannerslite_type" onchange="wpbannerslite_changesize();">');
		$selected = false;
		foreach($this->types as $type) {
			if ($type_details["width"] > 0 && $type_details["height"] > 0 && $type_details["width"] == $type["width"] && $type_details["height"] == $type["height"]) {
				$selected = true;
				print ('
												<option value="'.$type["id"].'" selected="selected">'.$type["width"]." x ".$type["height"].'</option>');
			} else if ($type["id"] > 0) {
				print ('
												<option value="'.$type["id"].'">'.$type["width"]." x ".$type["height"].'</option>');
			} else if ($type_details["width"] > 0 && $type_details["height"] > 0 && !$selected) {
				print ('
												<option value="0" selected="selected">Custom</option>');
			} else {
				print ('
												<option value="0">Custom</option>');
			}
		}
		print ('
											</select>
											<input type="text" name="wpbannerslite_width" id="wpbannerslite_width" value="'.(!empty($type_details['width']) ? htmlspecialchars($type_details['width'], ENT_QUOTES) : $this->types[0]["width"]).'" style="width: 60px; text-align: right;"> x
											<input type="text" name="wpbannerslite_height" id="wpbannerslite_height" value="'.(!empty($type_details['height']) ? htmlspecialchars($type_details['height'], ENT_QUOTES) : $this->types[0]["height"]).'" style="width: 60px; text-align: right;">
											<br /><em>Select banner size. You can choose standard banner sizes or specify your custom size.</em>
										</td>
									</tr>
									<tr>
										<th>Preview URL:</th>
										<td><input type="text" name="wpbannerslite_preview_url" id="wpbannerslite_preview_url" value="'.htmlspecialchars($type_details['preview_url'], ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter URL of any page which contains this banner type. It is used for "Live preview" functionality.</em></td>
									</tr>
								</table>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="wpbannerslite_update_banner_type" />
								'.(!empty($id) ? '<input type="hidden" name="wpbannerslite_id" value="'.$id.'" />' : '').'
								<input type="submit" class="button-primary" name="submit" value="Submit details »">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
		</div>
		<script type="text/javascript">
			wpbannerslite_changesize();
			function wpbannerslite_changesize() {
				var type = jQuery("#wpbannerslite_type").val();');
		foreach($this->types as $type) {
			if ($type["id"] == 0) {
				print ('
				if (type == "0") {
					jQuery("#wpbannerslite_width").removeAttr("disabled");
					jQuery("#wpbannerslite_height").removeAttr("disabled");
				}');
			} else {
				print ('
				if (type == "'.$type["id"].'") {
					jQuery("#wpbannerslite_width").attr("disabled", "disabled");
					jQuery("#wpbannerslite_height").attr("disabled", "disabled");
					jQuery("#wpbannerslite_width").val("'.$type["width"].'");
					jQuery("#wpbannerslite_height").val("'.$type["height"].'");
				}');
			}
		}
		print ('		
			}
		</script>');
	}
	
	function admin_banners() {
		global $wpdb;

		if (isset($_GET["s"])) $search_query = trim(stripslashes($_GET["s"]));
		else $search_query = "";
		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."wpbl_banners WHERE status != '".STATUS_DRAFT."' AND deleted='0'".((strlen($search_query) > 0) ? " AND (title LIKE '%".addslashes($search_query)."%' OR url LIKE '%".addslashes($search_query)."%')" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/ROWS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (isset($_GET["p"])) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(get_bloginfo("wpurl")."/wp-admin/admin.php?page=wp-banners-lite-banners".((strlen($search_query) > 0) ? "&s=".rawurlencode($search_query) : ""), $page, $totalpages);

		$sql = "SELECT t1.*, t2.title AS type_title, t2.width, t2.height, t2.preview_url FROM ".$wpdb->prefix."wpbl_banners t1 LEFT JOIN ".$wpdb->prefix."wpbl_types t2 ON t1.type_id = t2.id WHERE t1.status != '".STATUS_DRAFT."' AND t1.deleted='0'".((strlen($search_query) > 0) ? " AND (t1.title LIKE '%".addslashes($search_query)."%' OR t1.url LIKE '%".addslashes($search_query)."%')" : "")." ORDER BY t1.registered DESC LIMIT ".(($page-1)*ROWS_PER_PAGE).", ".ROWS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";

		print ('
			<div class="wrap admin_wpbannerslite_wrap">
				<div id="icon-upload" class="icon32"><br /></div><h2>Banners Lite - Banners</h2><br />
				'.$message.'
				<form action="'.get_bloginfo("wpurl").'/wp-admin/admin.php" method="get" style="margin-bottom: 10px;">
				<input type="hidden" name="page" value="wp-banners-lite-banners" />
				Search: <input type="text" name="s" value="'.htmlspecialchars($search_query, ENT_QUOTES).'">
				<input type="submit" class="button-secondary action" value="Search" />
				'.((strlen($search_query) > 0) ? '<input type="button" class="button-secondary action" value="Reset search results" onclick="window.location.href=\''.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-banners\';" />' : '').'
				</form>
				<div class="wpbannerslite_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-add">Create New Banner</a></div>
				<div class="wpbannerslite_pageswitcher">'.$switcher.'</div>
				<table class="wpbannerslite_strings">
				<tr>
					<th>Title</th>
					<th>Type</th>
					<th style="width: 160px;">E-mail</th>
					<th style="width: 60px;">Shows</th>
					<th style="width: 60px;">Clicks</th>
					<th style="width: 90px;">Actions</th>
				</tr>
		');
		if (sizeof($rows) > 0)
		{
			foreach ($rows as $row)
			{
				$bg_color = "";
				if (time() > $row["registered"] + 24*3600*$row["days_purchased"]) $bg_color = "#E0E0E0";
				else if ($row["status"] >= STATUS_PENDING) $bg_color = "#FFF0F0";
				
				if ($row["status"] < STATUS_PENDING && $row["status"] > STATUS_DRAFT) {
					if (time() <= $row["registered"] + 24*3600*$row["days_purchased"]) $expired = "Expires in ".$this->period_to_string($row["registered"] + 24*3600*$row["days_purchased"] - time());
					else $expired = "Expired";
				} else $expired = "";
				
				print ('
				<tr'.(!empty($bg_color) ? ' style="background-color: '.$bg_color.';"': '').'>
					<td><strong>'.htmlspecialchars((empty($row['title']) ? 'Banner without title' : $row['title']), ENT_QUOTES).'</strong><br /><em style="font-size: 12px; line-height: 14px;">'.$expired.(!empty($row['preview_url']) ? (!empty($expired) ? ', ' : '').'<a href="'.$this->add_url_parameters($row["preview_url"], array ("wpbannerslite_show" => $row["id_str"])).'" target="_blank">live preview</a>' : '').'</em></td>
					<td>'.htmlspecialchars($row['type_title'], ENT_QUOTES).' ('.$row['width'].'x'.$row['height'].')</td>
					<td>'.$row['email'].'</td>
					<td style="text-align: right;">'.intval($row["shows_displayed"]).'</td>
					<td style="text-align: right;">'.intval($row["clicks"]).'</td>
					<td style="text-align: center;">
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-add&id='.$row['id'].'" title="Edit banner details"><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/edit.png" alt="Edit banner details" border="0"></a>
						'.(((time() <= $row["registered"] + 24*3600*$row["days_purchased"]) && $row["status"] < STATUS_PENDING) ? '<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=wpbannerslite_block&id='.$row['id'].'" title="Block banner" onclick="return wpbannerslite_submitOperation();"><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/block.png" alt="Block banner" border="0"></a>' : '').'
						'.(($row["status"] >= STATUS_PENDING) ? '<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=wpbannerslite_unblock&id='.$row['id'].'" title="Unblock banner" onclick="return wpbannerslite_submitOperation();"><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/unblock.png" alt="Unblock banner" border="0"></a>' : '').'
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=wpbannerslite_delete&id='.$row['id'].'" title="Delete banner" onclick="return wpbannerslite_submitOperation();"><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/delete.png" alt="Delete banner" border="0"></a>
					</td>
				</tr>
				');
			}
		}
		else
		{
			print ('
				<tr><td colspan="6" style="padding: 20px; text-align: center;">'.((strlen($search_query) > 0) ? 'No results found for "<strong>'.htmlspecialchars($search_query, ENT_QUOTES).'</strong>"' : 'List is empty. Click <a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-add">here</a> to create new banner.').'</td></tr>
			');
		}
		print ('
				</table>
				<div class="wpbannerslite_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=wp-banners-lite-add">Create New Banner</a></div>
				<div class="wpbannerslite_pageswitcher">'.$switcher.'</div>
				<div class="wpbannerslite_legend">
				<strong>Legend:</strong>
					<p><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/edit.png" alt="Edit banner details" border="0"> Edit banner details</p>
					<p><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/block.png" alt="Block banner" border="0"> Block banner</p>
					<p><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/unblock.png" alt="Unblock banner" border="0"> Unblock banner</p>
					<p><img src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/images/delete.png" alt="Delete banner" border="0"> Delete banner</p>
					<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px;"></div> Active banner<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #FFF0F0;"></div> Blocked/Pending banner<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #E0E0E0;"></div> Expired banner
				</div>
			</div>
		');
	}

	function admin_add_banner() {
		global $wpdb;

		$sql = "SELECT * FROM ".$wpdb->prefix."wpbl_types WHERE deleted = '0'";
		$types = $wpdb->get_results($sql, ARRAY_A);
		if (empty($types)) {
			print ('
			<div class="wrap admin_wpbannerslite_wrap">
				<div id="icon-options-general" class="icon32"><br /></div><h2>Banner Manager - Create new banner</h2>
				<div class="error"><p>Please create at least one banner type first. You can do it <a href="'.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-types-add">here</a>.</p></div>
			</div>');
			return;
		}

		unset($id);
		if (isset($_GET["id"]) && !empty($_GET["id"])) {
			$id = intval($_GET["id"]);
			$banner_details = $wpdb->get_row("SELECT t1.*, t2.width, t2.height, t2.preview_url, t2.price, t2.title AS type_title FROM ".$wpdb->prefix."wpbl_banners t1 LEFT JOIN ".$wpdb->prefix."wpbl_types t2 ON t2.id = t1.type_id WHERE t1.id = '".$id."' AND t1.deleted = '0'", ARRAY_A);
			if (intval($banner_details["id"]) == 0) unset($id);
		}
		$errors = true;
		if (!empty($id)) $errors = $this->check_banner_details($banner_details);
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		else if ($errors !== true) {
			$message = "<div class='error'><p>The following error(s) exists:<br />- ".implode("<br />- ", $errors)."</p></div>";
		} else if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";
		

		print ('
		<div class="wrap admin_wpbannerslite_wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>Banners Lite - '.(!empty($id) ? 'Edit banner details' : 'Create new banner').'</h2>
			'.$message.'
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">
			<div class="postbox-container" style="width: 100%;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>'.(!empty($id) ? 'Edit banner details' : 'Create new banner').'</span></h3>
							<div class="inside">
								<table class="wpbannerslite_useroptions">
									<tr>
										<th>Title:</th>
										<td><input type="text" name="wpbannerslite_title" id="wpbannerslite_title" value="'.htmlspecialchars($banner_details['title'], ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter banner title.</em></td>
									</tr>
									<tr>
										<th>URL:</th>
										<td><input type="text" name="wpbannerslite_url" id="wpbannerslite_url" value="'.htmlspecialchars($banner_details['url'], ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter URL of website. The banner will be hyperlinked with this URL.</em></td>
									</tr>
									<tr>
										<th>Banner type:</th>
										<td>
											<select name="wpbannerslite_type" id="wpbannerslite_type">');

		foreach ($types as $type) {
			print ('
												<option value="'.$type["id"].'"'.($type["id"] == $banner_details["type_id"] ? ' selected="selected"' : '').'>'.htmlspecialchars($type["title"].' ('.$type["width"].'x'.$type["height"].')', ENT_QUOTES).'</option>
			');
		}
		print ('
											</select>
											<br /><em>Select desired banner type.</em>
										</td>
									</tr>
									<tr>
										<th>Banner image:</th>
										<td>
										'.(!empty($banner_details["file"]) ? (!empty($banner_details["preview_url"]) ? '<a href="'.$this->add_url_parameters($banner_details["preview_url"], array ("wpbannerslite_show" => $banner_details["id_str"])).'">Live preview</a><br />' : '').'<img src="'.get_bloginfo("wpurl").'/wp-content/uploads/wp-banners-lite/'.rawurlencode($banner_details["file"]).'"><br />' : '').'
										<input type="file" name="wpbannerslite_file" id="wpbannerslite_file" style="width: 98%;">
										<br /><em>Choose banner image. You can use JPEG, GIF and PNG images. The size of image must be exactly the same as specified in banner type.</em>
										</td>
									</tr>
									<tr>
										<th>Rotation period (days):</th>
										<td><input type="text" name="wpbannerslite_days" id="wpbannerslite_days" value="'.htmlspecialchars($banner_details['days_purchased'], ENT_QUOTES).'" style="width: 60px; text-align: right;"><br /><em>Enter period of rotation. How many days banner will be in rotation.</em></td>
									</tr>
									<tr>
										<th>E-mail:</th>
										<td><input type="text" name="wpbannerslite_email" id="wpbannerslite_email" value="'.htmlspecialchars($banner_details['email'], ENT_QUOTES).'" style="width: 50%;"><br /><em>Enter e-mail for statistics. Plugin sends statistics about banner rotation to this e-mail.</em></td>
									</tr>
								</table>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="wpbannerslite_update_banner" />
								'.(!empty($id) ? '<input type="hidden" name="wpbannerslite_id" value="'.$id.'" />' : '').'
								<input type="submit" class="button-primary" name="submit" value="Submit details »">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
		</div>');
	}

	function admin_request_handler() {
		global $wpdb;
		if (!empty($_POST['ak_action'])) {
			switch($_POST['ak_action']) {
				case 'wpbannerslite_update_settings':
					$this->populate_settings();
					$errors = $this->check_settings();
					if ($errors === true)
					{
						$this->update_settings();
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite&updated=true');
						die();
					}
					else
					{
						$this->update_settings();
						$message = "";
						if (is_array($errors)) $message = "The following error(s) occured:<br />- ".implode("<br />- ", $errors);
						setcookie("wpbannerslite_error", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite');
						die();
					}
					break;
				case "wpbannerslite_update_banner_type":
					if (isset($_POST["wpbannerslite_id"]) && !empty($_POST["wpbannerslite_id"])) {
						$id = intval($_POST["wpbannerslite_id"]);
						$type_details = $wpdb->get_row("SELECT t1.*, t2.total FROM ".$wpdb->prefix . "wpbl_types t1 LEFT JOIN (SELECT type_id, COUNT(*) AS total FROM ".$wpdb->prefix."wpbl_banners WHERE registered+24*3600*days_purchased >= '".time()."' AND deleted = '0' GROUP BY type_id) t2 ON t2.type_id = t1.id WHERE t1.id = '".$id."' AND t1.deleted = '0'", ARRAY_A);
						if (intval($type_details["id"]) == 0) unset($id);
					}
					$title = trim(stripslashes($_POST["wpbannerslite_title"]));
					$description = trim(stripslashes($_POST["wpbannerslite_description"]));
					$preview_url = trim(stripslashes($_POST["wpbannerslite_preview_url"]));
					$type = intval(trim(stripslashes($_POST["wpbannerslite_type"])));
					$price = 0;
					if ($type != 0) {
						foreach($this->types as $type_tmp) {
							if ($type_tmp["id"] == $type) {
								$width = $type_tmp["width"];
								$height = $type_tmp["height"];
								break;
							}
						}
					} else {
						$width = intval(trim(stripslashes($_POST["wpbannerslite_width"])));
						$height = intval(trim(stripslashes($_POST["wpbannerslite_height"])));
					}
					
					unset($errors);
					if (strlen($title) < 3) $errors[] = "title is too short";
					else if (strlen($title) > 128) $errors[] = "title is too long";
					if (!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $preview_url) && strlen($preview_url) > 0) $errors[] = "preview url must be valid URL";
					if ($width < 20) $errors[] = "width must be 20 or higher";
					if ($height < 20) $errors[] = "height must be 20 or higher";
					if (!empty($id) && $type_details["total"] > 0 && $width != $type_details["width"] && $height != $type_details["height"]) $errors[] = "banner size must be the same, because you have non-expired banners of this type";
					if (empty($errors)) {
						if (!empty($id)) {
							$sql = "UPDATE ".$wpdb->prefix."wpbl_types SET 
								title = '".mysql_real_escape_string($title)."',
								description = '".mysql_real_escape_string($description)."',
								preview_url = '".mysql_real_escape_string($preview_url)."',
								price = '".number_format($price, 2, ".", "")."',
								width = '".$width."',
								height = '".$height."'
								WHERE id = '".$id."'";
							if ($wpdb->query($sql) !== false)
							{
								setcookie("wpbannerslite_info", "Banner type successfully updated", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-types');
								die();
							} else {
								setcookie("wpbannerslite_error", "Service is not available", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-types-add'.(!empty($id) ? "&id=".$id : ""));
								die();
							}
						} else {
							$sql = "INSERT INTO ".$wpdb->prefix."wpbl_types (
								title, description, preview_url, width, height, price, created, deleted) VALUES (
								'".mysql_real_escape_string($title)."',
								'".mysql_real_escape_string($description)."',
								'".mysql_real_escape_string($preview_url)."',
								'".$width."',
								'".$height."',
								'".number_format($price, 2, ".", "")."',
								'".time()."', '0'
								)";
							if ($wpdb->query($sql) !== false)
							{
								setcookie("wpbannerslite_info", "Banner type successfully added", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-types');
								die();
							} else {
								setcookie("wpbannerslite_error", "Service is not available", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-types-add'.(!empty($id) ? "&id=".$id : ""));
								die();
							}
						}
					} else {
						$message = "The following error(s) occured:<br />- ".implode("<br />- ", $errors);
						setcookie("wpbannerslite_error", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-types-add'.(!empty($id) ? "&id=".$id : ""));
						die();
					}
					break;
				case "wpbannerslite_update_banner":
					unset($id);
					if (isset($_POST["wpbannerslite_id"]) && !empty($_POST["wpbannerslite_id"])) {
						$id = intval($_POST["wpbannerslite_id"]);
						$banner_details = $wpdb->get_row("SELECT t1.*, t2.width, t2.height, t2.preview_url, t2.price, t2.title AS type_title FROM ".$wpdb->prefix."wpbl_banners t1 LEFT JOIN ".$wpdb->prefix."wpbl_types t2 ON t2.id = t1.type_id WHERE t1.id = '".$id."' AND t1.deleted = '0'", ARRAY_A);
						if (intval($banner_details["id"]) == 0) unset($id);
					}
					$title = trim(stripslashes($_POST["wpbannerslite_title"]));
					$url = trim(stripslashes($_POST["wpbannerslite_url"]));
					$email = trim(stripslashes($_POST["wpbannerslite_email"]));
					$days = intval(trim(stripslashes($_POST["wpbannerslite_days"])));
					$type = intval(trim(stripslashes($_POST["wpbannerslite_type"])));

					unset($errors);
					if (strlen($title) < 3) $errors[] = "title is too short";
					else if (strlen($title) > 128) $errors[] = "title is too long";
					if (!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url) && strlen($url) > 0) $errors[] = "banner URL must be valid URL";
					if (!eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$", $email) && strlen($email) > 0) $errors[] = "e-mail must be valid e-mail address";

					$type_details = $wpdb->get_row("SELECT t1.*, t2.total FROM ".$wpdb->prefix . "wpbl_types t1 LEFT JOIN (SELECT type_id, COUNT(*) AS total FROM ".$wpdb->prefix."wpbl_banners WHERE registered+24*3600*days_purchased >= '".time()."' AND status != '".STATUS_DRAFT."' AND status < '".STATUS_PENDING."' AND deleted = '0' GROUP BY type_id) t2 ON t2.type_id = t1.id WHERE t1.id = '".$type."' AND t1.deleted = '0'", ARRAY_A);
					if (intval($type_details["id"]) == 0) $errors[] = "invalid banner type";
					
					$image = $banner_details["file"];
					if (is_uploaded_file($_FILES["wpbannerslite_file"]["tmp_name"]))
					{
						$ext = "";
						if (($pos = strrpos($_FILES["wpbannerslite_file"]["name"], ".")) !== false) {
							$ext = strtolower(substr($_FILES["wpbannerslite_file"]["name"], $pos));
						}
						if ($ext != ".jpg" && $ext != ".jpeg" && $ext != ".gif" && $ext != ".png") $errors[] = 'banner image must be JPEG, GIF or PNG file';
						else
						{
							list($width, $height, $imagetype, $attr) = getimagesize($_FILES["wpbannerslite_file"]["tmp_name"]);
							if ($width != $type_details["width"] || $height != $type_details["height"]) $errors[] = 'banner image size must be '.$type_details["width"].'x'.$type_details["height"];
							else {
								$image = "banner_".md5(microtime().$_FILES["wpbannerslite_file"]["tmp_name"]).$ext;
								if (!move_uploaded_file($_FILES["wpbannerslite_file"]["tmp_name"], ABSPATH."wp-content/uploads/wp-banners-lite/".$image)) {
									$errors[] = "can't save uploaded banner image";
									$image = "";
								} else {
									if (!empty($banner_details["file"]))
									{
										if (file_exists(ABSPATH."wp-content/uploads/wp-banners-lite/".$banner_details["file"]) && is_file(ABSPATH."wp-content/uploads/wp-banners-lite/".$banner_details["file"]))
											unlink(ABSPATH."wp-content/uploads/wp-banners-lite/".$banner_details["file"]);
									}
								}
							}
						}
					} else if (empty($id) || empty($banner_details["file"])) $errors[] = "banner image must be uploaded";

					if (!empty($id)) {
						if (!empty($errors)) {
							if (($banner_details["status"] % STATUS_ERROR_OFFSET) != STATUS_DRAFT) {
								$status = ($banner_details["status"] % STATUS_ERROR_OFFSET) + STATUS_ERROR_OFFSET;
								$blocked = time();
							} else {
								$status = STATUS_DRAFT;
								$blocked = $banner_details["blocked"];
							}
						} else {
							if ($banner_details["status"] == STATUS_DRAFT) {
								$status = STATUS_ACTIVE_BYADMIN;
								$blocked = $banner_details["blocked"];
							} else if ($banner_details["status"] > STATUS_ERROR_OFFSET) {
								$status = $banner_details["status"] % STATUS_ERROR_OFFSET;
								$blocked = $banner_details["blocked"];
							} else {
								$status = $banner_details["status"];
								$blocked = $banner_details["blocked"];
							}
						}
						$sql = "UPDATE ".$wpdb->prefix."wpbl_banners SET 
							title = '".mysql_real_escape_string($title)."',
							url = '".mysql_real_escape_string($url)."',
							file = '".mysql_real_escape_string($image)."',
							email = '".mysql_real_escape_string($email)."',
							days_purchased = '".$days."',
							type_id = '".$type."',
							status = '".$status."',
							blocked = '".$blocked."'
							WHERE id = '".$id."'";
						if ($wpdb->query($sql) !== false) {
							if (empty($errors)) {
								setcookie("wpbannerslite_info", "Banner successfully updated", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
								die();
							} else {
								$message = "The following error(s) occured:<br />- ".implode("<br />- ", $errors);
								setcookie("wpbannerslite_error", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-add&id='.$id);
								die();
							}
						} else {
							setcookie("wpbannerslite_error", "Service is not available", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
							header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-add&id='.$id);
							die();
						}
					} else {
						$registered = time();
						if (!empty($errors)) {
							$status = STATUS_DRAFT;
							$blocked = 0;
						} else {
							$status = STATUS_ACTIVE_BYADMIN;
							$blocked = 0;
						}
						if (intval($banner_details["id"]) == 0) unset($id);
						$id_str = md5(microtime().rand(1,1000));
						$sql = "INSERT INTO ".$wpdb->prefix."wpbl_banners (
							type_id, title, url, file, email, days_purchased, price, currency, shows_displayed, clicks, status, id_str, registered, blocked, deleted) VALUES (
							'".$type."',
							'".mysql_real_escape_string($title)."',
							'".mysql_real_escape_string($url)."',
							'".mysql_real_escape_string($image)."',
							'".mysql_real_escape_string($email)."',
							'".$days."',
							'0', 'USD', '0', '0', '".$status."',
							'".$id_str."',
							'".$registered."', '".$blocked."', '0'
							)";
						if ($wpdb->query($sql) !== false)
						{
							if (empty($errors)) {
								$message = "Banner successfully added";
								setcookie("wpbannerslite_info", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
								die();
							} else {
								$message = "The following error(s) occured:<br />- ".implode("<br />- ", $errors);
								setcookie("wpbannerslite_error", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-add&id='.$wpdb->insert_id);
								die();
							}
						} else {
							setcookie("wpbannerslite_error", "Service is not available", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
							header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-add');
							die();
						}
					}
					break;
			}
		}
		if (!empty($_GET['ak_action'])) {
			switch($_GET['ak_action']) {
				case 'wpbannerslite_types_delete':
					$id = intval($_GET["id"]);
					$type_details = $wpdb->get_row("SELECT t1.*, t2.total FROM ".$wpdb->prefix . "wpbl_types t1 LEFT JOIN (SELECT type_id, COUNT(*) AS total FROM ".$wpdb->prefix."wpbl_banners WHERE registered+24*3600*days_purchased >= '".time()."' AND deleted = '0' GROUP BY type_id) t2 ON t2.type_id = t1.id WHERE t1.id = '".$id."' AND t1.deleted = '0'", ARRAY_A);
					if (intval($type_details["id"]) == 0)
					{
						setcookie("wpbannerslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-types');
						die();
					}
					if ($type_details["total"] > 0) {
						setcookie("wpbannerslite_error", "You can not delete this banner type, because you have non-expired banners of this type", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-types');
						die();
					}
					$sql = "UPDATE ".$wpdb->prefix."wpbl_types SET deleted = '1' WHERE id = '".$id."'";
					if ($wpdb->query($sql) !== false)
					{
						setcookie("wpbannerslite_info", "Banner successfully removed", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-types');
						die();
					}
					else
					{
						setcookie("wpbannerslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-types');
						die();
					}
					break;

				case 'wpbannerslite_delete':
					$id = intval($_GET["id"]);
					$banner_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . "wpbl_banners WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($banner_details["id"]) == 0)
					{
						setcookie("wpbannerslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
						die();
					}
					$sql = "UPDATE ".$wpdb->prefix."wpbl_banners SET deleted = '1' WHERE id = '".$id."'";
					if ($wpdb->query($sql) !== false)
					{
						setcookie("wpbannerslite_info", "Banner successfully removed", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
						die();
					}
					else
					{
						setcookie("wpbannerslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
						die();
					}
					break;

				case 'wpbannerslite_block':
					$id = intval($_GET["id"]);
					$banner_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . "wpbl_banners WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($banner_details["id"]) == 0)
					{
						setcookie("wpbannerslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
						die();
					}
					if (time() <= $banner_details["registered"] + 24*3600*$banner_details["days_purchased"] && ($banner_details["status"] == STATUS_ACTIVE_BYUSER || $banner_details["status"] == STATUS_ACTIVE_BYADMIN))
					{
						$sql = "UPDATE ".$wpdb->prefix."wpbl_banners SET status = '".STATUS_PENDING_BLOCKED."', blocked = '".time()."' WHERE id = '".$id."'";
						$wpdb->query($sql);
						setcookie("wpbannerslite_info", "Banner successfully blocked", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
						die();
					}
					else
					{
						setcookie("wpbannerslite_error", "You can not block this banner", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
						die();
					}
					break;
					
				case 'wpbannerslite_unblock':
					$id = intval($_GET["id"]);
					$banner_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . "wpbl_banners WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($banner_details["id"]) == 0)
					{
						setcookie("wpbannerslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
						die();
					}
					if ($banner_details["status"] == STATUS_PENDING_PAYMENT || $banner_details["status"] == STATUS_PENDING_NOSLOTS || $banner_details["status"] == STATUS_PENDING_BLOCKED)
					{
						$type_details = $wpdb->get_row("SELECT t1.*, t2.total FROM ".$wpdb->prefix . "wpbl_types t1 LEFT JOIN (SELECT type_id, COUNT(*) AS total FROM ".$wpdb->prefix."wpbl_banners WHERE registered+24*3600*days_purchased >= '".time()."' AND status != '".STATUS_DRAFT."' AND status < '".STATUS_PENDING."' AND deleted = '0' GROUP BY type_id) t2 ON t2.type_id = t1.id WHERE t1.id = '".$banner_details["type_id"]."' AND t1.deleted = '0'", ARRAY_A);
						if (intval($banner_details["blocked"]) >= $banner_details["registered"]) {
							$registered = time() - $banner_details["blocked"] + $banner_details["registered"];
						} else $registered = $banner_details["registered"];
						$sql = "UPDATE ".$wpdb->prefix."wpbl_banners SET status = '".($banner_details["price"] > 0 ? STATUS_ACTIVE_BYUSER : STATUS_ACTIVE_BYADMIN)."', registered = '".$registered."' WHERE id = '".$id."'";
						$wpdb->query($sql);
						setcookie("wpbannerslite_info", "Banner successfully unblocked", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
						die();
					}
					else
					{
						setcookie("wpbannerslite_error", "You can not unblock this banner", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=wp-banners-lite-banners');
						die();
					}
					break;
			}
		}
	}

	function admin_warning() {
		echo '
		<div class="updated"><p><strong>Banners Lite plugin almost ready.</strong> You must do some <a href="admin.php?page=wp-banners-lite">settings</a> for it to work.</p></div>
		';
	}

	function admin_header()
	{
		global $wpdb;
		echo '
		<link rel="stylesheet" type="text/css" href="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/css/style.css?ver=1.28" media="screen" />
		<script type="text/javascript">
			function wpbannerslite_submitOperation() {
				var answer = confirm("Do you really want to continue?")
				if (answer) return true;
				else return false;
			}
		</script>';
	}

	function front_header()
	{
		echo '
		<link rel="stylesheet" type="text/css" href="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/css/front.css?ver=1.28" media="screen" />';
	}

	function shortcode_show($_atts) {
		if ($this->check_settings() === true) {
			$id = intval($_atts["id"]);
			return wpbanners_show($id, false);
		}
		return "";
	}	
	
	function check_banner_details($_banner) {
		global $wpdb;
		unset($errors);
		if (strlen($_banner["title"]) < 3) $errors[] = "title is too short";
		else if (strlen($_banner["title"]) > 128) $errors[] = "title is too long";
		if (!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $_banner["url"]) && strlen($_banner["url"]) > 0) $errors[] = "banner URL must be valid URL";
		if (!eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$", $_banner["email"]) && strlen($_banner["email"]) > 0) $errors[] = "e-mail must be valid e-mail address";
		$type_details = $wpdb->get_row("SELECT t1.*, t2.total FROM ".$wpdb->prefix . "wpbl_types t1 LEFT JOIN (SELECT type_id, COUNT(*) AS total FROM ".$wpdb->prefix."wpbl_banners WHERE registered+24*3600*days_purchased >= '".time()."' AND status != '".STATUS_DRAFT."' AND status < '".STATUS_PENDING."' AND deleted = '0' GROUP BY type_id) t2 ON t2.type_id = t1.id WHERE t1.id = '".$_banner["type_id"]."' AND t1.deleted = '0'", ARRAY_A);
		if (intval($type_details["id"]) == 0) $errors[] = "invalid banner type";
		if (strlen($_banner["file"]) == 0) $errors[] = "banner image must be uploded";
		if (empty($errors)) return true;
		else return $errors;
	}

	function page_switcher ($_urlbase, $_currentpage, $_totalpages)
	{
		$pageswitcher = "";
		if ($_totalpages > 1)
		{
			$pageswitcher = "<div class='tablenav bottom'><div class='tablenav-pages'>Pages: <span class='pagiation-links'>";
			if (strpos($_urlbase,"?") !== false) $_urlbase .= "&amp;";
			else $_urlbase .= "?";
			if ($_currentpage == 1) $pageswitcher .= "<a class='page disabled'>1</a> ";
			else $pageswitcher .= " <a class='page' href='".$_urlbase."p=1'>1</a> ";

			$start = max($_currentpage-3, 2);
			$end = min(max($_currentpage+3,$start+6), $_totalpages-1);
			$start = max(min($start,$end-6), 2);
			if ($start > 2) $pageswitcher .= " <b>...</b> ";
			for ($i=$start; $i<=$end; $i++)
			{
				if ($_currentpage == $i) $pageswitcher .= " <a class='page disabled'>".$i."</a> ";
				else $pageswitcher .= " <a class='page' href='".$_urlbase."p=".$i."'>".$i."</a> ";
			}
			if ($end < $_totalpages-1) $pageswitcher .= " <b>...</b> ";

			if ($_currentpage == $_totalpages) $pageswitcher .= " <a class='page disabled'>".$_totalpages."</a> ";
			else $pageswitcher .= " <a class='page' href='".$_urlbase."p=".$_totalpages."'>".$_totalpages."</a> ";
			$pageswitcher .= "</span></div></div>";
		}
		return $pageswitcher;
	}
	
	function cut_string($_string, $_limit=40) {
		if (strlen($_string) > $_limit) return substr($_string, 0, $_limit-3)."...";
		return $_string;
	}
	
	function period_to_string($period) {
		$period_str = "";
		$days = floor($period/(24*3600));
		$period -= $days*24*3600;
		$hours = floor($period/3600);
		$period -= $hours*3600;
		$minutes = floor($period/60);
		if ($days > 1) $period_str = $days." days, ";
		else if ($days == 1) $period_str = $days." day, ";
		if ($hours > 1) $period_str .= $hours." hours, ";
		else if ($hours == 1) $period_str .= $hours." hour, ";
		else if (!empty($period_str)) $period_str .= "0 hours, ";
		if ($minutes > 1) $period_str .= $minutes." minutes";
		else if ($minutes == 1) $period_str .= $minutes." minute";
		else $period_str .= "0 minutes";
		return $period_str;
	}
	
	function add_url_parameters($_base, $_params) {
		if (strpos($_base, "?")) $glue = "&";
		else $glue = "?";
		$result = $_base;
		if (is_array($_params)) {
			foreach ($_params as $key => $value) {
				$result .= $glue.rawurlencode($key)."=".rawurlencode($value);
				$glue = "&";
			}
		}
		return $result;
	}
}

function wpbannerslite_deactivate() {
	echo '
	<div class="updated"><p>If you wish to use <strong>Banners Lite plugin</strong>, please deactivate <strong>Banner Manager plugin</strong>.</p></div>';
}

if (!class_exists("wpbanners_class")) {
	$wpbannerslite = new wpbannerslite_class();
} else {
	add_action('admin_notices', 'wpbannerslite_deactivate');
}

if (!function_exists("wpbanners_show")) {
	function wpbanners_show($_id, $_echo = true) {
		$cid = "a_".md5($_id.rand(1,1000));
		$result = '
		<a id="'.$cid.'" style="display: none;" href="#"></a>	
		<!--[if IE]>
		<script type="text/javascript" src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/wpbanners_show.php?id='.$_id.'&cid='.$cid.'"></script>
		<![endif]-->
		<script defer="defer" type="text/javascript" src="'.get_bloginfo("wpurl").'/wp-content/plugins/wp-banners-lite/wpbanners_show.php?id='.$_id.'&cid='.$cid.'"></script>';
		if ($_echo) echo $result;
		else return $result;
	}
}
?>