<?php
/**
 * WordPress Redis Index
 * 
 * Redis caching system for WordPress. Inspired by Jim Westergren & Jeedo Aquino
 * 
 * @author Mark Hilton
 * @see http://www.jeedo.net/lightning-fast-wordpress-with-nginx-redis/
 * @see http://www.jimwestergren.com/wordpress-with-redis-as-a-frontend-cache/
 */

logger(str_repeat('-', 100));
logger('Starting Redis caching engine connection...');

// do not run if explicitly requested
#if (isset($_GET['nocache']) or (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'max-age=0')) {
if (isset($_GET['nocache'])) {
    logger('NOCACHE explicitly requested. terminating...');

    header('Redis-cache: no cach requested');
    die(require 'index.php');
}

// do not run if request is a POST or user is logged into WordPress
if ($_POST or preg_match("/wordpress_logged_in/", var_export($_COOKIE, true))) {
    logger('NOCACHE explicitly requested. terminating...');

    header('Redis-cache: cache disengaged');
    die(require 'index.php');
}


/**
 * start Redis cache processing
 *
 * 1. try to connect to redis server
 * 2. fetch or create domain host database
 * 3. define URL storage key  
 *
 */

try {
    $redis = new Redis();
    $connect = $redis->connect('redis.host', 6379, 1); // 1 sec timeout
}
// terminate script if cannot connect to Redis server
// and gracefully fall back into regular WordPress
catch (Exception $e) {
    logger('redis extension failed. terminating...');
    
    die(require 'index.php');
}

if ($connect) {
    logger('connected to Redis cache OK. retrieving domains list');
} else {
    logger('connection to Redis cache FAILED. terminating...');    
    die(require 'index.php');
}

$redis->select(0);
$domains = json_decode($redis->get('domains'), true);

// fetch redis database ID for current host
if (isset($domains[ $_SERVER['HTTP_HOST'] ]['id'])) {
    $db = $domains[ $_SERVER['HTTP_HOST'] ]['id'];

    logger(sprintf('current domain [id: %d]: %s found in cache. Total domains stored: %d', $db, $_SERVER['HTTP_HOST'], count($domains)));
}

// create new redis database if current host does not have one
else {
    if (! is_array($domains)) {
        $domains = [];
    }

    $db = count($domains) + 1;

    $domains[ $_SERVER['HTTP_HOST'] ]['id'] = $db;

    logger(sprintf('current domain: %s does not exist in cache - creating. Total domains stored: %d', $_SERVER['HTTP_HOST'], count($domains)));

    $redis->set('domains', json_encode($domains));
}

$redis->select($db);

// build URL key
$url = isset($domains[ $_SERVER['HTTP_HOST'] ]['cache_query']) ? $_SERVER['REQUEST_URI'] : strtok($_SERVER['REQUEST_URI'], '?');
$key = md5($_SERVER['HTTP_HOST'].$url);

logger(sprintf('requested URI: %s, key: %s', $url, $key));

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
    logger('flushing page cache requestes');
}

if (isset($_GET['flushall'])) {
    $redis->flushDb();

    header('Redis-cache: flushed domain cache');
    logger('flushing domain cache requestes');
}

if (isset($_GET['flush']) or isset($_GET['flushall'])) {
    die(require 'index.php');
}


/**
 * cache requests and server cached content
 *
 * 1. serve cached content if url key found
 * 2. store content into cache if url key does not exist
 *
 */

// check if a cache of the page exists
if ($redis->exists($key)) {
    header('Redis-cache: fetched from cache');
    logger('fetching content from the cache. key: '.$key);
    die($redis->get($key));
}

// cache the page
else {
    header('Redis-cache: storing new data');
    ob_start('callback');
    require 'index.php';
    ob_end_flush();
    logger('flushing output buffer to the browser'); 
}

function logger($message) {

    global $cc;

    if (file_exists('.redis.log') or file_exists('/tmp/.redis.log')) {
        syslog(LOG_INFO, sprintf('STEP %d: %s', ++$cc, $message));
    }
}

function callback($buffer) {

    global $redis, $key;

    if (trim($buffer) != '' and $redis->set($key, $buffer)) {
        // log syslog message if cannot store objects in redis
        logger('storing content in the cache. page count: '.$redis->dbSize());        
    } else {
        logger('Redis cannot store data. Memory: '.$redis->info('used_memory_human'));

        openlog('php', LOG_CONS | LOG_NDELAY | LOG_PID, LOG_USER | LOG_PERROR);
        syslog(LOG_INFO, 'Redis cannot store data. Memory: '.$redis->info('used_memory_human'));
        closelog();
    }

    return $buffer;
}
