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

// Load all options and filter based on custom logic
$all_options = wp_load_alloptions();

// Fetch true/false fields after saving dynamically from updated options
$true_false_fields = apply_filters('filter_wp_options_by_field_type', null, 'truefalse');

// If you need to load fresh options, do it within the filter function
add_filter('filter_wp_options_by_field_type', 'filter_options_by_field_type', 10, 2);
function filter_options_by_field_type($all_options, $type) {
    if ($all_options === null) {
        // Load fresh options if $all_options is null
        $all_options = wp_load_alloptions();
    }

    $filtered_options = array();

    foreach ($all_options as $option_name => $value) {
        if (strpos($option_name, 'ace_custom_') === 0) {
            $field_data = get_option($option_name, array());

            if (!empty($field_data) && isset($field_data['field_type']) && $field_data['field_type'] === $type) {
                $filtered_options[] = $field_data;
            }
        }
    }

    return $filtered_options;
}


// Fetch dropdown groups after saving dynamically from updated options
$dropdown_groups = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');



// Admin page HTML to manage Option Groups.
function ace_field_options_groups_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }


  // Check if we're editing a specific group or field
  $edit_key = isset($_GET['edit']) ? sanitize_text_field($_GET['edit']) : '';
  $field_type = ''; // New variable to determine the field type

// Retrieve all Option Groups again to display the newly added ones
$dropdown_groups = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');




// Loop through all true/false fields stored individually
$true_false_fields = apply_filters('filter_wp_options_by_field_type', $all_options, 'truefalse');
print_r($true_false_fields, true);

foreach ($all_options as $option_name => $value) {
    if (strpos($option_name, 'ace_custom_') === 0) {
        // Retrieve only the true/false fields
        $field_data = get_option($option_name, array());

        if (!empty($field_data)) {
            $true_false_fields[] = $field_data;
        }
    }
}


// Handle saving/updating a specific group in single edit mode.
if (isset($_GET['edit'])) {
    $edit_group_name = sanitize_text_field($_GET['edit']);
    $edit_option_name = 'ace_custom_' . create_underscored_value($edit_group_name);

    // Process form data for the edited group.
    if (isset($_POST['group']) && is_array($_POST['group'])) {

        $group = $_POST['group'];

        // Ensure both name and options are present
        if (isset($group['name'], $group['options']) && is_array($group['options'])) {
            $group_data = array(
                'name' => sanitize_text_field($group['name']),
                'options' => array_map(function ($option) {
                    $value = !empty($option['value']) ? create_underscored_value($option['value']) : sanitize_text_field($option['label']);
                    return array(
                        'label' => sanitize_text_field($option['label']),
                        'value' => $value,
                    );
                }, $group['options']),
                'field_type' => 'dropdown',
                'show_in_rest' => isset($group['show_in_rest']) ? 1 : 0,
            );

            // Save or update the group data with the new option(s)
            $updated = update_option($edit_option_name, $group_data);

            if ($updated) {
                echo '<div class="updated"><p>Group updated successfully.</p></div>';
                
                // Reload the updated group data to ensure it's correctly repopulated in the form
                $group_data = get_option($edit_option_name, array());
            } else {
                echo '<div class="error"><p>Failed to update the group. Please try again.</p></div>';
            }
        }
    }
}


