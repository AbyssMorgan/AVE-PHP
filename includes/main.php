<?php
	$includes_path = __DIR__;
	require_once("$includes_path/dictionaries/MediaOrientation.php");
	require_once("$includes_path/services/Logs.php");
	require_once("$includes_path/services/IniFile.php");
	require_once("$includes_path/services/CommandLine.php");
	require_once("$includes_path/extensions/MediaFunctions.php");
	require_once("$includes_path/AVE.php");
	require_once("$includes_path/tools/NamesGenerator.php");
	require_once("$includes_path/tools/FileFunctions.php");

	$ave = new AVE($argv);
	$ave->execute();
?>
