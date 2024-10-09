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

// Register the admin menu page for managing Option Groups.
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

// Enqueue the settings CSS
function ace_field_options_enqueue_styles() {
    $screen = get_current_screen();
    
    // Check if we are on the settings page
    if ($screen->id === 'toplevel_page_ace-dropdown-groups') {
        wp_enqueue_style(
            'ace-field-options-settings', // Handle
            plugins_url('/assets/styles/css/settings.css', __FILE__), // Path to the CSS file
            array(), // Dependencies (if any)
            null // Version number
        );


        wp_enqueue_script(
            'ace-field-options-ajax',
            plugins_url('/assets/blocks/ajax.js', __FILE__),
            array('jquery'), // Dependencies
            null, // Version
            true // Load in footer
        );
    
        // Localize script to make the AJAX URL available
        wp_localize_script('ace-field-options-ajax', 'aceAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    

    }
}
add_action('admin_enqueue_scripts', 'ace_field_options_enqueue_styles');


// Enqueue the editor CSS
function ace_field_options_enqueue_editor_styles() {
    wp_enqueue_style(
        'ace-field-options-editor', // Handle
        plugins_url('/assets/styles/css/editor.css', __FILE__), // Path to the CSS file
        array(), // Dependencies (if any)
        null // Version number
    );
}
add_action('enqueue_block_editor_assets', 'ace_field_options_enqueue_editor_styles');


function create_underscored_value($string) {
    return strtolower(str_replace(' ', '_', $string));
}




// Admin page HTML to manage Option Groups.
function ace_field_options_groups_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Retrieve the list of group names from the database.
    $group_names = get_option('ace_custom_dropdown_groups', array());
    $dropdown_groups = array();

    $true_false_fields = get_option('ace_custom_true_false_fields', array());

    print_r($true_false_fields);


  // Check if we're editing a specific group or field
  $edit_key = isset($_GET['edit']) ? sanitize_text_field($_GET['edit']) : '';
  $field_type = ''; // New variable to determine the field type

  // Retrieve the list of group names from the database.
  $group_names = get_option('ace_custom_dropdown_groups', array());
  $dropdown_groups = array();

  if (!empty($group_names)) {
    foreach ($group_names as $group_name) {
        $option_name = 'ace_custom_group_' . create_underscored_value($group_name);
        $group_data = get_option($option_name, array());
        
        // Always load the group data
        $group_data['show_in_rest'] = isset($group_data['show_in_rest']) ? $group_data['show_in_rest'] : 0;
        $dropdown_groups[] = $group_data; // Add all groups to the dropdowns
    }
}

// Now, we can handle true/false fields regardless of Option Groups
$true_false_fields = get_option('ace_custom_true_false_fields', array());



// Handle form submission to save both Option Groups and true/false fields.
if (isset($_POST['save_groups'])) {
    check_admin_referer('save_dropdown_groups');

    // Retrieve the existing group names
    $existing_group_names = get_option('ace_custom_dropdown_groups', array());

    // Initialize array for new group names
    $new_group_names = array();

    // Process and sanitize group options data.
    if (isset($_POST['dropdown_groups']) && is_array($_POST['dropdown_groups'])) {
        foreach ($_POST['dropdown_groups'] as $group) {
            if (isset($group['name'], $group['options']) && is_array($group['options'])) {
                // Create the sanitized name for the group
                $sanitized_name = sanitize_text_field($group['name']);
                $option_name = 'ace_custom_group_' . create_underscored_value($sanitized_name);

                // Prepare the group data to be saved
                $group_data = array(
                    'name' => $sanitized_name,
                    'options' => array_map(function($option) {
                        $value = !empty($option['value']) ? create_underscored_value($option['value']) : sanitize_text_field($option['label']);
                        return array(
                            'label' => sanitize_text_field($option['label']),
                            'value' => $value,
                        );
                    }, $group['options']),
                    // Save show_in_rest for the group
                    'show_in_rest' => isset($group['show_in_rest']) ? 1 : 0,
                );

                // Check if this group already exists
                if (in_array($sanitized_name, $existing_group_names)) {
                    // Update the existing group
                    update_option($option_name, $group_data);
                } else {
                    // Add the new group
                    update_option($option_name, $group_data);
                    $new_group_names[] = $sanitized_name; // Collect new group names
                }
            }
        }
    }

    // Save the updated list of group names
    update_option('ace_custom_dropdown_groups', array_unique(array_merge($existing_group_names, $new_group_names)));

    // Retrieve all Option Groups again to display the newly added ones
    $dropdown_groups = array();
    $group_names = get_option('ace_custom_dropdown_groups', array());

    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = 'ace_custom_group_' . create_underscored_value($group_name);
            $group_data = get_option($option_name, array());

            // If editing a specific group, only include that one; otherwise, include all
            if (empty($edit_key) || create_underscored_value($group_data['name']) === $edit_key) {
                if (!empty($group_data)) {
                    $dropdown_groups[] = $group_data; // Add the retrieved group data
                }
            }
        }
    }

    // Process and sanitize true/false fields data.
