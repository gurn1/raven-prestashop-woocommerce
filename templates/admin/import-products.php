<?php
/**
 * Import Products section
 * 
 * @since 1.0.0
 */

 ?>

 <section class="section rpw-section-import-products">
    <header class="rpw-section-header rpw-import-header">
        <h2>Import Products</h2>
    </header>

    <div class="rpw-import-body">
        <?php submit_button( __('Get Products', 'raven-prestashop-woocommerce'), 'secondary', 'rpw_import_products' ); ?>
        <span id="rpw_import_products_message" class="rpw-action_message"></span>
    </div>
</section>

