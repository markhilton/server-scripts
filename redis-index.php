<?php
/**
 * WP Redix Index
 * 
 * Redis caching system for WordPress. Inspired by Jim Westergren & Jeedo Aquino
 * 
 * @author Mark Hilton
 * @see http://www.jeedo.net/lightning-fast-wordpress-with-nginx-redis/
 * @see http://www.jimwestergren.com/wordpress-with-redis-as-a-frontend-cache/
 */

// do not run if explicitly requested
#if (isset($_GET['nocache']) or (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'max-age=0')) {
if (isset($_GET['nocache'])) {
    header('Redis-cache: no cach requested');
    require 'index.php';
    die();
}


/**
 * start Redis cache processing
 *
 * 1. try to connect to redis server
 * 2. fetch or create domain host database
 * 3. define URL storage key  
 *
 */

define('REDIS_CACHE_DEBUG', true);        // set to 1 if you wish to see execution time and cache actions
define('REDIS_CACHE_START', microtime()); // start timing page exec

try {
    $redis = new Redis();
    $redis->connect('redis.host', 6379, 1); // 1 sec timeout
}
// terminate script if cannot connect to Redis server
// and gracefully fall back into regular WordPress
catch (Predis\CommunicationException $exception) {
    require 'index.php';
    die();
}

$redis->select(0);
$domains = $redis->get('domains');

// fetch redis database ID for current host
if (isset($domains[ $_SERVER['HTTP_HOST'] ]['id'])) {
    $db = $domains[ $_SERVER['HTTP_HOST'] ]['id'];
    
    $redis->select($db);
}

// create new redis database if current host does not have one
else {
    if (! is_array($domains)) {
        $domains = [];
    }

    $db = count($domains) + 1;

    $domains[ $_SERVER['HTTP_HOST'] ]['id'] = $db;

    $redis->set('domains', $domains);

    $redis->select($db);

    // build URL key
    $url = isset($domains[ $_SERVER['HTTP_HOST'] ]['cache_query']) ? $_SERVER['REQUEST_URI'] : strtok($_SERVER['REQUEST_URI'], '?');
    $key = md5($url);
}


/**
 * execute redis flush requests
 *
 * 1. execute page flush
 * 2. execute domain flush
 *
 */
if (isset($_GET['flush'])) {
    $redis->del($key);

    header('Redis-cache: flushed page cache');
}

if (isset($_GET['flushall'])) {
    $redis->flushDb();

    header('Redis-cache: flushed domain cache');
}

if (isset($_GET['flush']) or isset($_GET['flushall'])) {
    die(require 'index.php');
}


/**
 * execute redis flush requests
 *
 * 1. execute page flush
 * 2. execute domain flush
 *
 */
$post     = $_POST ? true : false;
$loggedin = preg_match("/wordpress_logged_in/", var_export($_COOKIE, true));

// check if a cache of the page exists
if ($redis->exists($key) && !$loggedin && !$post) {
    header('Redis-cache: fetched from cache');
    die($redis->get($key));
}


// if logged in don't cache anything
if ($post or $loggedin) {
    header('Redis-cache: cache disengaged');

    die(require 'index.php');
}

// cache the page
else {
    header('Redis-cache: storing new data');

    ob_start();

    require 'index.php';

    $html = ob_get_flush();

    // log syslog message if cannot store objects in redis
    if (! $redis->set($key, $html)) {
        openlog('php', LOG_CONS | LOG_NDELAY | LOG_PID, LOG_USER | LOG_PERROR);
        syslog(LOG_INFO, 'Redis cannot store data. Memory: '.$redis->info('used_memory_human'));
        closelog();
    }
}
