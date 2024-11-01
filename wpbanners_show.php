<?php
include_once('../../../wp-load.php');
include_once(ABSPATH."wp-content/plugins/wp-banners-lite/const.php");

if (empty($wpbannerslite)) die();

if ($wpbannerslite->check_settings() === true) {
	$cid = $_GET["cid"];
	$cid = str_replace("'", "", $cid);
	if (substr($cid, 0, 2) != "a_") die();

	$type_id = intval($_GET["id"]);
	$sql = "SELECT * FROM ".$wpdb->prefix."wpbl_banners WHERE registered+24*3600*days_purchased < '".time()."' AND (status = '".STATUS_ACTIVE_BYUSER."' OR status = '".STATUS_ACTIVE_BYADMIN."') AND deleted = '0'";
	$rows = $wpdb->get_results($sql, ARRAY_A);
	foreach ($rows as $row) {
		$sql = "UPDATE ".$wpdb->prefix."wpbl_banners SET status = '".STATUS_ACTIVE_EXPIRED."' WHERE id = '".$row["id"]."'";
		$wpdb->query($sql);
		if (!empty($row["email"])) {
			$stats = 'Title: '.htmlspecialchars($row["title"], ENT_QUOTES).'
URL: '.$row["url"].'
Rotation period: '.$row["days_purchased"].' days
Shows: '.$row["shows_displayed"].'
Clicks: '.$row["clicks"].'
CTR: '.number_format($row["clicks"]*100/$row["shows_displayed"], 2, ".", "").'%';
			$tags = array("{banner_title}", "{statistics}");
			$vals = array(htmlspecialchars($row["title"], ENT_QUOTES),  $stats);
			$body = str_replace($tags, $vals, $wpbannerslite->stats_email_body);
			$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
			$mail_headers .= "From: ".$wpbannerslite->from_name." <".$wpbannerslite->from_email.">\r\n";
			$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
			wp_mail($row["email"], $wpbannerslite->stats_email_subject, $body, $mail_headers);
		}
	}

	$type_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . "wpbl_types WHERE id = '".$type_id."' AND deleted = '0'", ARRAY_A);
	if (($pos = strpos($_SERVER["HTTP_REFERER"], "wpbannerslite_show")) !== false) {
		$id_str = substr($_SERVER["HTTP_REFERER"], $pos + strlen("wpbannerslite_show="));
		if (($pos = strpos($id_str, "&")) !== false) {
			$id_str = substr($id_str, 0, $pos);
		}
		$banner_details = $wpdb->get_row("SELECT t1.*, t2.width, t2.height, t2.preview_url, t2.price, t2.title AS type_title FROM ".$wpdb->prefix."wpbl_banners t1 LEFT JOIN ".$wpdb->prefix."wpbl_types t2 ON t2.id = t1.type_id WHERE t1.type_id = '".$type_id."' AND t1.id_str='".$id_str."' AND t1.deleted = '0'", ARRAY_A);
	}
	if (intval($banner_details["id"]) == 0) $banner_details = $wpdb->get_row("SELECT t1.*, t2.width, t2.height, t2.preview_url, t2.price, t2.title AS type_title FROM ".$wpdb->prefix."wpbl_banners t1 LEFT JOIN ".$wpdb->prefix."wpbl_types t2 ON t2.id = t1.type_id WHERE t1.type_id = '".$type_id."' AND registered+24*3600*days_purchased >= '".time()."' AND status != '".STATUS_DRAFT."' AND status < '".STATUS_PENDING."' AND t1.deleted = '0' ORDER BY RAND()", ARRAY_A);

	if (intval($banner_details["id"]) == 0) {

	} else {
		$sql = "UPDATE ".$wpdb->prefix."wpbl_banners SET shows_displayed = shows_displayed + 1 WHERE id = '".$banner_details["id"]."'";
		$wpdb->query($sql);
		$banner = '<a target="_blank" class="wpbannerslite_placeholder" href="'.(!empty($banner_details["url"]) ? get_bloginfo("wpurl")."/wp-content/plugins/wp-banners-lite/redirect.php?id=".$banner_details["id_str"] : '#').'" style="'.(empty($banner_details["url"]) ? 'cursor: default; ' : '').'width: '.$banner_details["width"].'px; height: '.$banner_details["height"].'px; line-height: '.$banner_details["height"].'px; background: transparent url('.get_bloginfo("wpurl").'/wp-content/uploads/wp-banners-lite/'.$banner_details["file"].') 0 0 no-repeat;  border: 1px solid transparent !important;"'.(empty($banner_details["url"]) ? ' onclick="return false;"' : '').' title="'.htmlspecialchars($banner_details["title"], ENT_QUOTES).'">&nbsp;</a>';
		echo 'jQuery(\'#'.$cid.'\').replaceWith(\''.$banner.'\')';
	}
}
?>