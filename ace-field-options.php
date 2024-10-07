<?php
/**
 * Plugin Name: Ace Field Options
 * Description: A WordPress block plugin with multiple option groups for dropdowns.
 * Version: 0.420
 * Author: Shane Rounce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register the admin menu page for managing dropdown groups.
add_action( 'admin_menu', 'ace_field_options_admin_menu' );

function ace_field_options_admin_menu() {
    add_menu_page(
        'Options',
        'Options',
        'manage_options',
        'ace-dropdown-groups',
        'ace_field_options_groups_page_html',
        'dashicons-list-view',
        20
    );
}

function create_underscored_value($string) {
    return strtolower(str_replace(' ', '_', $string));
}

// Admin page HTML to manage dropdown groups.
function ace_field_options_groups_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Retrieve the list of group names from the database.
    $group_names = get_option('ace_custom_dropdown_groups', array());
    $dropdown_groups = array();

    // Load each group's options dynamically from its own wp_option.
    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = 'ace_custom_group_' . create_underscored_value($group_name);
            $group_data = get_option($option_name, array());
            if (!empty($group_data)) {
                $dropdown_groups[] = $group_data;
            }
        }
    }

// Handle form submission to save groups and options.
// Handle form submission to save groups and options.
if (isset($_POST['save_groups'])) {
    check_admin_referer('save_dropdown_groups');

    // Process and sanitize the form data.
    if (isset($_POST['dropdown_groups']) && is_array($_POST['dropdown_groups'])) {
        $dropdown_groups = array_map(function($group) {
            if (isset($group['name'], $group['options']) && is_array($group['options'])) {
                return array(
                    'name' => sanitize_text_field($group['name']),
                    'options' => array_map(function($option) {
                        $label = sanitize_text_field($option['label']);
                        $value = sanitize_text_field($option['value']);

                        if (!empty($option['value'])) {
                            $value = sanitize_text_field($option['value']); // Use the existing value if not empty
                        } else {
                            // Define a new value if it's empty
                            $value = !empty($label) ? create_underscored_value($label) : '';
                        }

                        return array(
                            'label' => $label,
                            'value' => $value ,
                        );
                    }, $group['options']),
                );
            }
            return null;
        }, $_POST['dropdown_groups']);

        // Remove null (invalid) groups.
        $dropdown_groups = array_filter($dropdown_groups);
    } else {
        $dropdown_groups = array(); // Reset to empty array if no groups are provided.
    }

    // Delete old options before saving updated ones.
    $group_names = get_option('ace_custom_dropdown_groups', array());
    foreach ($group_names as $group_name) {
        $option_name = 'ace_custom_group_' . create_underscored_value($group_name);
        delete_option($option_name); // Clean old options.
    }

    // Save each group as its own wp_option with the new structure.
    $new_group_names = array();
    if (!empty($dropdown_groups)) {
        foreach ($dropdown_groups as $group) {
            $option_name = 'ace_custom_group_' . create_underscored_value($group['name']);
            update_option($option_name, $group); // Save each group individually.
            $new_group_names[] = $group['name'];
        }
    }

    // Save the list of group names to a central option for easy retrieval later.
    update_option('ace_custom_dropdown_groups', $new_group_names);

    echo '<div class="updated"><p>Dropdown groups saved.</p></div>';
}





    ?>
    <div class="wrap">
    <h1>Manage Dropdown Groups</h1>
    <form method="POST">
        <?php wp_nonce_field('save_dropdown_groups'); ?>
        <table class="form-table" id="dropdown-groups-table">
            <thead>
                <tr>
                    <th>Group Name</th>
                    <th>Options</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dropdown_groups)) : ?>
                    <?php foreach ($dropdown_groups as $group_index => $group) : ?>
                        <?php $meta_key = create_underscored_value($group['name']); ?>
                        <tr class="dropdown-group-row">
                            <td>
                                <input type="text" name="dropdown_groups[<?php echo $group_index; ?>][name]" value="<?php echo esc_attr($group['name']); ?>" />
                                <p><strong>Meta Key:</strong> <?php echo esc_html($meta_key); ?></p>
                            </td>
                            <td>
                                <table class="group-options-table">
                                    <thead>
                                        <tr>
                                            <th>Option Label</th>
                                            <th>Option Value</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($group['options'])) : ?>
                                            <?php foreach ($group['options'] as $option_index => $option) : ?>
                                                <tr>
                                                    <td><input type="text" name="dropdown_groups[<?php echo $group_index; ?>][options][<?php echo $option_index; ?>][label]" value="<?php echo esc_attr($option['label']); ?>" /></td>
                                                    <td>
    <input 
        type="text" 
        name="dropdown_groups[<?php echo $group_index; ?>][options][<?php echo $option_index; ?>][value]" 
        value="<?php echo esc_attr($option['value']); ?>" 
        <?php echo !empty($option['value']) ? 'readonly' : ''; ?> 
    />
</td>
                                                    <td><button type="button" class="button remove-option">Remove</button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <button type="button" class="button add-option">Add Option</button>
                            </td>
                            <td><button type="button" class="button remove-group">Remove Group</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p><button type="button" class="button add-group">Add New Group</button></p>
        <?php submit_button('Save Groups', 'primary', 'save_groups'); ?>
    </form>
</div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add new group
            document.querySelector('.add-group').addEventListener('click', function() {
                const tableBody = document.querySelector('#dropdown-groups-table tbody');
                const groupIndex = tableBody.children.length;
                const newRow = document.createElement('tr');
                newRow.classList.add('dropdown-group-row');
                newRow.innerHTML = `
                    <td><input type="text" name="dropdown_groups[${groupIndex}][name]" value="" /></td>
                    <td>
                        <table class="group-options-table">
                            <thead>
                                <tr>
                                    <th>Option Label</th>
                                    <th>Option Value</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <button type="button" class="button add-option">Add Option</button>
                    </td>
                    <td><button type="button" class="button remove-group">Remove Group</button></td>
                `;
                tableBody.appendChild(newRow);
            });

            // Add new option to a group
            document.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('add-option')) {
                    const groupRow = e.target.closest('.dropdown-group-row');
                    const groupIndex = [...groupRow.parentElement.children].indexOf(groupRow);
                    const optionsTable = groupRow.querySelector('.group-options-table tbody');
                    const optionIndex = optionsTable.children.length;
                    const newRow = document.createElement('tr');
                    newRow.innerHTML = `
                        <td><input type="text" name="dropdown_groups[${groupIndex}][options][${optionIndex}][label]" value="" /></td>
                        <td><input type="text" name="dropdown_groups[${groupIndex}][options][${optionIndex}][value]" value="" /></td>
                        <td><button type="button" class="button remove-option">Remove</button></td>
                    `;
                    optionsTable.appendChild(newRow);
                }

                // Remove option
                if (e.target && e.target.classList.contains('remove-option')) {
                    e.target.closest('tr').remove();
                }

                // Remove group
                if (e.target && e.target.classList.contains('remove-group')) {
                    e.target.closest('tr').remove();
                }
            });
        });
    </script>
    <?php
}

// Enqueue editor assets (admin side) and styles
function ace_field_options_enqueue_editor_assets() {
    // Editor (backend) script
    wp_enqueue_script(
        'ace-field-options-block-editor',
        plugins_url( '/build/index.js', __FILE__ ),
        array( 'wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data' ),
        filemtime( plugin_dir_path( __FILE__ ) . 'build/index.js' )
    );


    // Pass dropdown groups to editor
    $group_names = get_option('ace_custom_dropdown_groups', array());
    $dropdown_groups = array();

    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = 'ace_custom_group_' . create_underscored_value($group_name);
            $group_data = get_option($option_name, array());
            if (!empty($group_data)) {
                $dropdown_groups[] = $group_data;
            }
        }
    }

    wp_localize_script('ace-field-options-block-editor', 'dropdownGroups', $dropdown_groups);
}
add_action('enqueue_block_editor_assets', 'ace_field_options_enqueue_editor_assets');

// Register post meta fields dynamically based on dropdown groups
add_action('init', 'acefields_register_dynamic_meta');

function acefields_register_dynamic_meta() {
    $group_names = get_option('ace_custom_dropdown_groups', array());

    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            // Create the sanitized meta key from the group name
            $group_name_key = create_underscored_value($group_name);

            // Register meta field for each valid group
            register_post_meta(
                'post',
                $group_name_key,
                array(
                    'show_in_rest'      => true,
                    'single'            => true,
                    'type'              => 'string', // Type should be string, even though we will store serialized arrays
                    'sanitize_callback' => 'acefields_sanitize_serialized_meta', // Custom sanitize function
                )
            );
        }
    }
}

function acefields_sanitize_serialized_meta($meta_value) {
    // Ensure that the meta value is stored as a string, without serialization
    if (!is_string($meta_value)) {
        return sanitize_text_field($meta_value); // Ensure it's a plain string
    }

    return $meta_value; // No extra processing
}


// Function to retrieve unserialized meta
function acefields_get_unserialized_meta($meta_value) {
    return maybe_unserialize($meta_value);
}


// Server-side rendering of the block (this ensures block data is rendered correctly)
function ace_field_options_render_block($attributes) {
    $post_id = get_the_ID();
    $selected_group = $attributes['selectedGroup'] ?? '';
    $dropdown_value_ref = $attributes['dropdownValue'] ?? ''; // Store the saved reference string

    // Fetch dropdown groups from the database
    $dropdown_groups = array();
    $group_names = get_option('ace_custom_dropdown_groups', array());

    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = 'ace_custom_group_' . create_underscored_value($group_name);
            $group_data = get_option($option_name, array());
            if (!empty($group_data)) {
                $dropdown_groups[] = $group_data;
            }
        }
    }

    // Initialize the variables to store the label and value
    $dropdown_label = '';
    $dropdown_value = '';

    // Check the reference string to match it with dropdown group and options
    if ($selected_group && $dropdown_value_ref) {
        foreach ($dropdown_groups as $group) {
            // Make sure the group matches
            if ($group['name'] === $selected_group) {
                foreach ($group['options'] as $option) {
                    // Check if the reference string contains the option value
                    if (strpos($dropdown_value_ref, $option['value']) !== false) {
                        $dropdown_label = $option['label'];
                        $dropdown_value = $option['value'];
                        break 2; // Exit both loops once a match is found
                    }
                }
            }
        }
    }

    // Output the block with the selected label
    return sprintf(
        '<p class="wp-block-ace-field-options-dropdown-block wp-block-paragraph">%s</p>',
        esc_html($dropdown_label) // Display the dynamically retrieved label
    );
}



register_block_type('ace-field-options/dropdown-block', array(
    'render_callback' => 'ace_field_options_render_block',
    'attributes' => array(
        'selectedGroup' => array(
            'type' => 'string',
            'default' => ''
        ),
        'dropdownValue' => array(
            'type' => 'string',
            'default' => ''
        ),
        'dropdownLabel' => array( // Add dropdownLabel to attributes
            'type' => 'string',
            'default' => ''
        )
    )
));

add_action('rest_api_init', function() {
    register_rest_route('ace/v1', '/meta/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_post_meta_fields',
        'permission_callback' => '__return_true',
    ));
});

function get_post_meta_fields($data) {
    $post_id = $data['id'];
    $post = get_post($post_id);

    if (!$post) {
        return new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), array('status' => 404));
    }

    // Get all post meta
    $meta = get_post_meta($post_id);
    
    // Initialize an empty array for filtered meta
    $filtered_meta = array();

    // Get all dropdown groups from the settings
    $group_names = get_option('ace_custom_dropdown_groups', array());
    $dropdown_groups = array();
    
    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = 'ace_custom_group_' . create_underscored_value($group_name);
            $group_data = get_option($option_name, array());
            if (!empty($group_data)) {
                $dropdown_groups[] = $group_data;
            }
        }
    }

    // Loop through post meta and process each field
    foreach ($meta as $meta_key => $meta_value) {
        // Only include keys that don't start with '_'
        if (strpos($meta_key, '_') !== 0) {
            $stored_value = maybe_unserialize($meta_value[0]); // Unserialize if needed

            // Match the stored value (e.g., "ace_custom_group_spot_condition_dirty")
            foreach ($dropdown_groups as $group) {
                foreach ($group['options'] as $option) {
                    // Match the stored value by checking if it contains the option value
                    if (strpos($stored_value, $option['value']) !== false) {
                        // Store the label and value in the filtered meta
                        $filtered_meta[$meta_key] = array(
                            'label' => $option['label'],
                            'value' => $option['value']
                        );
                        break 2; // Exit both loops once a match is found
                    }
                }
            }
        }
    }

    return $filtered_meta; // Return the filtered meta array with label and value
}


// Hook into the rest_prepare_post filter to modify the response for posts
add_filter('rest_prepare_post', 'modify_post_meta_for_rest', 10, 3);

function modify_post_meta_for_rest($response, $post, $request) {
    $meta = $response->data['meta'];

    // Get all dropdown groups from the settings
    $group_names = get_option('ace_custom_dropdown_groups', array());
    $dropdown_groups = array();

    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = 'ace_custom_group_' . create_underscored_value($group_name);
            $group_data = get_option($option_name, array());
            if (!empty($group_data)) {
                $dropdown_groups[] = $group_data;
            }
        }
    }

    // Modify the meta fields to include the dynamic labels
    foreach ($meta as $meta_key => $meta_value) {
        if (strpos($meta_key, '_') !== 0) {
            // Extract the group and option value from the meta key
            foreach ($dropdown_groups as $group) {
                foreach ($group['options'] as $option) {
                    // Check if the stored value matches the format: ace_custom_group_groupname_optionvalue
                    if (strpos($meta_value, $option['value']) !== false) {
                        // Replace the meta field with the correct label and value
                        $response->data['meta'][$meta_key] = array(
                            'label' => $option['label'],
                            'value' => $option['value']
                        );
                        break 2; // Exit both loops once a match is found
                    }
                }
            }
        }
    }

    return $response;
}




?>
