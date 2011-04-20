#!/usr/bin/php
<?php

$db_host = 'localhost';
$db_user = 'database_username';
$db_pass = 'database_password';
$db_name = 'database_name';

$conn = mysql_connect($db_host,$db_user,$db_pass) or die("Failed to connect to db");
mysql_select_db($db_name, $conn) or die("Failed to select db");


$tag = $argv[1];
echo "Looking for tag '$tag'\n";
$sql = "SELECT * FROM wp_terms WHERE slug = '$tag'";
$term_id = null;

$sth = mysql_query($sql);
$result = mysql_fetch_assoc($sth);
if (!empty($result['term_id'])) {
	$term_id = $result['term_id'];
	echo "Found term_id [$term_id]\n";
}
else {
	echo "No term_id found for specified tag [$tag]\n";
	exit;
}

echo "Looking for tag taxonomies\n";
$sql = "SELECT * FROM wp_term_taxonomy WHERE term_id = $term_id";
$cat_taxonomy_id = null;
$tag_taxonomy_id = null;

$sth = mysql_query($sql);
while ($result = mysql_fetch_assoc($sth)) {
	if (!empty($result['term_taxonomy_id']) && !empty($result['taxonomy'])) {
		if ($result['taxonomy'] == 'category') {
			$cat_taxonomy_id = $result['term_taxonomy_id'];
			echo "Found category taxonomy with id [$cat_taxonomy_id]\n";
		}
		elseif ($result['taxonomy'] == 'post_tag') {
			$tag_taxonomy_id = $result['term_taxonomy_id'];
			echo "Found post_tag taxonomy with id [$tag_taxonomy_id]\n";
		}
		else {
			echo "Ignoring unknown taxonomy [" . $result['taxonomy'] . "]\n";
		}
	}
}

if (!$cat_taxonomy_id || !$tag_taxonomy_id) {
	echo "Missing either category or post_tag taxonomy ID\n";
	exit;
}

echo "Looking for all posts tagged with tag [$tag]\n";
$sql = "SELECT * FROM wp_term_relationships WHERE term_taxonomy_id = $tag_taxonomy_id";
$sth = mysql_query($sql);
$tagged_objects = array();
while ($result = mysql_fetch_assoc($sth)) {
	if (!empty($result['object_id'])) {
		$tagged_objects[] = $result['object_id'];
	}
}

echo "Found " . count($tagged_objects) . " posts\n";

echo "Moving posts tagged with [$tag] to category [$tag]\n";
$stats = array();
$stats['all'] = 0;
$stats['ok'] = 0;
$stats['failed'] = 0;
foreach ($tagged_objects as $object_id) {
	echo "Checking if post [$object_id] is already in category [$tag]\n";
	$sql = "SELECT * FROM wp_term_relationships WHERE object_id = $object_id AND term_taxonomy_id = $cat_taxonomy_id";
	$in_category = false;
	$sth = mysql_query($sql);
	$result = mysql_fetch_assoc($sth);
	if (!empty($result)) {
		$in_category = true;
		echo "Already in category. Skipping\n";
	}
	else {
		echo "Not yet in category. Processing\n";
		$stats['all']++;
	}

	if (!$in_category) {
		$sql = "INSERT INTO wp_term_relationships SET object_id = $object_id, term_taxonomy_id = $cat_taxonomy_id";
		$sth = mysql_query($sql);
		$affected = mysql_affected_rows();
		if ($affected > 0) {
			echo "Added post [$object_id] to category [$tag]\n";
			$stats['ok']++;
		}
		else {
			echo "Failed to add post [$object_id] to category [$tag]\n";
			$stats['failed']++;
		}
	}
}

print_r($stats);

?>
