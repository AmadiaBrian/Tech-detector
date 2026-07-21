<?php
// Migration file for website monitoring feature

class AddWebsiteMonitoringTables {
    public function up($db) {
        // Create website_monitors table
        $db->exec("CREATE TABLE IF NOT EXISTS website_monitors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            url VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_checked_at TIMESTAMP NULL,
            last_status_code INT,
            last_error VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY user_url_unique (user_id, url)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Create website_monitor_logs table
        $db->exec("CREATE TABLE IF NOT EXISTS website_monitor_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            monitor_id INT NOT NULL,
            status_code INT,
            response_time FLOAT,
            error_message VARCHAR(255),
            ssl_valid_until TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (monitor_id) REFERENCES website_monitors(id) ON DELETE CASCADE,
            INDEX idx_monitor_created (monitor_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    public function down($db) {
        $db->exec("DROP TABLE IF EXISTS website_monitor_logs");
        $db->exec("DROP TABLE IF EXISTS website_monitors");
    }
}