// Handle form submission to save both Option Groups and true/false fields.
if (isset($_POST['save_groups'])) {
    check_admin_referer('save_dropdown_groups');

    // Retrieve the existing group names
    $existing_group_names = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');

    // Initialize array for new group names
    $new_group_names = array();
    

// Save or update group names
if (isset($_POST['dropdown_groups']) && is_array($_POST['dropdown_groups'])) {
    // Retrieve the existing group names
    $existing_group_names = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');

    foreach ($_POST['dropdown_groups'] as $group) {
        if (isset($group['name'], $group['options']) && is_array($group['options'])) {
            // Create the sanitized name for the group
            $sanitized_name = sanitize_text_field($group['name']);
            $option_name = 'ace_custom_' . create_underscored_value($sanitized_name);

            // Prepare the group data to be saved
            $group_data = array(
                'name' => $sanitized_name,
                'options' => array_map(function ($option) {
                    $value = !empty($option['value']) ? create_underscored_value($option['value']) : sanitize_text_field($option['label']);
                    return array(
                        'label' => sanitize_text_field($option['label']),
                        'value' => $value,
                    );
                }, $group['options']),
                'field_type' => 'dropdown',
                'show_in_rest' => isset($group['show_in_rest']) ? 1 : 0,
            );

            // Save or update the group as its own option
            update_option($option_name, $group_data);

            // Add the group name to the existing group list if it's not already there
            if (!in_array($sanitized_name, $existing_group_names)) {
                $existing_group_names[] = $sanitized_name;
            }
        }
    }

    // Update the list of group names in the database to reflect any new groups
}



$dropdown_groups = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');

    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = 'ace_custom_' . create_underscored_value($group_name);
            $group_data = get_option($option_name, array());

            // If editing a specific group, only include that one; otherwise, include all
            if (empty($edit_key) || create_underscored_value($group_data['name']) === $edit_key) {
                if (!empty($group_data)) {
                    $dropdown_groups[] = $group_data; // Add the retrieved group data
                }
            }
        }
    }

 // Reset the true_false_fields array to avoid duplication
 $true_false_fields = array(); // Reset the array before repopulating

 // Handle saving true/false fields
 if (isset($_POST['true_false_fields']) && is_array($_POST['true_false_fields'])) {
     foreach ($_POST['true_false_fields'] as $field) {
         // Ensure the field has a valid name, true label, and false label before proceeding
         if (!empty($field['name']) && !empty($field['true_label']) && !empty($field['false_label'])) {
             $field_name = create_underscored_value($field['name']);
     
             // Proceed with saving the true/false field
             $data = array(
                 'name' => sanitize_text_field($field['name']),
                 'true_label' => sanitize_text_field($field['true_label']),
                 'false_label' => sanitize_text_field($field['false_label']),
                 'field_type' => 'truefalse',
                 'show_in_rest' => isset($field['show_in_rest']) ? 1 : 0,
             );
             
             // Save or update the option
             update_option("ace_custom_{$field_name}", $data);
         }
     }
 }

 // Repopulate true_false_fields after saving
 $true_false_fields = apply_filters('filter_wp_options_by_field_type', null, 'truefalse');
 
 // Filter fields to ensure only valid ones are shown
 $true_false_fields = array_filter($true_false_fields, function($field) {
     return !empty($field['name']) && !empty($field['true_label']) && !empty($field['false_label']);
 });

    
    echo '<div class="updated"><p>Settings saved.</p></div>';
}




    ?>
    <div class="wrap">

    <h1>Manage Option Groups and True/False Fields</h1>
    <form method="POST">
        <?php wp_nonce_field('save_dropdown_groups'); 


                        include 'templates/settings-options.php';
 
                        ?>
        <p><button type="button" class="button add-group">Add New Group</button></p>

       <!-- Adding True/False Fields Section -->
        <?php 
            include_once 'templates/settings-truefalse.php';
         ?>
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
            <input type="text" name="true_false_fields[${fieldIndex}][true_label]" value="True" placeholder="Label for True" />
        </td>
        <td class="false-label column-false-label">
            <input type="text" name="true_false_fields[${fieldIndex}][false_label]" value="False" placeholder="Label for False" />
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


        document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('add-option')) {
            // Check if we're in single edit view or main view
            const groupRow = e.target.closest('.dropdown-group-row');
            let groupIndex, optionsTable;

            // Single Edit View: No groupRow, so target the single group's table directly
            if (!groupRow) {
                optionsTable = document.querySelector('.group-options-table tbody'); // Single group view
                groupIndex = null; // No group index needed for single view
            } else {
                // Main View: Handle multiple groups by calculating groupIndex
                groupIndex = [...groupRow.parentElement.children].indexOf(groupRow);
                optionsTable = groupRow.querySelector('.group-options-table tbody'); // Target the options table in the current group row
            }

            // Get the current number of options to append the new one
            const optionIndex = optionsTable.children.length;
            const newRow = document.createElement('tr');
            
            // Determine if we are in single edit view based on the URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const editKey = urlParams.has('edit');

            // Handle the input name differently based on the view (single view vs main view)
            if (!editKey) {
                // Main view (multiple groups): Include groupIndex in the input names
                newRow.innerHTML = `
                    <td><input type="text" name="dropdown_groups[${groupIndex}][options][${optionIndex}][label]" value="" /></td>
                    <td><input type="text" name="dropdown_groups[${groupIndex}][options][${optionIndex}][value]" value="" /></td>
                    <td><button type="button" class="button remove-option">Remove</button></td>
                `;
            } else {
                // Single view (one group): Don't include groupIndex in the input names
                newRow.innerHTML = `
                    <td><input type="text" name="dropdown_groups[0][options][${optionIndex}][label]" value="" /></td>
                    <td><input type="text" name="dropdown_groups[0][options][${optionIndex}][value]" value="" /></td>
                    <td><button type="button" class="button remove-option">Remove</button></td>
                `;
            }

            // Append the new row to the options table
            optionsTable.appendChild(newRow);
        }

        // Remove option
        if (e.target && e.target.classList.contains('remove-option')) {
            e.target.closest('tr').remove(); // Remove the option row
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

    // Fetch dropdown groups dynamically by field_type: dropdown
    $dropdown_groups = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');

    // Fetch true/false fields dynamically by field_type: truefalse
    $true_false_fields = apply_filters('filter_wp_options_by_field_type', null, 'truefalse');

    // Localize the dropdown groups for the dropdown block editor script
    wp_localize_script('ace-field-options-block-editor', 'dropdownGroups', $dropdown_groups);

    // Localize the true/false fields for the true/false block editor script
    wp_localize_script('ace-field-options-truefalse-editor', 'trueFalseFields', $true_false_fields);
}
add_action('enqueue_block_editor_assets', 'ace_field_options_enqueue_editor_assets');



