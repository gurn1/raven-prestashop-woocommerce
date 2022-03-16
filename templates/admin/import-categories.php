<?php
/**
 * Import Categories section
 * 
 * @since 1.0.0
 */

 ?>

 <section class="section rpw-section-import-">
    <header class="rpw-section-header rpw-import-header">
        <h2>Import Product Categories</h2>
    </header>

    <div class="rpw-import-body">
        <?php submit_button( __('Get Categories', 'raven-prestashop-woocommerce'), 'secondary', 'rpw_import_categories' ); ?>
        <span id="rpw_import_categories_message" class="rpw-action_message"></span>
    </div>
</section>

