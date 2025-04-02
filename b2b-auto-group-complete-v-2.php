<?php
/*
Plugin Name: Auto Assegnazione Gruppi B2B - Versione Semplificata
Plugin URI: https://pcusato.net
Description: Assegna automaticamente i gruppi B2B in base al dominio email - versione semplificata
Version: 2.0
Author: Alessandro Mari
Text Domain: b2b-auto-group
Domain Path: /languages
*/

// Impedisci l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit;
}

// Classe principale del plugin
class B2B_Auto_Group_Simplified {

    // Nome dell'opzione nel database - nome univoco per evitare conflitti
    const MAPPINGS_OPTION = 'b2b_auto_group_mappings_simple';
    const LOGS_OPTION = 'b2b_auto_group_logs_simple';
    const MIGRATION_DONE_OPTION = 'b2b_auto_group_migration_done_simple';
    
    // Campo meta B2B CORRETTO identificato
    const B2B_GROUP_META_FIELD = 'wcb2b_group';

    // Costruttore
    public function __construct() {
        // Migrazione dati dalle versioni precedenti (solo una volta)
        add_action('init', [$this, 'check_migration'], 5);
        
        // Aggiungi hook per la registrazione utente
        add_action('user_register', [$this, 'assign_b2b_group'], 10, 1);
        
        // Aggiungi hook per l'aggiornamento del profilo
        add_action('profile_update', [$this, 'assign_b2b_group'], 10, 1);
        
        // Hook per l'autenticazione
        add_action('wp_login', [$this, 'verify_user_group_on_login'], 10, 2);
        
        // Aggiungi la pagina di amministrazione
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Registra le impostazioni
        add_action('admin_init', [$this, 'register_settings']);
        
        // Aggiungi notifica nella pagina di modifica dell'utente
        add_action('admin_notices', [$this, 'show_user_group_info']);
    }

    // Verifica se è necessaria la migrazione dei dati
    public function check_migration() {
        $migration_done = get_option(self::MIGRATION_DONE_OPTION, false);
        
        // Se la migrazione non è ancora stata fatta
        if (!$migration_done) {
            $this->migrate_data();
            update_option(self::MIGRATION_DONE_OPTION, true);
        }
    }

    // Migra i dati dalle versioni precedenti del plugin
    public function migrate_data() {
        // Non eseguire la migrazione se le nostre mappature esistono già
        $current_mappings = get_option(self::MAPPINGS_OPTION, []);
        if (!empty($current_mappings)) {
            return;
        }
        
        // Elenco di tutte le possibili opzioni da controllare
        $possible_mappings_options = [
            'b2b_auto_group_mappings',
            'b2b_auto_group_mappings_final',
            'b2b_auto_group_mappings_optimized',
            'b2b_auto_group_mappings_complete',
            'b2b_auto_group_mappings_debug',
            'b2b_auto_group_mappings_final_correct',
            'wcb2b_auto_group_mappings',
            'wcb2b_domain_group_mappings',
            'b2b_domain_group_mappings'
        ];
        
        // Cerca mappature da migrare
        $mappings_found = false;
        $merged_mappings = [];
        
        foreach ($possible_mappings_options as $option_name) {
            $old_mappings = get_option($option_name, []);
            if (!empty($old_mappings) && is_array($old_mappings)) {
                $merged_mappings = array_merge($merged_mappings, $old_mappings);
                $mappings_found = true;
            }
        }
        
        if ($mappings_found) {
            // Aggiorna le nuove mappature
            update_option(self::MAPPINGS_OPTION, $merged_mappings);
        } else {
            // Non sono state trovate mappature, crea un'opzione vuota
            update_option(self::MAPPINGS_OPTION, []);
        }
        
        // Elenco di tutte le possibili opzioni log da controllare
        $possible_logs_options = [
            'b2b_auto_group_logs',
            'b2b_auto_group_logs_final',
            'b2b_auto_group_logs_optimized',
            'b2b_auto_group_logs_complete',
            'b2b_auto_group_logs_debug',
            'b2b_auto_group_logs_final_correct',
            'wcb2b_auto_group_logs',
            'wcb2b_assignment_logs',
            'b2b_assignment_logs'
        ];
        
        // Cerca log da migrare
        $logs_found = false;
        $merged_logs = [];
        
        foreach ($possible_logs_options as $option_name) {
            $old_logs = get_option($option_name, []);
            if (!empty($old_logs) && is_array($old_logs)) {
                $merged_logs = array_merge($merged_logs, $old_logs);
                $logs_found = true;
            }
        }
        
        if ($logs_found) {
            // Aggiorna i nuovi log
            update_option(self::LOGS_OPTION, $merged_logs);
        } else {
            // Non sono stati trovati log, crea un'opzione vuota
            update_option(self::LOGS_OPTION, []);
        }
    }
    
