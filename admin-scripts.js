jQuery(document).ready(function($) {
    // Test API connection
    $('#systemeio-test-connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $status = $('.status-indicator');
        var $message = $('.status-message');
        
        $button.prop('disabled', true);
        $status.removeClass('success error').addClass('testing');
        $message.text(systemeio_vars.testing);
        
        $.ajax({
            url: systemeio_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'systemeio_test_connection',
                nonce: systemeio_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.removeClass('testing').addClass('success');
                    $message.html('<strong>' + systemeio_vars.connected + '</strong><br>' + response.data.message);
                    
                    // Update tags dropdowns if available
                    if (response.data.tags) {
                        $('.systemeio-tags-select').each(function() {
                            var currentVal = $(this).val() || [];
                            $(this).empty();
                            
                            $.each(response.data.tags, function(i, tag) {
                                $(this).append($('<option>', {
                                    value: tag.id,
                                    text: tag.name,
                                    selected: currentVal.includes(tag.id)
                                }));
                            });
                        });
                    }
                } else {
                    $status.removeClass('testing').addClass('error');
                    $message.text(response.data || systemeio_vars.failed);
                }
            },
            error: function() {
                $status.removeClass('testing').addClass('error');
                $message.text(systemeio_vars.failed);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Clear logs via AJAX
    $('form[name="clear_logs_form"]').on('submit', function(e) {
        e.preventDefault();
        
        if (confirm('Are you sure you want to clear all logs?')) {
            $.ajax({
                url: systemeio_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'systemeio_clear_logs',
                    nonce: systemeio_vars.nonce
                },
                success: function() {
                    location.reload();
                }
            });
        }
    });
});