document.addEventListener('DOMContentLoaded', function() {
    // Add event listener for removing a group
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-this')) {
            if (!e.target.classList.contains('are-you-sure')) {
                // First click: change button text and add "are-you-sure" class
                e.target.textContent = 'Are you sure?';
                e.target.classList.add('are-you-sure');
            } else {
                // Second click: proceed with deletion
                const button = e.target;
                const groupName = button.getAttribute('data-group-name'); // Get the group name from the data attribute
                const fieldType = button.getAttribute('data-field-type'); // Get the field type from the data attribute

                // Determine the option name based on field type
                const optionName = fieldType === 'truefalse' ? 'truefalse_' + groupName : 'choices_' + groupName;

                console.log(optionName);

                // Send AJAX request to delete the group
                jQuery.ajax({
                    url: aceAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_wp_option', // Match the action name
                        option_name: optionName // Pass the correct option name
                    },
                    success: function(response) {
                        console.log('Response:', response); // Log the response for debugging
                        if (response.success) {
                            // Remove the group from the DOM
                            e.target.closest('tr').remove();
                            alert(response.data); // Display success message
                        } else {
                            alert(response.data); // Display error message
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error); // Log the error for debugging
                        alert('An error occurred. Please try again.');
                    }
                });
            }
        }
    });

    // Reset "are-you-sure" buttons if clicked elsewhere
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('remove-this')) {
            const buttons = document.querySelectorAll('.remove-this.are-you-sure');
            buttons.forEach(button => {
                button.textContent = 'Remove Group';
                button.classList.remove('are-you-sure');
            });
        }
    });
});
