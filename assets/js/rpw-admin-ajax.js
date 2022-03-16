/**
 * ajax functions for Raven Prestashop to WooCommerce Migration Tool
 *
 * @since 1.0.0
 */

/**
 * Test database connection
 * 
 * @since 1.0.0
 */
function connection_test($) {

    var container   = '.rpw-section-database-connection';
    var button      = '#rpw_test_database';
  
    $(container).on('click', button, function(e) {
        e.preventDefault();

        var form_data = $(container + ' input').serialize(),
            testMessage = $('#rpw_database_test_message');

        $.ajax( {
            type: 'POST',
            url: rpwObject.ajaxurl,
            data: {
                action: 'rpw_test_prestashop_connection',
                security: $('#rpw_form_import').find('#_wpnonce').val(),
                form_data: form_data
            },
            success: function(data) {
                testMessage.html(data);
            },
            error: function(jqXHR, exception) {
                error_report(jqXHR, exception);
            }
        } );
    } );
}

/**
 * Get Categories
 * 
 * @since 1.0.0
 */
function get_categories($) {
    var container   = '.rpw-import-body',
        button      = '#rpw_import_categories',
        loader = '<img class="loader rvo-inline-icon" src="' + rpwObject.loaderURL + '">',
        successIcon = '<span class="success-icon rvo-inline-icon dashicons dashicons-saved"></span>',
        errorIcon = '<span class="error-icon rvo-inline-icon dashicons dashicons-no-alt"></span>';

    $(container).on('click', button, function(e) {
        e.preventDefault(e); 

        container = $(this).closest(container);
        container.append('<div class="rpw-overlay">'+loader+'</div>');

        $.ajax( {
            type: 'POST',
            url: rpwObject.ajaxurl,
            data: {
                action: 'rpw_get_product_categories',
                security: $('#rpw_form_import').find('#_wpnonce').val()
            },
            success: function(data) {
                var text = data > 1 ? ' Categories' : ' Category';
                $(container).append('<div class="message">'+data+text+' Found</div>');
            },
            error: function(jqXHR, exception) {
                error_report(jqXHR, exception);
            }
        } );

    } );
}

/**
 * Get Categories
 * 
 * @since 1.0.0
 */
 function get_import_data($) {
    var container   = '.rpw-section-import-data',
        button      = '.button',
        loader      = '<img class="loader rvo-inline-icon" src="' + rpwObject.loaderURL + '">',
        successIcon = '<span class="success-icon rvo-inline-icon dashicons dashicons-saved"></span>',
        errorIcon   = '<span class="error-icon rvo-inline-icon dashicons dashicons-no-alt"></span>';

    $(container).on('click', button, function(e) {
        e.preventDefault(e); 

        var ID = $(this).attr('id'); 

        container = $(this).closest('.rpw-import-body');
        container.append('<div class="rpw-overlay">'+loader+'</div>');

        console.log(get_action(ID));
        $.ajax( {
            type: 'POST',
            url: rpwObject.ajaxurl,
            data: {
                action: get_action(ID),
                security: $('#rpw_form_import').find('#_wpnonce').val()
            },
            success: function(data) {
                $(container).append('<div class="message">'+data+'</div>');

                container.find('.rpw-overlay').remove();
            },
            error: function(jqXHR, exception) {
                error_report(jqXHR, exception);
            }
        } );

        /*$.ajax( {
            type: 'POST',
            url: rpwObject.ajaxurl,
            cache: false,
            data: { action: get_action(ID), security: $('#rpw_form_import').find('#_wpnonce').val() },
            }).done( function(result) {
                $(container).append('<div class="message">'+result+'</div>');
                container.find('.rpw-overlay').remove();
            }).fail( function(jqXHR, exception) {
                error_report(jqXHR, exception);
            }).always( function() {

            });
            return false;
        });*/

    } );
}

/**
 * Get ajax action
 * 
 * @since 1.0.0
 */
function get_action(ID) {
    var action = '';

    switch(ID) {
        case 'rpw_import_categories' :
            action = 'rpw_get_product_categories';
            break;
        case 'rpw_import_products' :
            action = 'rpw_get_the_products';
            break;
        case 'rpw_import_users' :
            action = 'rpw_get_the_users';
            break;
        case 'rpw_import_orders' :
            action = 'rpw_get_the_orders';
            break;
        default :
            action = '';
    }

    return action;
}

/**
 * Error tracking
 * 
 * @since 1.0.0
 */
 function error_report(jqXHR, exception) {
    if (jqXHR.status === 0) {
        alert('Not connect.\n Verify Network.');
    } else if (jqXHR.status == 404) {
        alert('Requested page not found. [404]');
    } else if (jqXHR.status == 500) {
        alert('Internal Server Error [500].');
    } else if (exception === 'parsererror') {
        alert('Requested JSON parse failed.');
    } else if (exception === 'timeout') {
        alert('Time out error.');
    } else if (exception === 'abort') {
        alert('Ajax request aborted.');
    } else {
        alert('Uncaught Error.\n' + jqXHR.responseText);
    }
}

(function($) {
	
	$(document).ready(function() {
        connection_test($);

       // get_categories($);
        get_import_data($);
    } );

} )( jQuery );