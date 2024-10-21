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
                const groupName = 'choices_' + button.getAttribute('data-group-name'); // Get the group name from the data attribute
                console.log(groupName);
                 // Send AJAX request to delete the group
        jQuery.ajax({
            url: aceAjax.ajaxurl,
            type: 'POST',
            data: {
            action: 'delete_wp_option', // Match the action name
            option_name: groupName // Pass the correct option name
            },
            success: function(response) {
            if (response.success) {
                // Remove the group from the DOM
                e.target.closest('tr').remove();
                alert(response.data); // Display success message
            } else {
                alert(response.data); // Display error message
            }
            },
            error: function() {
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
