<?php

// Suppress warnings/deprecation notices from leaking into the WebDAV response body
// (would otherwise corrupt the XML/data the Zotero client is trying to parse).
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require 'settings.php';

chdir (dirname(__FILE__) . "/inc");

require 'include.php';
require_once "HTTP/WebDAV/Server/Filesystem.php";
$server = new HTTP_WebDAV_Server_Filesystem();

$server->ServeRequest( dirname( get_real_path( $data_dir ) ) );
#$server->ServeRequest( dirname( dirname(__FILE__) . '/' . $data_dir ) );

?>
