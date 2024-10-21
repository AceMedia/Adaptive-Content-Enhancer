<?php
/**
 * Plugin Name: Adaptive Content Enhancer
 * Description: A WordPress block plugin with multiple option groups for dropdowns.
 * Version: 0.420
 * Author: Shane Rounce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register the admin menu page for managing Option Groups.
add_action( 'admin_menu', 'ace_field_options_admin_menu' );

add_action('admin_post_import_acf_fields', 'handle_acf_import_submission');


function ace_field_options_admin_menu() {
    add_menu_page(
        'Options',
        'Options',
        'manage_options',
        'adaptive-content-enhancer',
        'adaptive_content_enhancer_choices_page_html',
        'dashicons-list-view',
        20
    );

    add_submenu_page(
        'adaptive-content-enhancer',
        'ACF Import',
        'ACF Import',
        'manage_options',
        'ace-acf-import',
        'ace_field_options_acf_settings_page_html'
    );
}

// Enqueue the settings CSS
function ace_field_options_enqueue_styles() {
    $screen = get_current_screen();
    
    // Check if we are on the settings page
    if ($screen->id === 'toplevel_page_adaptive-content-enhancer') {
        wp_enqueue_style(
            'acemedia-settings', // Handle
            plugins_url('/assets/styles/css/settings.css', __FILE__), // Path to the CSS file
            array(), // Dependencies (if any)
            null // Version number
        );


        wp_enqueue_script(
            'acemedia-ajax',
            plugins_url('/assets/blocks/ajax.js', __FILE__),
            array('jquery'), // Dependencies
            null, // Version
            true // Load in footer
        );
    
        // Localize script to make the AJAX URL available
        wp_localize_script('acemedia-ajax', 'aceAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    

    }
}
add_action('admin_enqueue_scripts', 'ace_field_options_enqueue_styles');


// Enqueue the editor CSS
function ace_field_options_enqueue_editor_styles() {
    wp_enqueue_style(
        'acemedia-editor', // Handle
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
        $field_data = (array) get_option($option_name, array());

            if (!empty($field_data) && isset($field_data['field_type']) && $field_data['field_type'] === $type) {
                $filtered_options[] = $field_data;
            }
    }

    return $filtered_options;
}


// Fetch dropdown groups after saving dynamically from updated options
$choices = array_merge(
    apply_filters('filter_wp_options_by_field_type', null, 'select'),
    apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
    apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
    apply_filters('filter_wp_options_by_field_type', null, 'radio'),
    apply_filters('filter_wp_options_by_field_type', null, 'buttons')
);



// Admin page HTML to manage Option Groups.
function adaptive_content_enhancer_choices_page_html() {
    if (!current_user_can('manage_options')) {
        error_log('Unauthorized attempt to delete group option');
        wp_send_json_error('Unauthorized', 403);
    }


  // Check if we're editing a specific group or field
  $edit_key = isset($_GET['edit']) ? sanitize_text_field($_GET['edit']) : '';
  $field_type = ''; // New variable to determine the field type

// Retrieve all Option Groups again to display the newly added ones
$choices = array_merge(
    apply_filters('filter_wp_options_by_field_type', null, 'select'),
    apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
    apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
    apply_filters('filter_wp_options_by_field_type', null, 'radio'),
    apply_filters('filter_wp_options_by_field_type', null, 'buttons')
);




// Loop through all true/false fields stored individually
$true_false_fields = apply_filters('filter_wp_options_by_field_type', $all_options, 'truefalse');

foreach ($all_options as $option_name => $value) {
        // Retrieve only the true/false fields
        $field_data = (array) get_option($option_name, array());

        if (!empty($field_data)) {
            $true_false_fields[] = $field_data;
        }
}


// Handle saving/updating a specific group in single edit mode.
if (isset($_GET['edit'])) {
    $edit_group_name = sanitize_text_field($_GET['edit']);
    $edit_option_name = 'choices_' . create_underscored_value($edit_group_name);

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
                'field_type' => sanitize_text_field($group['field_type']),
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
    check_admin_referer('save_choices');

    // Retrieve the existing group names
    $existing_group_names = array_merge(
        apply_filters('filter_wp_options_by_field_type', null, 'select'),
        apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
        apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
        apply_filters('filter_wp_options_by_field_type', null, 'radio'),
        apply_filters('filter_wp_options_by_field_type', null, 'buttons')
    );

    // Initialize array for new group names
    $new_group_names = array();

    // Save or update group names
    if (isset($_POST['choices']) && is_array($_POST['choices'])) {
        foreach ($_POST['choices'] as $group) {
            if (isset($group['name'], $group['options']) && is_array($group['options'])) {
                // Create the sanitized name for the group
                $sanitized_name = sanitize_text_field($group['name']);
                $option_name = 'choices_' . create_underscored_value($sanitized_name);

                // Set default field_type to 'select' if not provided      
              $field_type = isset($group['field_type']) ? sanitize_text_field($group['field_type']) : 'select';


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
                'field_type' => $field_type, // Save the field type
                    'show_in_rest' => isset($group['show_in_rest']) ? 1 : 0,
                );

                // Save or update the group as its own option
                update_option($option_name, $group_data);
            }
        }
    }



 // Repopulate choices after saving
 $choices = array_merge(
    apply_filters('filter_wp_options_by_field_type', null, 'select'),
    apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
    apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
    apply_filters('filter_wp_options_by_field_type', null, 'radio'),
    apply_filters('filter_wp_options_by_field_type', null, 'buttons')
);


    if (!empty($group_names)) {
        foreach ($group_names as $group_name) {
            $option_name = create_underscored_value($group_name);
            $group_data = get_option($option_name, array());

            // If editing a specific group, only include that one; otherwise, include all
            if (empty($edit_key) || create_underscored_value($group_data['name']) === $edit_key) {
                if (!empty($group_data)) {
                    $field_groups[] = $group_data; // Add the retrieved group data
                }
            }
        }
    }

 // Reset the true_false_fields array to avoid duplication
 $true_false_fields = array(); // Reset the array before repopulating

 // Handle saving true/false fields
// Save or update true/false fields when form is submitted
if (isset($_POST['true_false_fields']) && is_array($_POST['true_false_fields'])) {
    foreach ($_POST['true_false_fields'] as $field) {
        // Ensure the field has a valid name, true label, and false label before proceeding
        if (!empty($field['name']) && !empty($field['true_label']) && !empty($field['false_label'])) {
            $field_name = create_underscored_value($field['name']);
        
            // Prepare data to be saved as a WordPress option
            $data = array(
                'name' => sanitize_text_field($field['name']),
                'true_label' => sanitize_text_field($field['true_label']),
                'false_label' => sanitize_text_field($field['false_label']),
                'field_type' => 'truefalse', // Make sure to set the field type explicitly
                'show_in_rest' => isset($field['show_in_rest']) ? 1 : 0,
            );
            
            // Save or update the option
            update_option("truefalse_" . $field_name, $data);
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
        <?php wp_nonce_field('save_choices'); 


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
            const tableBody = document.querySelector('#choices-table tbody');
            const groupIndex = tableBody.children.length;
            const newRow = document.createElement('tr');
            newRow.classList.add('choices-group-row');
            newRow.innerHTML = `
                <td><input type="text" name="choices[${groupIndex}][name]" value="" /></td>
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
            const groupRow = e.target.closest('.choices-group-row');
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
                    <td><input type="text" name="choices[${groupIndex}][options][${optionIndex}][label]" value="" /></td>
                    <td><input type="text" name="choices[${groupIndex}][options][${optionIndex}][value]" value="" /></td>
                    <td><button type="button" class="button remove-option">Remove</button></td>
                `;
            } else {
                // Single view (one group): Don't include groupIndex in the input names
                newRow.innerHTML = `
                    <td><input type="text" name="choices[0][options][${optionIndex}][label]" value="" /></td>
                    <td><input type="text" name="choices[0][options][${optionIndex}][value]" value="" /></td>
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
        'acemedia-block-editor',
        plugins_url( '/assets/blocks/options.js', __FILE__ ),
        array( 'wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data' ),
        filemtime( plugin_dir_path( __FILE__ ) . 'assets/index.js' )
    );


    // Fetch dropdown groups dynamically by field_type: dropdown
    $choices = array_merge(
        apply_filters('filter_wp_options_by_field_type', null, 'select'),
        apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
        apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
        apply_filters('filter_wp_options_by_field_type', null, 'radio'),
        apply_filters('filter_wp_options_by_field_type', null, 'buttons')
    );

    // Fetch true/false fields dynamically by field_type: truefalse
    $true_false_fields = apply_filters('filter_wp_options_by_field_type', null, 'truefalse');

    // Localize the dropdown groups for the dropdown block editor script
    wp_localize_script('acemedia-block-editor', 'choiceGroups', $choices);

    // Localize the true/false fields for the true/false block editor script
    wp_localize_script('acemedia-truefalse-editor', 'trueFalseFields', $true_false_fields);
}
add_action('enqueue_block_editor_assets', 'ace_field_options_enqueue_editor_assets');



// Register post meta fields dynamically based on Option Groups and True/False fields
add_action('init', 'acefields_register_dynamic_meta');

function acefields_register_dynamic_meta() {
    // Define a mapping of field types to how they should be treated
    $field_types = [
        'select' => 'string',
        'truefalse' => 'boolean',
        'checkbox' => 'string',
        'radio' => 'string',
        'buttons' => 'string',
    ];

    // Loop over each field type and register its meta fields
    foreach ($field_types as $type => $meta_type) {
        // Fetch fields using the filter based on their type
        $fields = apply_filters('filter_wp_options_by_field_type', null, $type);

        if (!empty($fields)) {
            foreach ($fields as $field_data) {
                // Create the sanitized meta key from the field name
                $field_name_key = create_underscored_value($field_data['name']);

                // Determine the sanitize callback based on the field type
                $sanitize_callback = $meta_type === 'boolean' ? 'rest_sanitize_boolean' : 'acefields_sanitize_serialized_meta';

                // Register each field as a post meta field
                register_post_meta(
                    'post',
                    $field_name_key,
                    array(
                        'show_in_rest'      => true,
                        'single'            => true,
                        'type'              => $meta_type, // Type based on field type
                        'sanitize_callback' => $sanitize_callback,
                    )
                );
            }
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

function ace_field_options_render_block($attributes) {
    $wrapper_attributes = get_block_wrapper_attributes();
    $selected_group = $attributes['selectedGroup'] ?? '';
    $selected_field_type = $attributes['selectedFieldType'] ?? '';
    $show_as_input = $attributes['showAsTextFrontend'] ?? false;

    // Fetch the group options dynamically.
    $group_options = get_group_options($selected_group, $attributes);
    $dropdown_label = $group_options['label'];

    $output = '';

    // Render based on whether inputs or plain text are requested.
    if ($show_as_input) {
        switch ($selected_field_type) {
            case 'truefalse':
                // Fetch true/false field labels dynamically from wp_options.
                $true_label = $group_options['label']; // This is already dynamically fetched from wp_options
                $is_checked = in_array($attributes['trueFalseValue'], ['1', true, 'true'], true) ? 'checked' : '';

                // Render the checkbox with the correct label.
                $output = sprintf(
                    '<label><input type="checkbox" class="acemedia-checkbox" %s> %s</label>',
                    $is_checked,
                    esc_html($true_label)
                );
                break;

            case 'checkbox':
            case 'radio':
            case 'buttons':
                // Loop and render inputs for each option in the selected group.
                foreach ($group_options['options'] as $option) {
                    $isSelected = $attributes['choiceValue'] === $option['value'] ? 'checked' : '';
                    $inputType = ($selected_field_type === 'checkbox') ? 'checkbox' : 'radio';
                    $output .= sprintf(
                        '<label><input type="%s" name="%s" value="%s" class="acemedia-%s" %s> %s</label>',
                        esc_attr($inputType),
                        esc_attr($selected_group),
                        esc_attr($option['value']),
                        esc_attr($inputType),
                        $isSelected,
                        esc_html($option['label'])
                    );
                }
                break;

            default:
                // Default to select dropdown.
                $select = '<select class="acemedia-input">';
                foreach ($group_options['options'] as $option) {
                    $isSelected = $attributes['choiceValue'] === $option['value'] ? 'selected' : '';
                    $select .= sprintf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($option['value']),
                        $isSelected,
                        esc_html($option['label'])
                    );
                }
                $select .= '</select>';
                $output = $select;
                break;
        }
        return sprintf('<div %s>%s</div>', $wrapper_attributes, $output);
    } else {
        // Render plain text labels for non-input fields.
        if ($selected_field_type === 'truefalse') {
            // Fetch true/false field label dynamically for display.
            $dropdown_label = $group_options['label']; // Use the dynamically fetched label
        }

        return sprintf('<p %s>%s</p>', $wrapper_attributes, esc_html($dropdown_label));
    }
}


function get_group_options($selected_group, $attributes) {
    $selected_group = create_underscored_value($selected_group);
    $dropdown_value_ref = $attributes['choiceValue'] ?? '';

    $dropdown_groups = array_merge(
        apply_filters('filter_wp_options_by_field_type', null, 'select'),
        apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
        apply_filters('filter_wp_options_by_field_type', null, 'radio'),
        apply_filters('filter_wp_options_by_field_type', null, 'buttons'),
        apply_filters('filter_wp_options_by_field_type', null, 'truefalse')
    );

    $dropdown_label = '';
    $dropdown_value = '';
    $dropdown_options = []; // Initialize an empty array for options

    if ($selected_group) {
        foreach ($dropdown_groups as $group) {
            if (create_underscored_value($group['name']) === $selected_group) {
                if ($group['field_type'] === 'truefalse') {
                    $true_label = $group['true_label'] ?? 'True';
                    $is_true = in_array($attributes['trueFalseValue'], ['1', true, 'true'], true);
                    $dropdown_label = $is_true ? $true_label : 'False';
                    $dropdown_value = $is_true ? '1' : '0';
                } else {
                    // For other field types, populate dropdown options.
                    foreach ($group['options'] as $option) {
                        $dropdown_options[] = [
                            'label' => $option['label'],
                            'value' => $option['value']
                        ];
                        if ((string)$dropdown_value_ref === (string)$option['value']) {
                            $dropdown_label = $option['label'];
                            $dropdown_value = $option['value'];
                        }
                    }
                }
            }
        }
    }

    return [
        'label' => $dropdown_label,
        'value' => $dropdown_value,
        'options' => $dropdown_options // Ensure options are returned
    ];
}








register_block_type('adaptive-content-enhancer/choices', array(
    'render_callback' => 'ace_field_options_render_block',
    'attributes' => array(
        'selectedGroup' => array(
            'type' => 'string',
            'default' => ''
        ),
        'choiceValue' => array(
            'type' => 'string',
            'default' => ''
        ),
        'choiceLabel' => array(
            'type' => 'string',
            'default' => ''
        ),
        'selectedFieldType' => array(
            'type' => 'string',
            'default' => ''
        ),
        'style' => array(
            'type' => 'object',
            'default' => array(
                'spacing' => array(
                    'padding' => array(
                        'top' => '0',
                        'right' => '0',
                        'bottom' => '0',
                        'left' => '0'
                    )
                )
            )
        )
    ),
    'supports' => array(
        'align' => true,
        'attributes' => true,
        'color' => array(
            'text' => true,
            'background' => true,
        ),
        'spacing' => array(
            'padding' => true,
            'margin' => true,
        ),
        'typography' => array(
            'fontSize' => true,
            'lineHeight' => true,
            'textAlign' => true,
            '__experimentalFontWeights' => true,
            '__experimentalFontFamily' => true,
            '__experimentalFontStyle' => true,
            '__experimentalFontVariant' => true,
            '__experimentalLetterSpacing' => true,
            '__experimentalTextTransform' => true,
            '__experimentalCustomLineHeight' => true,
            'customFontSize' => true,
        ),
        '__experimentalBorder' => array(
            'color' => true,
            'radius' => true,
            'style' => true,
            'width' => true,
            '__experimentalDefaultControls' => array(
                'color' => true,
                'radius' => true,
                'style' => true,
                'width' => true,
            )
        ),
        'customClassName' => true,
    ),
));







// Register the true/false block with its own render callback
register_block_type('acemedia/true-false-block', array(
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
    $choices = array_merge(
    apply_filters('filter_wp_options_by_field_type', null, 'select'),
    apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
    apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
    apply_filters('filter_wp_options_by_field_type', null, 'radio'),
    apply_filters('filter_wp_options_by_field_type', null, 'buttons')
);

    // Fetch true/false fields
    $true_false_fields = apply_filters('filter_wp_options_by_field_type', null, 'truefalse');

    // Handle dropdown groups (missing logic)
    if (!empty($choices)) {
        foreach ($choices as $group_data) {
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
    $choices = array_merge(
    apply_filters('filter_wp_options_by_field_type', null, 'select'),
    apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
    apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
    apply_filters('filter_wp_options_by_field_type', null, 'radio'),
    apply_filters('filter_wp_options_by_field_type', null, 'buttons')
);

    if (!empty($choices)) {
        foreach ($choices as $group_data) {
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
    $true_false_fields = apply_filters('filter_wp_options_by_field_type', wp_load_alloptions(), 'truefalse');

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
                    'type'              => 'boolean', // Ensure this is a boolean field
                    'sanitize_callback' => 'rest_sanitize_boolean', // Use the default WordPress boolean sanitizer
                )
            );
        }
    }
}



add_action('wp_ajax_delete_wp_option', 'ace_delete_wp_option');

function ace_delete_wp_option() {
    if (!current_user_can('manage_options')) {
        error_log('User does not have permissions to delete options.');
        wp_send_json_error('Unauthorized', 403);
    }

    // Check if the option name is set
    if (isset($_POST['option_name'])) {
        $group_name = sanitize_text_field($_POST['option_name']);
        $option_name = create_underscored_value($group_name); // Use 'group_name' from the request

        // Fetch all groups (dropdowns and true/false) using the filter
        $all_groups = array_merge(
            apply_filters('filter_wp_options_by_field_type', null, 'select'),
            apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
            apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
            apply_filters('filter_wp_options_by_field_type', null, 'radio'),
            apply_filters('filter_wp_options_by_field_type', null, 'buttons')
        );

        // Check if the group exists in the filtered options
        $group_exists = false;
        foreach ($all_groups as $group) {
            if (create_underscored_value($group['name']) === $option_name) {
                $group_exists = true;
                break;
            }
        }

        if (delete_option($option_name)) {
            error_log("Deleted group option: " . $option_name);
            wp_send_json_success('Option deleted successfully');
        } else {
            error_log("Failed to delete group option: " . $option_name);
            wp_send_json_error(print_r($all_groups, false));
        }
    } else {
        error_log('Invalid option name in delete request');
        wp_send_json_error('Invalid option name');
    }
}



function ace_cleanup_nameless_true_false_fields() {
    $true_false_fields = apply_filters('filter_wp_options_by_field_type', wp_load_alloptions(), 'truefalse');
    
    foreach ($true_false_fields as $field) {
        if (empty($field['name'])) {
            // Delete the option if it has no valid name
        //    delete_option("choices_" . create_underscored_value($field['name']));
        }
    }
}


function get_all_acf_fields_and_types() {
    global $wpdb;

    // Query to fetch all ACF-related meta keys (global, without post_id constraint)
    $acf_field_keys = $wpdb->get_results("
        SELECT DISTINCT meta_key, meta_value 
        FROM {$wpdb->postmeta}
        WHERE meta_key LIKE '\\_%'
        AND meta_value LIKE 'field_%'
    ");

    $acf_fields = array();
    
    foreach ($acf_field_keys as $field_key) {
        $actual_field_key = substr($field_key->meta_key, 1); // Remove leading underscore
        $acf_field_data = get_acf_field_data_by_key($field_key->meta_value); // Fetch the field data from wp_options
    

        // Check if the type exists
        if ($acf_field_data && isset($acf_field_data['type'])) {
            // If the type exists, assign it to the array
            $acf_fields[$actual_field_key] = $acf_field_data['type'];
        } else {
            // If not, assign 'unknown'
            $acf_fields[$actual_field_key] = 'unknown';
        }
    }
    
    return $acf_fields;
}

function get_post_meta_by_key($field_key) {
    global $wpdb;

    // Look for the field type by fetching the ACF field definition
    $acf_field = $wpdb->get_var($wpdb->prepare("
        SELECT meta_value 
        FROM {$wpdb->postmeta}
        WHERE meta_key = %s
    ", $field_key));

    if ($acf_field) {
        // ACF stores field definitions as serialized data, so we need to unserialize
        $acf_field_data = maybe_unserialize($acf_field);
        return $acf_field_data['type'] ?? 'unknown'; // Return 'type' if present, or 'unknown'
    }

    return 'unknown'; // Fallback if not found
}

function ace_field_options_acf_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Fetch ACF fields and their types
    $acf_fields = get_all_acf_fields_and_types();

    ?>
    <div class="wrap">
        <h1>Import ACF Fields</h1>
        <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="import_acf_fields">
    <?php wp_nonce_field('save_acf_imports'); ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Field Name</th>
                <th>Field Type</th>
                <th>Sample Data</th>
                <th>Import</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($acf_fields)) : ?>
                <?php foreach ($acf_fields as $field_key => $field_type) : ?>
                    <tr>
                        <td>
                            <?php
                            $acf_field_data = get_acf_field_content_by_excerpt($field_key);
                            if ($acf_field_data) {
                                echo 'Field Type: ' . esc_html($acf_field_data['post_excerpt']) . '<br>';

                                if (!empty($acf_field_data['post_content']['choices'])) {
                                    $choices = $acf_field_data['post_content']['choices'];
                                    echo 'Choices: <ul>';
                                    foreach ($choices as $value => $label) {
                                        echo '<li>' . esc_html($label) . ' (' . esc_html($value) . ')</li>';
                                    }
                                    echo '</ul>';
                                } else {
                                    echo 'No choices available.';
                                }
                            } else {
                                echo 'No matching ACF field found.';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($field_type); ?></td>
                        <td><?php echo esc_html(get_acf_sample_data($field_key)); ?></td>
                        <td>
                            <!-- Checkbox to import the field -->
                            <input type="checkbox" name="acf_import_fields[]" value="<?php echo esc_attr(serialize($choices)); ?>" />

                            <!-- Hidden field for post_excerpt (group name) -->
                            <input type="hidden" name="acf_group_names[]" value="<?php echo esc_attr($acf_field_data['post_excerpt']); ?>" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="4">No ACF fields found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p><input type="submit" value="Import Selected Fields" class="button-primary" /></p>
</form>

    </div>
    <?php
}


function get_acf_field_data_by_key($acf_field_key) {
    global $wpdb;

    // Query to fetch ACF field post content and post excerpt by post_name (field_xxxxx)
    $acf_field_post = $wpdb->get_row($wpdb->prepare("
        SELECT post_content, post_excerpt
        FROM {$wpdb->posts}
        WHERE post_name = %s
        AND post_type = 'acf-field'
    ", $acf_field_key));

    if ($acf_field_post) {
        // Unserialize the field data stored in post_content
        $acf_field_data = maybe_unserialize($acf_field_post->post_content);

        // Add post_excerpt to the field data for type comparison
        $acf_field_data['post_excerpt'] = $acf_field_post->post_excerpt;

        return $acf_field_data;
    }

    error_log('ACF field data not found for key: ' . $acf_field_key);
    return null; // Return null if not found
}

function get_acf_field_content_by_excerpt($field_key) {
    global $wpdb;

    // Query to fetch the post_content where post_excerpt matches the field_key
    $acf_field_post = $wpdb->get_row($wpdb->prepare("
        SELECT post_content, post_excerpt
        FROM {$wpdb->posts}
        WHERE post_excerpt = %s
        AND post_type = 'acf-field'
    ", $field_key));

    // Check if we got a result
    if ($acf_field_post) {
        // Unserialize the post_content since ACF stores serialized data
        $acf_field_data = maybe_unserialize($acf_field_post->post_content);

        // Return both the post_content (which holds field data) and the post_excerpt (field type)
        return array(
            'post_excerpt' => $acf_field_post->post_excerpt,  // Field type (like 'select', 'checkbox')
            'post_content' => $acf_field_data                 // Actual field data
        );
    }

    // If no result found, return null
    return null;
}


function handle_acf_import_submission() {
    // Validate user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized user');
    }

    // Validate the nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'save_acf_imports')) {
        wp_die('Invalid nonce');
    }

    // Process the form if there are fields to import
    if (isset($_POST['acf_import_fields']) && is_array($_POST['acf_import_fields']) && isset($_POST['acf_group_names']) && is_array($_POST['acf_group_names'])) {
        foreach ($_POST['acf_import_fields'] as $index => $serialized_choices) {
            // Remove any escaping before unserializing
            $cleaned_choices = stripslashes($serialized_choices);

            // Deserialize the cleaned choices array
            $choices = unserialize($cleaned_choices);

            // Fetch the corresponding group name (post_excerpt)
            $group_name = isset($_POST['acf_group_names'][$index]) ? sanitize_text_field($_POST['acf_group_names'][$index]) : '';

            error_log('Deserialized Choices: ' . print_r($choices, true));
            error_log('Group Name (post_excerpt): ' . $group_name);

            if ($choices && is_array($choices) && !empty($group_name)) {
                // Prepare the group data in the required format
                $group_data = array(
                    'name' => $group_name,
                    'options' => array_map(function ($value, $label) {
                        return array(
                            'label' => sanitize_text_field($label),
                            'value' => sanitize_text_field($value),
                        );
                    }, array_keys($choices), $choices),
                    'field_type' => sanitize_text_field($group['field_type']),
                    'show_in_rest' => 1,
                );

                // Create the option key name based on the post_excerpt
                $option_name = create_underscored_value($group_name);
                error_log('Importing ACF group: ' . $group_name);

                // Save the group data as an option in the required serialized format
                $updated = update_option($option_name, $group_data);
                $updated = true; // Simulate successful update
                // Log success for each imported group
                if ($updated) {
                    error_log("Successfully imported ACF group: " . $group_name);

                    // Step 2: Scan for posts and match ACF data to update as post meta
                    $posts = get_posts(array('numberposts' => -1)); // Get all posts

                    foreach ($posts as $post) {
                        // Fetch all meta for this post
                        $post_meta = get_post_meta($post->ID);
                    
                        error_log('Post Meta Data for Post #' . $post->ID . ': ' . print_r($post_meta, true)); // Log all post meta for the post
                    
                        // Loop through each meta to find ACF fields that match the group choices
                        foreach ($post_meta as $meta_key => $meta_value) {
                            // Check if the meta key is related to ACF (ACF fields typically start with an underscore)
                            if (strpos($meta_key, '_') === 0) {
                                $acf_meta_key = substr($meta_key, 1); // Remove the leading underscore
                                $acf_meta_value = maybe_unserialize($meta_value[0]); // Unserialize if needed
                                
                                // Check if we have the regular version of this meta key in the post's metadata
                                if (isset($post_meta[$acf_meta_key])) {
                                    $regular_meta_value = maybe_unserialize($post_meta[$acf_meta_key][0]); // Unserialize the corresponding regular meta value if needed
                                    error_log('ACF Meta Key: ' . $acf_meta_key . ' | Value: ' . print_r($regular_meta_value, true));
                                } else {
                                    error_log('No matching non-prefixed meta value found for ACF key: ' . $acf_meta_key);
                                }
                            }
                        }
                    }
                    
                    
                    

                                    } else {
                    error_log("Failed to import ACF group: " . $group_name);
                }
            } else {
                error_log("Invalid choices or group name");
            }
        }
    }

    // Redirect back to the import page
    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=ace-acf-import');
    wp_redirect(add_query_arg('acf_imported', '1', $redirect_url));
    exit;
}








function get_acf_sample_data($field_key) {
    global $wpdb;

    // Fetch the most recent value for this ACF field from any post that has it, sorted by meta_id (assuming higher meta_id is more recent)
    $sample_value = $wpdb->get_var($wpdb->prepare("
        SELECT meta_value 
        FROM {$wpdb->postmeta}
        WHERE meta_key = %s
        ORDER BY meta_id DESC
        LIMIT 1
    ", $field_key));

    // Unserialize if necessary (ACF fields are sometimes serialized)
    if ($sample_value) {
        $sample_value = maybe_unserialize($sample_value);

        // Handle arrays or objects
        if (is_array($sample_value) || is_object($sample_value)) {
            $sample_value = json_encode($sample_value); // Convert to JSON for display
        }
    } else {
        $sample_value = 'No data found'; // Fallback message if no sample data is found
    }

    return $sample_value;
}




function ace_determine_field_type($meta_value) {
    if (is_array($meta_value)) {
        error_log('Array Value: ' . print_r($meta_value, true));
        return 'array';
    } elseif (is_bool($meta_value)) {
        return 'boolean';
    } elseif (is_numeric($meta_value)) {
        return is_float($meta_value + 0) ? 'number' : 'integer';
    } else {
        return 'string';
    }
}

function ace_dynamically_register_acf_fields($post_id) {
    global $wpdb;

    $acf_field_keys = $wpdb->get_results($wpdb->prepare("
        SELECT meta_key, meta_value FROM $wpdb->postmeta 
        WHERE post_id = %d 
        AND meta_key LIKE '\\_%'
        AND meta_value LIKE 'field_%%'
    ", $post_id));

    $fields_to_register = [];

    foreach ($acf_field_keys as $field_key) {
        $actual_field_key = substr($field_key->meta_key, 1); // Remove leading underscore
        $meta_value = get_post_meta($post_id, $actual_field_key, true); // Get the stored value

        // Determine the field type based on the stored meta value
        $field_type = ace_determine_field_type($meta_value);

        // Handle arrays: dynamically create the schema for array types
        if ($field_type === 'array') {
            $items_schema = ace_determine_array_items_schema($meta_value);

            $fields_to_register[] = [
                'meta_key' => $actual_field_key,
                'type' => 'array',
                'schema' => [
                    'items' => $items_schema, // Define the schema for each array item dynamically
                ]
            ];

        } else {
            // Register other non-array field types
            $fields_to_register[] = [
                'meta_key' => $actual_field_key,
                'type' => $field_type
            ];
        }

        // Log for debugging purposes
        error_log("Determined field type: $field_type for field: $actual_field_key");
    }

    return $fields_to_register;
}

function ace_determine_array_items_schema($meta_value) {
    if (!empty($meta_value) && is_array($meta_value)) {
        $first_item = reset($meta_value); // Get the first item to inspect its structure

        if (is_array($first_item)) {
            // If each item is an array/object, return a schema for an object
            return [
                'type' => 'object',
                'properties' => ace_determine_object_properties_schema($first_item) // Schema for object properties
            ];
        } elseif (is_string($first_item)) {
            return ['type' => 'string']; // Simple array of strings
        } elseif (is_numeric($first_item)) {
            return ['type' => 'number']; // Array of numbers
        } elseif (is_bool($first_item)) {
            return ['type' => 'boolean']; // Array of booleans
        }
    }

    // Default to string array if no specific type is detected
    return ['type' => 'string'];
}

function ace_determine_object_properties_schema($object) {
    $properties_schema = [];

    foreach ($object as $key => $value) {
        if (is_string($value)) {
            $properties_schema[$key] = ['type' => 'string'];
        } elseif (is_numeric($value)) {
            $properties_schema[$key] = ['type' => 'number'];
        } elseif (is_bool($value)) {
            $properties_schema[$key] = ['type' => 'boolean'];
        } elseif (is_array($value)) {
            $properties_schema[$key] = ['type' => 'array', 'items' => ace_determine_array_items_schema($value)];
        }
    }

    return $properties_schema;
}

function ace_map_acf_to_rest_type($acf_type) {
    $mapping = array(
        'text' => 'string',
        'textarea' => 'string',
        'number' => 'number',
        'true_false' => 'boolean',
        'select' => 'string',
        'google_map' => 'array', // Handle Google Map as an array
        'repeater' => 'array',   // Handle repeaters as arrays
        // Add more mappings for other ACF types as needed
    );

    return $mapping[$acf_type] ?? 'string'; // Default to 'string' if no mapping found
}

add_action('rest_api_init', 'conditionally_register_acf_fields_for_current_post');

function conditionally_register_acf_fields_for_current_post() {
    // Only run this inside the WordPress admin and the post editor screen.
    if (is_admin() && isset($_GET['post'])) {
        $post_id = intval($_GET['post']); // Get the current post ID from the URL

        if ($post_id) {
            error_log('Post ID inside if block: ' . $post_id);

            // Dynamically register ACF fields only for the current post being edited
            $fields = ace_dynamically_register_acf_fields($post_id);

            // Loop through the fields and register them
            foreach ($fields as $field) {
                if ($field['type'] === 'array') {
                    // For array types, ensure schema for items is provided
                    $schema = [
                        'type'  => 'array',
                        'items' => $field['schema']['items']
                    ];
                } else {
                    // For non-array fields, standard schema
                    $schema = [
                        'type' => $field['type']
                    ];
                }
                error_log('Schema for field: ' . $field['meta_key'] . ' | ' . print_r($schema, true));

                if (function_exists('register_post_meta')) {
                    try {
                        register_post_meta(
                            'post',
                            $field['meta_key'],
                            array(
                                'show_in_rest'      => [
                                    'schema' => $schema // Dynamically assign schema based on the field type
                                ],
                                'single'            => true,
                                'type'              => $field['type'],
                            )
                        );
                        error_log('Successfully registered field: ' . $field['meta_key']);
                    } catch (Exception $e) {
                        error_log('Failed to register field: ' . $field['meta_key'] . ' | Error: ' . $e->getMessage());
                    }
                } else {
                    error_log('register_post_meta function not available.');
                }
            }
        } else {
            error_log('Post ID is not valid');
        }
    }

    // Register the test field
    register_post_meta(
        'post',
        'test',
        array(
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'string', // Type should be string, even though we might store serialized arrays
        )
    );
}


?>
