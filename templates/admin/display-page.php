<?php
/**
 * Default template for admin page
 * 
 * @since 1.0.0
 */

 ?>


<div id="rpw_admin_page" class="wrap">

    <h1><?php echo $this->name; ?></h1>

    <form id="rpw_form_import" method="post" action="options.php">
        <?php settings_fields( $this->ID ); ?>
        <?php //wp_nonce_field( 'rpw_nonce' ); ?>

        <?php require('test-connection.php'); ?>

        <?php require('settings.php'); ?>

        <div class="rpw-section-import-data">
            <?php require('import-categories.php'); ?>

            <?php require('import-products.php'); ?>

            <?php require('import-users.php'); ?>

            <?php require('import-orders.php'); ?>
        </div>

    </form>

</div>

