<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads and writes to the plugin's database tables. Also handles the active-import lock.
 */
class Site_Builder_Import_Tracker {

    private string $imports_table;
    private string $items_table;

    public function __construct() {
        global $wpdb;
        $this->imports_table = $wpdb->prefix . 'site_builder_imports';
        $this->items_table   = $wpdb->prefix . 'site_builder_import_items';
    }

    public function create_import(string $type, string $folder_name, array $settings, int $user_id): int {
        global $wpdb;
        $wpdb->insert($this->imports_table, [
            'type'        => $type,
            'status'      => 'building',
            'folder_name' => $folder_name,
            'settings'    => wp_json_encode($settings),
            'started_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
            'user_id'     => $user_id,
        ]);
        return (int)$wpdb->insert_id;
    }

    public function get_import(int $import_id): ?object {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->imports_table} WHERE id = %d",
            $import_id
        ));
        return $row ?: null;
    }

    public function get_last_completed_import(): ?object {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT * FROM {$this->imports_table} WHERE status = 'completed' ORDER BY id DESC LIMIT 1"
        );
        return $row ?: null;
    }

    /**
     * Returns the most recent CREATE or ADD import that finished successfully and has
     * not been rolled back yet. Used by the Rollback tab to decide what to offer.
     */
    public function get_last_rollbackable_import(): ?object {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT * FROM {$this->imports_table}
             WHERE status = 'completed' AND type IN ('create', 'add')
             ORDER BY id DESC LIMIT 1"
        );
        return $row ?: null;
    }

    /**
     * Find the most recent import for the Report tab.
     * Includes any terminal status (completed/cancelled/failed/rolled_back) but excludes
     * 'running' imports — those are shown in the active-import card instead.
     * Excludes rollback-type imports (the report shows the original import, not its rollback).
     */
    public function get_latest_import_for_report(): ?object {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT * FROM {$this->imports_table}
             WHERE status IN ('completed', 'cancelled', 'failed', 'rolled_back')
               AND type IN ('create', 'add')
             ORDER BY id DESC LIMIT 1"
        );
        return $row ?: null;
    }

    public function update_import(int $import_id, array $data): void {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        $wpdb->update($this->imports_table, $data, ['id' => $import_id]);
    }

    public function set_queue(int $import_id, array $queue): void {
        $this->update_import($import_id, [
            'queue'       => wp_json_encode($queue),
            'total_count' => count($queue),
        ]);
    }

    public function get_queue(int $import_id): array {
        $import = $this->get_import($import_id);
        if (!$import || !$import->queue) return [];
        $decoded = json_decode($import->queue, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function mark_finished(int $import_id, string $status = 'completed'): void {
        $this->update_import($import_id, [
            'status'      => $status,
            'finished_at' => current_time('mysql'),
        ]);
    }

    public function increment_processed(int $import_id, int $by = 1): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->imports_table} SET processed_count = processed_count + %d, updated_at = %s WHERE id = %d",
            $by, current_time('mysql'), $import_id
        ));
    }

    public function track_item(int $import_id, string $type, ?int $ref_id = null, ?string $ref_path = null, $ref_data = null): void {
        global $wpdb;
        $wpdb->insert($this->items_table, [
            'import_id'  => $import_id,
            'item_type'  => $type,
            'ref_id'     => $ref_id,
            'ref_path'   => $ref_path,
            'ref_data'   => $ref_data !== null ? wp_json_encode($ref_data) : null,
            'created_at' => current_time('mysql'),
        ]);
    }

    public function get_items(int $import_id, ?string $type = null): array {
        global $wpdb;
        if ($type) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->items_table} WHERE import_id = %d AND item_type = %s ORDER BY id",
                $import_id, $type
            )) ?: [];
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->items_table} WHERE import_id = %d ORDER BY id",
            $import_id
        )) ?: [];
    }

    public function delete_import(int $import_id): void {
        global $wpdb;
        $wpdb->delete($this->items_table, ['import_id' => $import_id]);
        $wpdb->delete($this->imports_table, ['id' => $import_id]);
    }

    public function append_error(int $import_id, string $message, array $context = []): void {
        $import = $this->get_import($import_id);
        $errors = $import && $import->errors ? json_decode($import->errors, true) : [];
        if (!is_array($errors)) $errors = [];
        $errors[] = ['message' => $message, 'context' => $context, 'at' => current_time('mysql')];
        $this->update_import($import_id, ['errors' => wp_json_encode($errors)]);
    }

    // --- Active import lock ---

    public function get_lock(): ?array {
        $lock = get_option('site_builder_active_import');
        if (!is_array($lock)) return null;
        if (!isset($lock['heartbeat'])) return null;
        $age = time() - strtotime($lock['heartbeat']);
        if ($age > 300) return null; // stale lock
        return $lock;
    }

    public function acquire_lock(int $import_id, int $user_id): bool {
        if ($this->get_lock() !== null) return false;
        update_option('site_builder_active_import', [
            'import_id'  => $import_id,
            'user_id'    => $user_id,
            'started_at' => current_time('mysql'),
            'heartbeat'  => current_time('mysql'),
        ]);
        return true;
    }

    public function refresh_lock(int $import_id): void {
        $lock = get_option('site_builder_active_import');
        if (is_array($lock) && (int)($lock['import_id'] ?? 0) === $import_id) {
            $lock['heartbeat'] = current_time('mysql');
            update_option('site_builder_active_import', $lock);
        }
    }

    public function release_lock(): void {
        delete_option('site_builder_active_import');
    }
}
