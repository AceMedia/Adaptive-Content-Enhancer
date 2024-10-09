document.addEventListener('DOMContentLoaded', function() {
    // Add event listener for removing a group
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-group')) {
            if (!e.target.classList.contains('are-you-sure')) {
                // First click: change button text and add "are-you-sure" class
                e.target.textContent = 'Are you sure?';
                e.target.classList.add('are-you-sure');
            } else {
                // Second click: proceed with deletion
                const groupRow = e.target.closest('.dropdown-group-row');
                const groupNameInput = groupRow.querySelector('input[name^="dropdown_groups["][name]');
                const groupName = groupNameInput.value;

                // Send AJAX request to delete the group
                jQuery.ajax({
                    url: aceAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_dropdown_group',
                        group_name: groupName
                    },
                    success: function(response) {
                        if (response.success) {
                            // Remove the group row from the DOM
                            groupRow.remove();
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

    // Add event listener to reset "are-you-sure" buttons if clicked elsewhere
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('remove-group')) {
            const buttons = document.querySelectorAll('.remove-group.are-you-sure');
            buttons.forEach(button => {
                button.textContent = 'Remove';
                button.classList.remove('are-you-sure');
            });
        }
    });
});