if (isset($_POST['true_false_fields']) && is_array($_POST['true_false_fields'])) {
    // Load current true/false fields from the database
    $current_true_false_fields = get_option('ace_custom_true_false_fields', array());

    // Initialize an array to hold updated true/false fields
    $updated_true_false_fields = $current_true_false_fields; // Start with the current fields

    foreach ($_POST['true_false_fields'] as $field_index => $field) {
        if (isset($field['name'], $field['true_label'], $field['false_label'])) {
            // Create a unique option name for each field
            $field_name = create_underscored_value($field['name']);
            $data = array(
                'true_label' => sanitize_text_field($field['true_label']),
                'false_label' => sanitize_text_field($field['false_label']),
                'show_in_rest' => isset($field['show_in_rest']) ? 1 : 0,
            );

            // Check if this field is already in the updated array
            $field_exists = false;

            foreach ($updated_true_false_fields as $key => $existing_field) {
                if (create_underscored_value($existing_field['name']) === $field_name) {
                    // Update the existing field
                    $updated_true_false_fields[$key] = array_merge($existing_field, $data);
                    $field_exists = true;
                    break;
                }
            }

            // If it doesn't exist, it's a new field
            if (!$field_exists) {
                $updated_true_false_fields[] = array_merge(['name' => $field['name']], $data);
            }

            // Save or update each field as its own option
            update_option("ace_custom_true_false_field_{$field_name}", $data);
        }
    }

    // Save the updated true/false fields in a single option for easy retrieval
    update_option('ace_custom_true_false_fields', $updated_true_false_fields);
}


    // Load all true/false fields into an array for display
    $true_false_fields = []; // Initialize empty array
    foreach ($_POST['true_false_fields'] as $field) {
        if (isset($field['name'])) {
            $field_name = create_underscored_value($field['name']);
            $true_false_fields[] = [
                'name' => $field['name'],
                'true_label' => get_option("ace_custom_true_false_field_{$field_name}")['true_label'] ?? '',
                'false_label' => get_option("ace_custom_true_false_field_{$field_name}")['false_label'] ?? '',
                'show_in_rest' => get_option("ace_custom_true_false_field_{$field_name}")['show_in_rest'] ?? 0, // Load the checkbox state
            ];
        }
    }
    
    echo '<div class="updated"><p>Settings saved.</p></div>';
}




    ?>
    <div class="wrap">

    <h1>Manage Option Groups and True/False Fields</h1>
    <form method="POST">
        <?php wp_nonce_field('save_dropdown_groups'); 
        print_R($field_type); 


                        include 'templates/settings-options.php';
 
                        ?>
        <p><button type="button" class="button add-group">Add New Group</button></p>

       <!-- Adding True/False Fields Section -->
    <?php if (!empty($true_false_fields)) : ?>
        <?php foreach ($true_false_fields as $field_index => $field) :
            include_once 'templates/settings-truefalse.php';
         endforeach; ?>
    <?php endif; ?>
<?php submit_button('Save Settings', 'primary', 'save_groups'); ?>

    </form>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add new dropdown group (same as before)
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

        document.querySelector('.add-field').addEventListener('click', function() {
    const tableBody = document.querySelector('#truefalse');
    const fieldIndex = tableBody.children.length;
    const newRow = document.createElement('tr');
    newRow.classList.add('level-0');
    newRow.id = `field-${fieldIndex}`;
    newRow.innerHTML = `
        <td class="name column-name has-row-actions column-primary">
            <input type="text" name="true_false_fields[${fieldIndex}][name]" value="" placeholder="Field Name" />
        </td>
        <td class="true-label column-true-label">
            <input type="text" name="true_false_fields[${fieldIndex}][true_label]" value="" placeholder="Label for True" />
        </td>
        <td class="false-label column-false-label">
            <input type="text" name="true_false_fields[${fieldIndex}][false_label]" value="" placeholder="Label for False" />
        </td>
        <td class="action column-action">
            <button type="button" class="button remove-field">Remove</button>
        </td>
    `;

    tableBody.appendChild(newRow);

    // Add event listener for the newly created 'Remove' button
    newRow.querySelector('.remove-field').addEventListener('click', function() {
        newRow.remove();
    });
});


    // Remove true/false field
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-field')) {
            e.target.closest('tr').remove();
        }
    });

        // Remove option
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-option')) {
                e.target.closest('tr').remove();
            }
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

            });
    });
</script>

    <?php
}

