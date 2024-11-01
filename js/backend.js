jQuery(document).ready(function(){
    jQuery('.terms_of_service_link').on('click',function(){
        jQuery('.uns-project-terms-popup').show();
    });
    jQuery('.terms_of_service_close').on('click',function(){
        jQuery('.uns-project-terms-popup').hide();
    });

    jQuery('#save_attestation').click(function(){
        jQuery('#default_attestation').submit();
    });

    jQuery('input.single-checkbox').on('change', function() {
        if(jQuery('input.single-checkbox:checked').length >= 1) {
            jQuery('input.single-checkbox').prop('checked', false);
            this.checked = true;
        }
    });
})

function unsDisconnectUser(url, jwt) {
    jQuery(document).ready(function () {
        jQuery.ajax({
            url: url,
            method: 'POST',
            data: {
                jwt: jwt,
            },
            success: function (data) {
                window.location.href = window.location.href;
                return;
            },
            error: function (data) {
                console.log('There was an error while trying to disconnect your account.')
                console.log(data);
            }
        });
    });
}