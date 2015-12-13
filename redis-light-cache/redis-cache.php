<?php
/*
Plugin Name: Redis Light Cache
Plugin URI: http://wordpress.org/extend/plugins/flush-cache/
Description: Adds FLUSH CACHE button to executes Redis site cache flush.
Version: 1.0
Author: Mark Hilton
Author URI: http://esecure.cc/
*/

if (!defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', get_option('siteurl').'/wp-content');
}
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH.'wp-content');
}
if (!defined('WP_PLUGIN_URL')) {
    define('WP_PLUGIN_URL',  WP_CONTENT_URL.'/plugins');
}
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR',  WP_CONTENT_DIR.'/plugins');
}

function activate_rediscache()
{
}

function deactive_rediscache()
{
}

function admin_init_rediscache()
{
}

function admin_menu_rediscache()
{
    add_options_page('Redis Cache', 'Redis Cache', 'manage_options', 'rediscache', 'options_page_rediscache');
    add_menu_page('Redis Cache', 'Redis Cache', 4, 'rediscache', 'options_page_rediscache', $icon_url, 4);
}

// add page cache flush on post save / update
add_action('save_post', 'flush_page');

function flush_page($post_id) {
    $permalink = get_permalink($post_id);

    try {
        $redis   = new Redis();
        $connect = $redis->connect('redis.host', 6379, 1); // 1 sec timeout
    }
    // terminate script if cannot connect to Redis server
    // and gracefully fall back into regular WordPress
    catch (Exception $e) {
        return;
    }

    if (! $connect) {
        return;
    }

    $redis->select(0);
    $domains = json_decode($redis->get('domains'), true);

    // fetch redis database ID for current host
    if (isset($domains[ $_SERVER['HTTP_HOST'] ]['id'])) {
        $db = $domains[ $_SERVER['HTTP_HOST'] ]['id'];
    }

    // create new redis database if current host does not have one
    else {
        if (! is_array($domains)) {
            $domains = [];
        }

        $db = count($domains) + 1;

        $domains[ $_SERVER['HTTP_HOST'] ]['id'] = $db;

        $redis->set('domains', json_encode($domains));
    }

    $redis->select($db);

    // build URL key
    $url = $_SERVER['HTTP_HOST'].parse_url($permalink, PHP_URL_PATH);
    $key = md5($url);

    $redis->delete($key); # die($key.' - '.$url); // DEBUG
}

function options_page_rediscache()
{
    try {
        $redis = new Redis();
        $redis->connect('redis.host', 6379, 1); // 1 sec timeout
    } 
    catch (Exception $e) {
        die('No redis extension');
    }

    $redis->select(0);

    $info    = $redis->info();
    $domains = json_decode($redis->get('domains'), true);

    if (isset($domains[ $_SERVER['HTTP_HOST'] ]['id'])) {
        $db = $domains[ $_SERVER['HTTP_HOST'] ]['id'];
        
        $redis->select($db);
        $pages = $redis->dbSize();
    } else {
        $pages = 0;
    }

    if ($_POST) {
        $redis->flushDb();
        $pages   = 0;
        $message = 'Domain cache flushed';
    }

    # echo '<pre>'; print_r($info); print_r(array_keys($domains)); echo '</pre>';

    ?>
    <h2>Redis Cache Engine</h2>
    <?php if ($message): ?>
    <div id="setting-error-settings_updated" class="updated settings-error" style="margin: 0"> 
        <p><?php echo $message; ?></p>
    </div>
    <?php endif; ?>

    <style>th, td { text-align: left; padding-left: 0; padding-right: 50px; }</style>
    <br>
    <table cellpadding="5">
        <tr>
            <th>Version:</th>
            <td><?php echo $info['redis_version']; ?></td>
        </tr>
        <tr>
            <th>Uptime:</th>
            <td><?php printf('%d days, %d hours and %s minutes', gmdate('d', $info['uptime_in_seconds']), gmdate('H', $info['uptime_in_seconds']), gmdate('m', $info['uptime_in_seconds'])); ?></td>
        </tr>
        <tr>
            <th>Memory used:</th>
            <td><?php echo $info['used_memory_human']; ?></td>
        </tr>
        <tr>
            <th>Memory peak:</th>
            <td><?php echo $info['used_memory_peak_human']; ?></td>
        </tr>
        <tr>
            <th>Stored pages:</th>
            <td><?php echo $pages; ?></td>
        </tr>
    </table>

    <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
        <p><input type="submit" name="flush" class="button button-primary button-large" value="Flush cache"></p>
    </form>
<?php 
}

function rediscache()
{
}

register_activation_hook(__FILE__, 'activate_rediscache');
register_deactivation_hook(__FILE__, 'deactive_rediscache');

if (is_admin()) {
    add_action('admin_init', 'admin_init_rediscache');
    add_action('admin_menu', 'admin_menu_rediscache');
}

if (!is_admin()) {
    add_action('wp_head', 'rediscache');
}

?>
