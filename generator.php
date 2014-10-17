<html>
<head>
</head>
<body>
	<style>
ul {
	list-style: none;
	padding: 0;
	margin: 0 auto;
	width: 960px;
	border: 1px solid #334455;
}

li {
	margin: 0;
	display: block;
	background-color: #aaff99;
	padding: 8px 5px;
	border-bottom: 1px solid #333333;
}
</style>

<?php
require_once "lib/Belvedere.php";
date_default_timezone_set("Europe/Rome");
$belvedere = new Belvedere();
$belvedere->execute();
$belvedere->flush_log();
// echo "<pre>";
// echo json_encode($belvedere->site_config, JSON_PRETTY_PRINT);
// echo "</pre>";
?>
</body>
</html>