// Register post meta fields dynamically based on Option Groups and True/False fields
add_action('init', 'acefields_register_dynamic_meta');

function acefields_register_dynamic_meta() {
    // Fetch Option Groups (Dropdowns) using the filter
    $dropdown_groups = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');

    // Register post meta fields for Dropdown groups
    if (!empty($dropdown_groups)) {
        foreach ($dropdown_groups as $group_data) {
            // Create the sanitized meta key from the group name
            $group_name_key = create_underscored_value($group_data['name']);

            // Register meta field for each valid dropdown group
            register_post_meta(
                'post',
                $group_name_key,
                array(
                    'show_in_rest'      => true,
                    'single'            => true,
                    'type'              => 'string', // Type should be string, even though we might store serialized arrays
                    'sanitize_callback' => 'acefields_sanitize_serialized_meta', // Custom sanitize function
                )
            );
        }
    }

    // Fetch True/False fields using the filter
    $true_false_fields = apply_filters('filter_wp_options_by_field_type', null, 'truefalse');

    // Register post meta fields for True/False fields
    if (!empty($true_false_fields)) {
        foreach ($true_false_fields as $field_data) {
            $field_name_key = create_underscored_value($field_data['name']);
    
            // Register each true/false field as a post meta field
            register_post_meta(
                'post',
                $field_name_key,
                array(
                    'show_in_rest'      => true,
                    'single'            => true,
                    'type'              => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean', // Sanitize boolean values
                )
            );
        }
    }
    
}

