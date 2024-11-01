<?php
include_once('../../../wp-load.php');
$id_str = $_GET["id"];
$id_str = preg_replace('/[^a-zA-Z0-9]/', '', $id_str);
$banner_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."wpbl_banners WHERE id_str = '".$id_str."'", ARRAY_A);

if (!empty($banner_details["url"])) {
	$sql = "UPDATE ".$wpdb->prefix."wpbl_banners SET clicks = clicks + 1 WHERE id = '".$banner_details["id"]."'";
	$wpdb->query($sql);
	header("Location: ".$banner_details["url"]);
	die();
}
header("Location: ".get_bloginfo("wpurl"));
die();
?>