    // Ottieni mappature
    public function get_mappings() {
        $mappings = get_option(self::MAPPINGS_OPTION, []);
        if (!is_array($mappings)) {
            return [];
        }
        return $mappings;
    }
    
    // Ottieni log
    public function get_logs() {
        $logs = get_option(self::LOGS_OPTION, []);
        if (!is_array($logs)) {
            return [];
        }
        return $logs;
    }
    
    // Funzione per assegnare il gruppo B2B
    public function assign_b2b_group($user_id) {
        // Ottieni informazioni sull'utente
        $user_info = get_userdata($user_id);
        
        // Verifica che l'utente esista
        if (!$user_info) {
            return false;
        }
        
        $email = $user_info->user_email;
        
        // Estrai il dominio dall'email
        $domain = substr(strrchr($email, "@"), 1);
        
        // Ottieni le associazioni dominio-gruppo
        $domain_groups = $this->get_mappings();
        
        // Verifica se il dominio è nella lista
        if (array_key_exists($domain, $domain_groups)) {
            $gruppo = $domain_groups[$domain];
            
            // Aggiorna il campo meta effettivamente utilizzato (wcb2b_group)
            update_user_meta($user_id, self::B2B_GROUP_META_FIELD, $gruppo);
            
            // Salva log dell'operazione
            $log_entry = sprintf(
                'Email: %s, Dominio: %s, Gruppo assegnato: %s, Campo: %s, Data: %s',
                $email,
                $domain,
                $gruppo,
                self::B2B_GROUP_META_FIELD,
                current_time('mysql')
            );
            
            $logs = $this->get_logs();
            array_unshift($logs, $log_entry); // Aggiungi in cima
            $logs = array_slice($logs, 0, 100); // Mantieni solo gli ultimi 100 log
            update_option(self::LOGS_OPTION, $logs);
            
            // Meta per tracciamento interno
            update_user_meta($user_id, '_b2b_auto_group_assigned', $gruppo);
            update_user_meta($user_id, '_b2b_auto_group_timestamp', current_time('mysql'));
            update_user_meta($user_id, '_b2b_auto_group_domain', $domain);
            
            return true;
        }
        
        return false;
    }
    
    // Funzione per verificare gruppo al login
    public function verify_user_group_on_login($user_login, $user) {
        $user_id = $user->ID;
        $last_assignment = get_user_meta($user_id, '_b2b_auto_group_timestamp', true);
        
        // Se sono passati più di 30 giorni dall'ultima assegnazione, riapplica
        if (empty($last_assignment) || strtotime($last_assignment) < strtotime('-30 days')) {
            $this->assign_b2b_group($user_id);
        }
    }
    