// Custom sanitize function for serialized meta data
function acefields_sanitize_serialized_meta($meta_value) {
    if (is_array($meta_value) || is_object($meta_value)) {
        // Convert arrays/objects to JSON string for storage
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

    $dropdown_groups = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');

    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = 'ace_custom_' . create_underscored_value($group_name);
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



function ace_field_options_render_true_false_block($attributes) {
    $post_id = get_the_ID();
    $selected_field = $attributes['selectedField'] ?? ''; // Field name to fetch

    // Fetch the stored value for the true/false field
    $true_false_value = get_post_meta($post_id, create_underscored_value($selected_field), true);

    // Fetch the specific true/false field from the database
    $field_option_name = 'ace_custom_' . create_underscored_value($selected_field);
    $true_false_field = get_option($field_option_name, array());

    // Initialize the label
    $label = '';

    // Determine the label based on the stored value
    if (!empty($true_false_field)) {
        $label = ($true_false_value === '1') ? $true_false_field['true_label'] : $true_false_field['false_label'];
    }

    // Output the block with the selected label
    return sprintf(
        '<p class="wp-block-ace-field-options-true-false-block wp-block-paragraph">%s</p>',
        esc_html($label)
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
    register_rest_route('ace/v1', '/meta/(?P<id>\d+)(/(?P<field>[\w\d_]+))?(:(?P<part>label|value))?', array(
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

    // Fetch dropdown groups
    $dropdown_groups = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');

    // Fetch true/false fields
    $true_false_fields = apply_filters('filter_wp_options_by_field_type', null, 'truefalse');

    // Handle dropdown groups (missing logic)
    if (!empty($dropdown_groups)) {
        foreach ($dropdown_groups as $group_data) {
            // Check if the group should be shown in REST
            if (empty($group_data['show_in_rest'])) {
                continue; // Skip this group if it shouldn't be shown
            }

            $meta_key = create_underscored_value($group_data['name']); // Get the key for the field

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

    // Process true/false fields (existing logic)
    foreach ($true_false_fields as $field) {
        $meta_key = create_underscored_value($field['name']); // Get the key for the true/false field

        if (isset($meta[$meta_key])) {
            // Check if this field should be shown in REST API
            if (empty($field['show_in_rest'])) {
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

        // Check if the field exists in the filtered_meta
        if (array_key_exists($field_key, $filtered_meta)) {
            $field_data = $filtered_meta[$field_key]; // Get the specific field data

            // Check if a specific part (label or value) is requested
            if (isset($data['part']) && in_array($data['part'], ['label', 'value'])) {
                return new WP_REST_Response($field_data[$data['part']]); // Return the requested part
            }

            return new WP_REST_Response($field_data); // Return the full field data if no specific part is requested
        } else {
            return new WP_Error('rest_field_invalid', __('Invalid field specified.'), array('status' => 404));
        }
    }

    return new WP_REST_Response($filtered_meta); // Return the filtered meta array with label and value
}




add_filter('rest_prepare_post', 'modify_post_meta_for_rest', 10, 3);

function modify_post_meta_for_rest($response, $post, $request) {
    // Check if the request is for the frontend (view context)
    if ($request->get_param('context') !== 'view') {
        return $response; // Return unmodified response for non-view contexts
    }

    $meta = $response->data['meta'];

    // Fetch all dropdown option groups dynamically by filtering options by field_type: dropdown
    $dropdown_groups = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');

    if (!empty($dropdown_groups)) {
        foreach ($dropdown_groups as $group_data) {
            $meta_key = create_underscored_value($group_data['name']); // Get the key for the field

            // Check if the group should be shown in REST
            if (empty($group_data['show_in_rest'])) {
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
                            break; // Exit loop once a match is found
                        }
                    }
                }

                // Only add to the response if a matching option was found
                if ($label !== '' && $value !== '') {
                    $response->data['meta'][$meta_key] = array(
                        'label' => $label,
                        'value' => $value // Return the matched label and value
                    );
                }
            }
        }
    }

    // Fetch all true/false fields dynamically by filtering options by field_type: truefalse
    $true_false_fields = apply_filters('filter_wp_options_by_field_type', null, 'truefalse');

    foreach ($true_false_fields as $field) {
        $meta_key = create_underscored_value($field['name']); // Get the key for the true/false field

        if (isset($meta[$meta_key])) {
            // Check if this field should be shown in REST API
            if (empty($field['show_in_rest'])) {
                unset($response->data['meta'][$meta_key]); // Optionally remove the field from response if show_in_rest is false
            } else {
                $meta_value = $meta[$meta_key];
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
// Get all true/false fields based on field_type
$true_false_fields = apply_filters('filter_wp_options_by_field_type', $all_options, 'truefalse');

foreach ($existing_group_names as $group_name) {
    $option_name = 'ace_custom_' . create_underscored_value($group_name);
    $field_data = get_option($option_name, array());

    if (!empty($field_data) && isset($field_data['field_type']) && $field_data['field_type'] === 'truefalse') {
        $true_false_fields[] = $field_data;
    }
}

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
        $option_name = 'ace_custom_' . create_underscored_value($group_name); // Use 'group_name' from the request

        // Fetch all groups (dropdowns and true/false) using the filter
        $all_groups = array_merge(
            apply_filters('filter_wp_options_by_field_type', null, 'dropdown'),
            apply_filters('filter_wp_options_by_field_type', null, 'truefalse')
        );

        // Check if the group exists in the filtered options
        $group_exists = false;
        foreach ($all_groups as $group) {
            if (create_underscored_value($group['name']) === create_underscored_value($group_name)) {
                $group_exists = true;
                break;
            }
        }

        if ($group_exists && delete_option($option_name)) {
            // Fetch the appropriate list to update depending on the group type
            $group_names = apply_filters('filter_wp_options_by_field_type', null, 'dropdown');

            // Remove the group name from the list if it exists
            if (($key = array_search($group_name, $group_names)) !== false) {
                unset($group_names[$key]);
            }

            wp_send_json_success('Option deleted successfully');
        } else {
            wp_send_json_error('Option not found');
        }
    } else {
        wp_send_json_error('Invalid option name');
    }
}



function ace_cleanup_nameless_true_false_fields() {
    $true_false_fields = apply_filters('filter_wp_options_by_field_type', wp_load_alloptions(), 'truefalse');
    
    foreach ($true_false_fields as $field) {
        if (empty($field['name'])) {
            // Delete the option if it has no valid name
        //    delete_option("ace_custom_" . create_underscored_value($field['name']));
        }
    }
}



?>
