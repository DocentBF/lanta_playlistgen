<?php
include_once dirname(__FILE__)."/PlaylistGenerator.php";
$macAddr = "00:1a:79:22:b3:8f";

$playlistGenerator = new \PlaylistGenerator($macAddr);
try {
    print_r($playlistGenerator->generate());
} catch (Exception $e) {
    print_r($e->getMessage());
}