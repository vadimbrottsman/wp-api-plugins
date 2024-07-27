<?php
/**
 * Plugin Name: My API Plugin
 * Plugin URI: https://t.me/megeroi
 * Description: Плагин для интеграции с внешним API.
 * Version: 1.0.0
 * Author: B.Vadim
 * Author URI: https://t.me/megeroi
 * License: GPLv2 or later
 * Text Domain: my-api-plugin
 */
 
 function get_api_data() {
    $url = 'https://jsonplaceholder.typicode.com/todos';
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return $data;
}

function save_api_data($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_api_data';

    foreach ($data as $item) {
        $existing_record = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id_todos = %d", $item['id'])
        );

        if (!$existing_record) {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $item['userId'],
                    'id_todos' => $item['id'],
                    'title' => $item['title'],
                    'completed' => $item['completed'],
                )
            );
        }
    }
}

function create_api_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'my_api_data';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        id_todos INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        completed TINYINT(1) NOT NULL
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function my_api_plugin_activate() {
    create_api_table();
    $data = get_api_data();
    if ($data) {
        save_api_data($data);
    }
}
register_activation_hook(__FILE__, 'my_api_plugin_activate');

function my_api_plugin_deactivate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_api_data';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_deactivation_hook(__FILE__, 'my_api_plugin_deactivate');

add_action('admin_menu', 'my_api_plugin_admin_menu');
function my_api_plugin_admin_menu() {
    add_menu_page(
        'My API Plugin', 
        'My API Plugin', 
        'manage_options', 
        'my-api-plugin', 
        'my_api_plugin_admin_page', 
        'dashicons-networking', 
        6 
    );
}

function my_api_plugin_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_api_data';

    if (isset($_POST['sync_data'])) {
        $data = get_api_data();
        if ($data) {
            save_api_data($data);
            echo '<div class="updated"><p>Данные успешно синхронизированы.</p></div>';
        } else {
            echo '<div class="error"><p>Ошибка при получении данных.</p></div>';
        }
    }

    if (isset($_POST['search_title'])) {
        $search_term = sanitize_text_field($_POST['search_term']);
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name WHERE title LIKE %s", '%' . $search_term . '%')
        );
        if (empty($results)) {
            echo '<div class="error"><p>Задачи не найдены.</p></div>';
        } else {
            echo '<h2>Результаты поиска:</h2>';
            echo '<ul>';
            foreach ($results as $item) {
                echo '<li>Пользователь: ' . $item->user_id . '</li>';
                echo '<li>Наименование задачи: ' . $item->title . '</li>';
                if ($item->completed == 1) {
                    echo '<span style="color: green;">Выполнено</span>';
                } else {
                    echo '<span style="color: red;">Не выполнено</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
    }

    ?>
    <div class="wrap">
        <h1>My API Plugin</h1>
        <h2>Синхронизация данных</h2>
        <form method="post">
            <input type="submit" name="sync_data" class="button-primary" value="Синхронизировать данные">
        </form>

        <h2>Поиск по полю title</h2>
        <form method="post">
            <input type="text" name="search_term" placeholder="Введите текст для поиска...">
            <input type="submit" name="search_title" class="button-primary" value="Поиск">
        </form>
    </div>
    <?php
}

add_shortcode('my_api_data_random', 'my_api_plugin_shortcode_random');
function my_api_plugin_shortcode_random() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_api_data';
    $results = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE completed = 0 ORDER BY RAND() LIMIT 5"
    );
    if (empty($results)) {
        return 'Задачи не найдены.';
    }
    $output = '<ul>';
    foreach ($results as $item) {
        $output .= '<li>Пользователь: ' . $item->user_id . '</li>';
        $output .= '<span>Наименование задачи: ' . $item->title . '</span><br>';
        if ($item->completed == 1) {
            $output .= '<span><span style="color: green;">Выполнено</span></span>';
        }else{
            $output .= '<span><span style="color: red;">Не выполнено</span></span>';
        }
    }
    $output .= '</ul>';
    return $output;
}