/**
 * My Materialpool Admin JS
 *
 * @since      0.0.1
 * @author     Frank Neumann-Staude <frank@staude.net>
 *
 */

jQuery(document).ready(function(){
    jQuery("#mympooldeleteall").click( function() {
        var data = {
            'action': 'mympool_delete_all',
        };
        jQuery.post(ajaxurl, data, function(response) {
        });

        location.reload();
        return false;
    })

    jQuery("#mympoolimportall").click( function() {
        var data = {
            'action': 'mympool_import_all',
        };
        jQuery.post(ajaxurl, data, function(response) {
        });

        location.reload();
        return false;
    })
});