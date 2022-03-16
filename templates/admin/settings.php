<?php
/**
 * Test connection to prestashop database form
 * 
 * @since 1.0.0
 */
 ?>

<div class="section rpw-section-other-settings">
    <h2>Settings</h2>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="rpw_prestashop_connection_url"><?php _e('URL', 'raven-prestashop-woocommerce'); ?></label></th>
            <td><input id="rpw_prestashop_connection_url" name="<?php echo $this->option_name; ?>[url]" type="text" size="50" value="<?php echo isset($data['url']) ? esc_attr($data['url']) : ''; ?>" /></td>
        </tr>

        <tr>
            <th scope="row"><label for="rpw_prestashop_connection_archive_id"><?php _e('Archive ID', 'raven-prestashop-woocommerce'); ?></label></th>
            <td><input id="rpw_prestashop_connection_archive_id" name="<?php echo $this->option_name; ?>[archive-id]" type="number" size="20" value="<?php echo isset($data['archive-id']) ? esc_attr($data['archive-id']) : ''; ?>" /></td>
        </tr>

        <tr>
            <th scope="row">&nbsp;</th>
            <td><?php submit_button( __('Save Settings', 'raven-prestashop-woocommerce') ); ?>
            <span id="rpw_database_test_message" class="action_message"></span></td>
        </tr>
    </table>
</div>

