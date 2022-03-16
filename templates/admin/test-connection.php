<?php
/**
 * Test connection to prestashop database form
 * 
 * @since 1.0.0
 */

 ?>

 <div class="section rpw-section-database-connection">
    <h2>Prestashop Database Parameters</h2>

    <?php do_action('rpw_before_database_test'); ?>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="hostname"><?php _e('Hostname', 'raven-prestashop-woocommerce'); ?></label></th>
            <td><input id="rpw_prestashop_connection_hostname" name="<?php echo $this->option_name; ?>[hostname]" type="text" size="50" value="<?php echo esc_attr($data['hostname']); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="database"><?php _e('Database', 'raven-prestashop-woocommerce'); ?></label></th>
            <td><input id="rpw_prestashop_connection_database" name="<?php echo $this->option_name; ?>[database]" type="text" size="50" value="<?php echo esc_attr($data['database']); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="username"><?php _e('Username', 'raven-prestashop-woocommerce'); ?></label></th>
            <td><input id="rpw_prestashop_connection_username" name="<?php echo $this->option_name; ?>[username]" type="text" size="50" value="<?php echo esc_attr($data['username']); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="password"><?php _e('Password', 'raven-prestashop-woocommerce'); ?></label></th>
            <td><input id="rpw_prestashop_connection_password" name="<?php echo $this->option_name; ?>[password]" type="password" size="50" value="<?php echo esc_attr($data['password']); ?>" /></td>
        </tr>
        <tr>
            <th scope="row"><label for="prefix"><?php _e('PrestaShop Table Prefix', 'raven-prestashop-woocommerce'); ?></label></th>
            <td><input id="rpw_prestashop_connection_prefix" name="<?php echo $this->option_name; ?>[prefix]" type="text" size="50" value="<?php echo esc_attr($data['prefix']); ?>" /></td>
        </tr>

        <tr>
            <th scope="row">&nbsp;</th>
            <td><?php submit_button( __('Test the database connection', 'raven-prestashop-woocommerce'), 'secondary', 'rpw_test_database' ); ?>
            <span id="rpw_database_test_message" class="action_message"></span></td>
        </tr>
    </table>

    <?php do_action('rpw_after_database_test'); ?>

</div>