// Enqueue editor assets (admin side) and styles
function ace_field_options_enqueue_editor_assets() {

    // Editor (backend) script for dropdown block (index.js)
    wp_enqueue_script(
        'ace-field-options-block-editor',
        plugins_url( '/assets/options.js', __FILE__ ),
        array( 'wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data' ),
        filemtime( plugin_dir_path( __FILE__ ) . 'assets/index.js' )
    );

    // Editor (backend) script for true/false block (truefalse.js)
    wp_enqueue_script(
        'ace-field-options-truefalse-editor',
        plugins_url( '/assets/truefalse.js', __FILE__ ),
        array( 'wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data' ),
        filemtime( plugin_dir_path( __FILE__ ) . 'assets/truefalse.js' )
    );

    // Pass Option Groups to editor
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

    // Localize Option Groups and true/false fields
    wp_localize_script('ace-field-options-block-editor', 'dropdownGroups', $dropdown_groups);

    // Fetch true/false fields
    $true_false_fields = get_option('ace_custom_true_false_fields', array());
    wp_localize_script('ace-field-options-truefalse-editor', 'trueFalseFields', $true_false_fields);
}
add_action('enqueue_block_editor_assets', 'ace_field_options_enqueue_editor_assets');


// Register post meta fields dynamically based on Option Groups
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
    if (is_array($meta_value) || is_object($meta_value)) {
        // Convert arrays/objects to JSON string
        $meta_value = wp_json_encode($meta_value);
    }

    return sanitize_text_field((string) $meta_value);
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

    // Fetch Option Groups from the database
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



// Render function for the true/false block
function ace_field_options_render_true_false_block($attributes) {
    $post_id = get_the_ID();
    $selected_field = $attributes['selectedField'] ?? ''; // Field name to fetch
    $true_false_value = get_post_meta($post_id, create_underscored_value($selected_field), true); // Get stored value for the true/false field

    // Fetch true/false fields from the database
    $true_false_fields = get_option('ace_custom_true_false_fields', array());
    $label = '';

    // Find the corresponding true/false field
    foreach ($true_false_fields as $field) {
        if ($field['name'] === $selected_field) {
            // Determine the label based on the stored value
            $label = ($true_false_value === '1') ? $field['true_label'] : $field['false_label'];
            break; // Exit loop once found
        }
    }


    // Output the block with the selected label
    return sprintf(
        '<p class="wp-block-ace-field-options-true-false-block wp-block-paragraph">%s</p>',
        esc_html($label) // Display the true/false label
    );
}

// Register the true/false block with its own render callback
register_block_type('ace-field-options/true-false-block', array(
    'render_callback' => 'ace_field_options_render_true_false_block',
    'attributes' => array(
        'selectedField' => array(
            'type' => 'string',
            'default' => ''
        ),
        // Other attributes as needed
    )
));


