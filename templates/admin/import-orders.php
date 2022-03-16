<?php
/**
 * Import Orders section
 * 
 * @since 1.0.0
 */

 ?>

 <section class="section rpw-section-import-orders">
    <header class="rpw-section-header rpw-import-header">
        <h2>Import Orders</h2>
    </header>

    <div class="rpw-import-body">
        <?php submit_button( __('Get Orders', 'raven-prestashop-woocommerce'), 'secondary', 'rpw_import_orders' ); ?>
        <span id="rpw_import_orders_message" class="rpw-action_message"></span>
    </div>
</section>

