<?php
/**
 * Import Users section
 * 
 * @since 1.0.0
 */

 ?>

 <section class="section rpw-section-import-users">
    <header class="rpw-section-header rpw-import-header">
        <h2>Import Customers</h2>
    </header>

    <div class="rpw-import-body">
        <?php submit_button( __('Get Users', 'raven-prestashop-woocommerce'), 'secondary', 'rpw_import_users' ); ?>
        <span id="rpw_import_users_message" class="rpw-action_message"></span>
    </div>
</section>