    // Mostra info sui gruppi nella pagina utente
    public function show_user_group_info() {
        $screen = get_current_screen();
        
        // Visualizza solo nella pagina di modifica utente
        if (isset($screen->id) && in_array($screen->id, ['user-edit', 'profile'])) {
            $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : get_current_user_id();
            
            // Ottieni il gruppo assegnato
            $assigned_group = get_user_meta($user_id, '_b2b_auto_group_assigned', true);
            
            if (!empty($assigned_group)) {
                $timestamp = get_user_meta($user_id, '_b2b_auto_group_timestamp', true);
                $domain = get_user_meta($user_id, '_b2b_auto_group_domain', true);
                $current_group = get_user_meta($user_id, self::B2B_GROUP_META_FIELD, true);
                
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p>' . sprintf(
                    __('Gruppo B2B <strong>%s</strong> assegnato automaticamente in base al dominio <strong>%s</strong> il %s', 'b2b-auto-group'),
                    esc_html($assigned_group),
                    esc_html($domain),
                    esc_html($timestamp)
                ) . '</p>';
                
                // Mostra info sul campo meta utilizzato
                echo '<p><strong>Campo meta utilizzato:</strong> <code>' . esc_html(self::B2B_GROUP_META_FIELD) . '</code> = <strong>' . esc_html($current_group) . '</strong></p>';
                
                // Aggiungi pulsante per riassegnare manualmente
                echo '<p>';
                echo '<a href="' . wp_nonce_url(
                    admin_url('admin.php?page=b2b-auto-group&reassign_user=' . $user_id),
                    'b2b_auto_group_reassign_user'
                ) . '" class="button">' . __('Riassegna Gruppo', 'b2b-auto-group') . '</a>';
                echo '</p>';
                
                echo '</div>';
            }
        }
    }
    
    // Aggiungi pagina di amministrazione
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Auto Assegnazione Gruppi B2B', 'b2b-auto-group'),
            __('Auto Gruppi B2B', 'b2b-auto-group'),
            'manage_woocommerce',
            'b2b-auto-group',
            [$this, 'admin_page']
        );
    }
    
    // Registra impostazioni
    public function register_settings() {
        register_setting('b2b_auto_group_options', self::MAPPINGS_OPTION);
        register_setting('b2b_auto_group_options', self::LOGS_OPTION);
    }
    
    // Pagina di amministrazione
    public function admin_page() {
        // Verifica permessi
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Non hai permessi sufficienti per accedere a questa pagina.', 'b2b-auto-group'));
        }
        
        // Riassegna un utente
        if (isset($_GET['reassign_user']) && check_admin_referer('b2b_auto_group_reassign_user')) {
            $user_id = absint($_GET['reassign_user']);
            $result = $this->assign_b2b_group($user_id);
            
            if ($result) {
                $user_info = get_userdata($user_id);
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . sprintf(
                    __('Gruppo B2B riassegnato con successo all\'utente %s.', 'b2b-auto-group'),
                    esc_html($user_info->user_email)
                ) . '</p>';
                echo '</div>';
            }
        }
        
        // Salva nuova associazione
        if (isset($_POST['add_mapping']) && check_admin_referer('b2b_auto_group_add_mapping')) {
            $domain = sanitize_text_field($_POST['domain']);
            $group = sanitize_text_field($_POST['group']);
            
            if (!empty($domain) && !empty($group)) {
                // Ottieni le mappature esistenti
                $mappings = $this->get_mappings();
                
                // Aggiungi la nuova mappatura
                $mappings[$domain] = $group;
                
                // Salva le mappature aggiornate
                update_option(self::MAPPINGS_OPTION, $mappings);
                
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . sprintf(
                    __('Associazione aggiunta: dominio <strong>%s</strong> → gruppo <strong>%s</strong>', 'b2b-auto-group'),
                    esc_html($domain),
                    esc_html($group)
                ) . '</p>';
                echo '</div>';
            }
        }
        
        // Elimina associazione
        if (isset($_GET['delete_domain']) && check_admin_referer('b2b_auto_group_delete_mapping')) {
            $domain = sanitize_text_field($_GET['delete_domain']);
            
            // Ottieni le mappature esistenti
            $mappings = $this->get_mappings();
            
            if (isset($mappings[$domain])) {
                // Rimuovi la mappatura
                unset($mappings[$domain]);
                
                // Salva le mappature aggiornate
                update_option(self::MAPPINGS_OPTION, $mappings);
                
                // Elimina anche dalle opzioni delle versioni precedenti
                $old_options = [
                    'b2b_auto_group_mappings',
                    'b2b_auto_group_mappings_final',
                    'b2b_auto_group_mappings_optimized',
                    'b2b_auto_group_mappings_complete',
                    'b2b_auto_group_mappings_debug',
                    'b2b_auto_group_mappings_final_correct',
                    'wcb2b_auto_group_mappings',
                    'wcb2b_domain_group_mappings',
                    'b2b_domain_group_mappings'
                ];
                
                foreach ($old_options as $option_name) {
                    $old_mappings = get_option($option_name, []);
                    if (isset($old_mappings[$domain])) {
                        unset($old_mappings[$domain]);
                        update_option($option_name, $old_mappings);
                    }
                }
                
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . sprintf(
                    __('Associazione per il dominio <strong>%s</strong> eliminata.', 'b2b-auto-group'),
                    esc_html($domain)
                ) . '</p>';
                echo '</div>';
            }
        }
        
        // Svuota logs
        if (isset($_GET['clear_logs']) && check_admin_referer('b2b_auto_group_clear_logs')) {
            update_option(self::LOGS_OPTION, []);
            
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('Log svuotati con successo.', 'b2b-auto-group') . '</p>';
            echo '</div>';
        }
        
        // Ottieni associazioni esistenti
        $mappings = $this->get_mappings();
        
        // Ottieni log
        $logs = $this->get_logs();
        
        // Visualizza la pagina
        ?>
        <div class="wrap">
            <h1><?php _e('Auto Assegnazione Gruppi B2B - Versione Semplificata', 'b2b-auto-group'); ?></h1>
            
            <div class="notice notice-success">
                <p><strong><?php _e('Versione semplificata:', 'b2b-auto-group'); ?></strong> <?php _e('Questa versione utilizza il campo meta corretto <code>' . self::B2B_GROUP_META_FIELD . '</code> e offre un\'interfaccia semplificata.', 'b2b-auto-group'); ?></p>
            </div>
            
            <div class="card">
                <h2><?php _e('Aggiungi Nuova Associazione', 'b2b-auto-group'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('b2b_auto_group_add_mapping'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="domain"><?php _e('Dominio Email', 'b2b-auto-group'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="domain" id="domain" class="regular-text" 
                                       placeholder="esempio.com" required />
                                <p class="description">
                                    <?php _e('Inserisci solo il dominio, senza "@" (es. "esempio.com")', 'b2b-auto-group'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="group"><?php _e('Gruppo B2B', 'b2b-auto-group'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="group" id="group" class="regular-text" 
                                       placeholder="Nome del gruppo o ID" required />
                                <p class="description">
                                    <?php _e('Inserisci il nome esatto del gruppo B2B o il suo ID numerico (es. "7868")', 'b2b-auto-group'); ?>
                                </p>
                                <?php
                                // Mostra gruppi disponibili se possibile
                                $this->show_available_groups();
                                ?>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="add_mapping" class="button button-primary" 
                               value="<?php _e('Aggiungi Associazione', 'b2b-auto-group'); ?>" />
                    </p>
                </form>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Associazioni Esistenti', 'b2b-auto-group'); ?></h2>
                
                <?php if (empty($mappings)) : ?>
                    <p><?php _e('Non ci sono associazioni dominio-gruppo configurate.', 'b2b-auto-group'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Dominio Email', 'b2b-auto-group'); ?></th>
                                <th><?php _e('Gruppo B2B', 'b2b-auto-group'); ?></th>
                                <th><?php _e('Azioni', 'b2b-auto-group'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mappings as $domain => $group) : ?>
                                <tr>
                                    <td><?php echo esc_html($domain); ?></td>
                                    <td><?php echo esc_html($group); ?></td>
                                    <td>
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin.php?page=b2b-auto-group&delete_domain=' . urlencode($domain)),
                                            'b2b_auto_group_delete_mapping'
                                        ); ?>" class="button button-small" 
                                           onclick="return confirm('<?php _e('Sei sicuro di voler eliminare questa associazione?', 'b2b-auto-group'); ?>')">
                                            <?php _e('Elimina', 'b2b-auto-group'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Log Attività', 'b2b-auto-group'); ?></h2>
                
                <?php if (empty($logs)) : ?>
                    <p><?php _e('Non ci sono log di attività.', 'b2b-auto-group'); ?></p>
                <?php else : ?>
                    <a href="<?php echo wp_nonce_url(
                        admin_url('admin.php?page=b2b-auto-group&clear_logs=1'),
                        'b2b_auto_group_clear_logs'
                    ); ?>" class="button" style="margin-bottom: 10px;"
                       onclick="return confirm('<?php _e('Sei sicuro di voler cancellare tutti i log?', 'b2b-auto-group'); ?>')">
                        <?php _e('Svuota Log', 'b2b-auto-group'); ?>
                    </a>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Dettagli Operazione', 'b2b-auto-group'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td><?php echo esc_html($log); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Istruzioni', 'b2b-auto-group'); ?></h2>
                <p><?php _e('Questo plugin assegna automaticamente gli utenti ai gruppi B2B in base al dominio della loro email durante la registrazione.', 'b2b-auto-group'); ?></p>
                <ol>
                    <li><?php _e('Aggiungi associazioni dominio-gruppo utilizzando il form qui sopra', 'b2b-auto-group'); ?></li>
                    <li><?php _e('Quando un utente si registra con un\'email che ha un dominio nella lista, verrà automaticamente assegnato al gruppo corrispondente', 'b2b-auto-group'); ?></li>
                    <li><?php _e('Le associazioni funzionano anche quando un utente esistente modifica la propria email', 'b2b-auto-group'); ?></li>
                </ol>
                <p><strong><?php _e('Nota tecnica:', 'b2b-auto-group'); ?></strong> <?php _e('Questa versione utilizza il campo meta <code>' . self::B2B_GROUP_META_FIELD . '</code>, che è stato identificato come il campo utilizzato dal plugin WooCommerce B2B.', 'b2b-auto-group'); ?></p>
            </div>
        </div>
        <?php
    }
    
    // Mostra gruppi disponibili
    private function show_available_groups() {
        $all_groups = $this->get_all_b2b_groups();
        
        if (!empty($all_groups)) {
            sort($all_groups);
            
            echo '<div style="margin-top: 10px;">';
            echo '<strong>' . __('Gruppi disponibili:', 'b2b-auto-group') . '</strong>';
            echo '<ul style="background: #f8f8f8; padding: 10px; border: 1px solid #ddd; max-height: 150px; overflow-y: auto;">';
            foreach ($all_groups as $group) {
                echo '<li><code style="cursor: pointer;" onclick="document.getElementById(\'group\').value = this.innerText;">' . esc_html($group) . '</code></li>';
            }
            echo '</ul>';
            echo '<p class="description">' . __('Clicca su un gruppo per selezionarlo', 'b2b-auto-group') . '</p>';
            echo '</div>';
        }
    }
    
    // Ottieni TUTTI i gruppi B2B disponibili nel sistema
    private function get_all_b2b_groups() {
        global $wpdb;
        $groups = [];
        
        // METODO 1: Trova gruppi dal campo meta principale
        $meta_values = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
            self::B2B_GROUP_META_FIELD
        ));
        
        if (!empty($meta_values)) {
            $groups = array_merge($groups, $meta_values);
        }
        
        // METODO 2: Cerca gruppi in altri campi meta
        $other_meta_fields = [
            'wcb2b_customer_group',
            '_wcb2b_customer_group',
            'customer_group',
            '_customer_group',
            '_wcb2b_group'
        ];
        
        foreach ($other_meta_fields as $meta_key) {
            if ($meta_key == self::B2B_GROUP_META_FIELD) continue; // Salta il campo principale, già controllato
            
            $meta_values = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
                $meta_key
            ));
            
            if (!empty($meta_values)) {
                $groups = array_merge($groups, $meta_values);
            }
        }
        
        // METODO 3: Cerca tabelle del plugin B2B
        $possible_tables = [
            $wpdb->prefix . 'wcb2b_groups',
            $wpdb->prefix . 'wcb2b_customer_groups',
            $wpdb->prefix . 'b2b_groups',
            $wpdb->prefix . 'b2b_customer_groups'
        ];
        
        foreach ($possible_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                // Questa tabella esiste, tenta di leggere i gruppi
                
                // Verifica le colonne disponibili
                $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
                $column_names = [];
                
                foreach ($columns as $column) {
                    $column_names[] = $column->Field;
                }
                
                // Cerca colonne che potrebbero contenere l'ID o il nome del gruppo
                $id_column = in_array('id', $column_names) ? 'id' : 
                           (in_array('group_id', $column_names) ? 'group_id' : null);
                
                $name_column = in_array('name', $column_names) ? 'name' : 
                              (in_array('group_name', $column_names) ? 'group_name' : 
                              (in_array('title', $column_names) ? 'title' : null));
                
                // Se abbiamo trovato entrambe le colonne
                if ($id_column && $name_column) {
                    $results = $wpdb->get_results("SELECT $id_column, $name_column FROM $table");
                    
                    if (!empty($results)) {
                        foreach ($results as $row) {
                            // Aggiungi sia l'ID che il nome come gruppi disponibili
                            $groups[] = $row->$id_column;
                            $groups[] = $row->$name_column;
                        }
                    }
                } 
                // Se abbiamo trovato solo la colonna ID
                elseif ($id_column) {
                    $results = $wpdb->get_col("SELECT $id_column FROM $table");
                    if (!empty($results)) {
                        $groups = array_merge($groups, $results);
                    }
                }
                // Se abbiamo trovato solo la colonna nome
                elseif ($name_column) {
                    $results = $wpdb->get_col("SELECT $name_column FROM $table");
                    if (!empty($results)) {
                        $groups = array_merge($groups, $results);
                    }
                }
            }
        }
        
        // METODO 4: Usa dati inseriti dall'amministratore
        $existing_mappings = $this->get_mappings();
        if (!empty($existing_mappings)) {
            $groups = array_merge($groups, array_values($existing_mappings));
        }
        
        // Aggiungi manualmente gruppi comuni
        $common_groups = [
            '1', '2', '3', // ID comuni
            '7868', // ID già noto
            'Default', 'Standard', 'Premium', 'Gold', 'Silver', 'Bronze', // Nomi comuni
            'Ospite', 'PCUsato', 'Eni' // Nomi specifici dell'applicazione
        ];
        
        $groups = array_merge($groups, $common_groups);
        
        // Rimuovi valori vuoti, duplicati e converti tutto in stringhe
        $groups = array_filter($groups, function($value) {
            return !empty($value) && $value !== '' && $value !== null;
        });
        
        $clean_groups = [];
        foreach ($groups as $group) {
            $clean_groups[] = (string) $group;
        }
        
        // Rimuovi duplicati e ordina
        $clean_groups = array_unique($clean_groups);
        
        return $clean_groups;
    }
}

// Inizializza il plugin
$b2b_auto_group_simplified = new B2B_Auto_Group_Simplified();