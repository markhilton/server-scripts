<?php

echo "Testing Redis... ";

try {
	$redis = new Redis();
    $redis->connect('redis.host', 6379, 1); // 1 sec timeout

    die("OK\n"); 
} catch (Exception $e) {
    die("ERROR ".$e->getMessage()."\n");
}