add_action('rest_api_init', function() {
    register_rest_route('ace/v1', '/meta/(?P<id>\d+)(/(?P<field>[a-zA-Z_]+))?', array(
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

    // Get all Option Groups from the settings
    $group_names = get_option('ace_custom_dropdown_groups', array());
    $dropdown_groups = array();
    
    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = 'ace_custom_group_' . create_underscored_value($group_name);
            $group_data = get_option($option_name, array());

            // Check if the group should be shown in REST
            if (empty($group_data['show_in_rest'])) { // Treat empty or missing as false
                continue; // Skip this group if it shouldn't be shown
            }

            $meta_key = create_underscored_value($group_name); // Get the key for the field
            
            // Match the stored value for Option Groups
            if (isset($meta[$meta_key])) {
                $stored_value = maybe_unserialize($meta[$meta_key][0]); // Unserialize if needed
                
                foreach ($group_data['options'] as $option) {
                    if ($stored_value === $option['value']) { // Compare the stored value with option value
                        // Store the label and value in the filtered meta
                        $filtered_meta[$meta_key] = array(
                            'label' => $option['label'],
                            'value' => $option['value']
                        );
                        break; // Exit loop once a match is found
                    }
                }
            }
        }
    }

    // Handle true/false fields
    $true_false_fields = get_option('ace_custom_true_false_fields', array());

    foreach ($true_false_fields as $field) {
        $meta_key = create_underscored_value($field['name']); // Get the key for the field

        if (isset($meta[$meta_key])) { // Check if the meta exists
            // Check if this field should be shown in REST API
            if (empty($field['show_in_rest'])) { // Treat empty or missing as false
                continue; // Skip this field if it shouldn't be shown
            }

            $meta_value = $meta[$meta_key]; // Get the meta value
            $label = ($meta_value[0] == 1) ? $field['true_label'] : $field['false_label'];

            $filtered_meta[$meta_key] = array(
                'label' => $label,
                'value' => $meta_value // Ensure this is returned correctly
            );
        }
    }

    // Check if a specific field is requested
    if (isset($data['field'])) {
        $field_key = create_underscored_value($data['field']);
        if (array_key_exists($field_key, $filtered_meta)) {
            return new WP_REST_Response($filtered_meta[$field_key]); // Return the specific field data
        } else {
            return new WP_Error('rest_field_invalid', __('Invalid field specified.'), array('status' => 404));
        }
    }

    return new WP_REST_Response($filtered_meta); // Return the filtered meta array with label and value
}





// Hook into the rest_prepare_post filter to modify the response for posts
add_filter('rest_prepare_post', 'modify_post_meta_for_rest', 10, 3);

function modify_post_meta_for_rest($response, $post, $request) {
    // Check if the request is for the frontend (view context)
    if ($request->get_param('context') !== 'view') {
        return $response; // Return unmodified response for non-view contexts
    }

    $meta = $response->data['meta'];

    // Get all Option Groups from the settings
    $group_names = get_option('ace_custom_dropdown_groups', array());
    $dropdown_groups = array();

    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = 'ace_custom_group_' . create_underscored_value($group_name);
            $group_data = get_option($option_name, array());
            $meta_key = create_underscored_value($group_name); // Get the key for the field
    
            // Check if the group should be shown in REST
            if (empty($group_data['show_in_rest'])) { // Treat empty or missing as false
                unset($response->data['meta'][$meta_key]); // Remove if not allowed in REST
            } else {
                // Initialize variables for the label and value


                $label = '';
                $value = '';
    
                // Match the stored value for Option Groups
                if (isset($meta[$meta_key])) {
                    $stored_value = $meta[$meta_key]; // Get the stored value from the meta
                    
                    foreach ($group_data['options'] as $option) {
                        if ($stored_value === $option['value']) { // Compare the stored value with option value
                            // Store the label and value in the filtered meta
                            $label = $option['label'];
                            $value = $option['value'];
                         //   print_r($value);

                            break; // Exit loop once a match is found
                        }
                    }
                }
    
                // Only add to the response if a matching option was found
                if ($label !== '' && $value !== '') {
                    // Structure the response correctly
                    $response->data['meta'][$meta_key] = array(
                        'label' => $label,
                        'value' => $value // Return the matched label and value
                    );
                }
            }
        }
    }
    


    // Handle true/false fields
    $true_false_fields = get_option('ace_custom_true_false_fields', array());

    foreach ($true_false_fields as $field) {
        $meta_key = create_underscored_value($field['name']); // Get the key for the field

        if (isset($meta[$meta_key])) { // Check if the meta exists
            // Check if this field should be shown in REST API
            if (empty($field['show_in_rest'])) { // Treat empty or missing as false
                unset($response->data['meta'][$meta_key]); // Optionally remove the field from response if show_in_rest is false
            } else {
                $meta_value = $meta[$meta_key];
                // Adjust the check based on how the meta_value is stored
                $label = ($meta_value === '1' || $meta_value === true) ? $field['true_label'] : $field['false_label'];
                $response->data['meta'][$meta_key] = array(
                    'label' => $label,
                    'value' => $meta_value // Return the meta value
                );
            }
        }
    }


    return new WP_REST_Response($response);  // Return the filtered meta array with label and value

}







add_action('init', 'acefields_register_true_false_meta');

function acefields_register_true_false_meta() {
    $true_false_fields = get_option('ace_custom_true_false_fields', array());

    if (!empty($true_false_fields)) {
        foreach ($true_false_fields as $field) {
            // Create the sanitized meta key from the field name
            $field_key = create_underscored_value($field['name']);

            // Register meta field for each true/false field
            register_post_meta(
                'post',
                $field_key,
                array(
                    'show_in_rest'      => true,
                    'single'            => true,
                    'type'              => 'boolean', // This field should be a boolean (true/false)
                    'sanitize_callback' => 'rest_sanitize_boolean', // Use the default WordPress boolean sanitizer
                )
            );
        }
    }
}


// Handle the AJAX request to delete any WP option
add_action('wp_ajax_delete_wp_option', 'ace_delete_wp_option');

function ace_delete_wp_option() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }

    // Check if the option name is set
    if (isset($_POST['group_name'])) {
        $group_name = sanitize_text_field($_POST['group_name']);
        $option_name = 'ace_custom_group_' . create_underscored_value($group_name); // Use 'group_name' from the request

        // Delete the option
        if (delete_option($option_name)) {
            // Remove the group name from the list of group names
            $group_names = get_option('ace_custom_dropdown_groups', array());
            if (($key = array_search($group_name, $group_names)) !== false) {
                unset($group_names[$key]);
                update_option('ace_custom_dropdown_groups', array_values($group_names)); // Reindex the array
            }
            wp_send_json_success('Option deleted successfully');
        } else {
            wp_send_json_error('Option not found');
        }
    } else {
        wp_send_json_error('Invalid option name');
    }
}



?>
