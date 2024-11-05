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
        'Fields',
        'Fields',
        'manage_options',
        'adaptive-content-enhancer',
        'adaptive_content_enhancer_choices_page_react',
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





// Enqueue the editor CSS
function ace_field_options_enqueue_editor_styles() {
    wp_enqueue_style(
        'acemedia-editor', // Handle
        plugins_url('/assets/styles/editor.css', __FILE__), // Path to the CSS file
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
    apply_filters('filter_wp_options_by_field_type', null, 'text'),
    apply_filters('filter_wp_options_by_field_type', null, 'select'),
    apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
    apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
    apply_filters('filter_wp_options_by_field_type', null, 'radio'),
);



// Enqueue editor assets (admin side) and styles
function ace_field_options_enqueue_editor_assets() {

    // Editor (backend) script for dropdown block (index.js)
    wp_enqueue_script(
        'acemedia-block-editor',
        plugins_url( '/assets/js/blocks/options.js', __FILE__ ),
        array( 'wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data' ),
        filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/blocks/options.js' )
    );


    // Fetch dropdown groups dynamically by field_type: dropdown
    $choices = array_merge(
        apply_filters('filter_wp_options_by_field_type', null, 'text'),
        apply_filters('filter_wp_options_by_field_type', null, 'select'),
        apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
        apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
        apply_filters('filter_wp_options_by_field_type', null, 'radio')
    );

    // Fetch true/false fields dynamically by field_type: truefalse

    // Localize the dropdown groups for the dropdown block editor script
    wp_localize_script('acemedia-block-editor', 'choiceGroups', $choices);

}
add_action('enqueue_block_editor_assets', 'ace_field_options_enqueue_editor_assets');



// Register post meta fields dynamically based on Option Groups and True/False fields
add_action('init', 'acefields_register_dynamic_meta_and_bindings');

function acefields_register_dynamic_meta_and_bindings() {
    // Define a mapping of field types to how they should be treated
    $field_types = [
        'text' => 'string',
        'select' => 'string',
        'truefalse' => 'boolean',
        'checkbox' => 'array', // Set to 'array' for checkboxes
        'radio' => 'string',
        'buttons' => 'string',
    ];

    // Loop over each field type and register its meta fields and associated block bindings sources
    foreach ($field_types as $type => $meta_type) {
        // Fetch fields using the filter based on their type
        $fields = apply_filters('filter_wp_options_by_field_type', null, $type);

        if (!empty($fields)) {
            foreach ($fields as $field_data) {
                // Create the sanitized meta key from the field name
                $field_name_key = create_underscored_value($field_data['name']);

                // Determine the sanitize callback based on the field type
                $sanitize_callback = 'acefields_sanitize_serialized_meta';

                // Define schema for array items if field type is checkbox
                $schema = ($type === 'checkbox') 
                    ? ['type' => 'array', 'items' => ['type' => 'string']] 
                    : ['type' => $meta_type];

                // Register each field as a post meta field
                register_meta(
                    'post',
                    $field_name_key,
                    array(
                        'show_in_rest'      => ['schema' => $schema], // Schema with array items for checkboxes
                        'single'            => true,
                        'type'              => $meta_type, // Type based on field type
                        'sanitize_callback' => $sanitize_callback,
                        'auth_callback'     => '__return_true',
                    )
                );
            }
        }
    }
}



// Custom sanitize function for serialized meta data
function acefields_sanitize_serialized_meta($meta_value) {
    // Return arrays and objects as they are to avoid double encoding
    if (is_array($meta_value) || is_object($meta_value)) {
        return $meta_value;
    }

    // Convert other data types to a sanitized string
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
    $show_as_input = $attributes['showAsInputOnFrontend'] ?? false;
    $show_labels = $attributes['showLabelsOnFrontend'] ?? false;

    // Fetch the group options dynamically.
    $group_options = get_group_options($selected_group, $attributes);
    $dropdown_label = $group_options['label'];
    $dropdown_title = $group_options['title']; // Use the dynamically fetched title

    $output = '';

    // Render based on whether inputs or plain text are requested.
    if ($show_as_input && $selected_field_type != 'text') {
        switch ($selected_field_type) {
            case 'truefalse':
                // Fetch true/false field labels dynamically from wp_options.
                $true_label = $group_options['label']; // Already dynamically fetched from wp_options
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
                if ($show_labels) {
                    $output .= '<label>';
                    $output .= $dropdown_title; // Render the title dynamically
                }

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
                $output .= $select;
                
                if ($show_labels) {
                    $output .= '</label>';
                }
                break;
        }
        return sprintf('<div %s>%s</div>', $wrapper_attributes, $output);
    } else {
        // Render plain text labels for non-input fields.
        if ($selected_field_type === 'truefalse') {
            $dropdown_label = $group_options['label']; // Use the dynamically fetched label
        }
        // Render plain text labels for non-input fields.
        if ($selected_field_type === 'text') {
            $dropdown_label = get_post_meta(get_the_ID(), $selected_group, true);
        }

        return sprintf('<p %s>%s</p>', $wrapper_attributes, esc_html($dropdown_label));
    }
}



add_action('enqueue_block_editor_assets', 'ace_enqueue_text_block');

function ace_enqueue_text_block() {
    $dir = plugin_dir_path(__FILE__);
    $url = plugin_dir_url(__FILE__);

    if (file_exists("{$dir}/assets/js/blocks/text.asset.php")) {
        $asset = include "{$dir}/assets/js/blocks/text.asset.php";

        wp_enqueue_script(
            'ace-text-block',
            "{$url}/assets/js/blocks/text.js",
            $asset['dependencies'],
            $asset['version'],
            true
        );
    } else {
        wp_enqueue_script(
            'ace-text-block',
            "{$url}/assets/js/blocks/text.js",
            ['wp-blocks', 'wp-element', 'wp-editor'],
            filemtime("{$dir}/assets/js/blocks/text.js"),
            true
        );
    }

    wp_set_script_translations('ace-text-block', 'ace');
}


function ace_enqueue_custom_select_scripts() {
    // Check if we should enqueue scripts based on the post content.
    if (!is_singular('post')) {
        return; // Only run on singular post pages.
    }

    global $post;
    if (has_blocks($post->ID)) {
        // Parse through the blocks and check for the condition.
        $blocks = parse_blocks($post->post_content);
        $should_enqueue = false;

        foreach ($blocks as $block) {
            if (
                isset($block['attrs']['showAsInputOnFrontend']) &&
                $block['attrs']['showAsInputOnFrontend'] === true
            ) {
                $should_enqueue = true;
                break;
            }
        }

        if ($should_enqueue) {
            // Enqueue Select2 or any other custom library
            wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
            wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', [], '4.0.13');

            // Custom script to initialize Select2 on your custom select elements
            wp_add_inline_script('select2', "
                jQuery(document).ready(function($) {
                    $('select').select2();
                });
            ");
        }
    }
}
add_action('wp_enqueue_scripts', 'ace_enqueue_custom_select_scripts');



function get_group_options($selected_group, $attributes) {
    $selected_group = create_underscored_value($selected_group);
    $dropdown_value_ref = $attributes['choiceValue'] ?? '';

    $dropdown_groups = array_merge(
        apply_filters('filter_wp_options_by_field_type', null, 'select'),
        apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
        apply_filters('filter_wp_options_by_field_type', null, 'radio'),
        apply_filters('filter_wp_options_by_field_type', null, 'truefalse')
    );

    $dropdown_label = '';
    $dropdown_value = '';
    $dropdown_options = []; // Initialize an empty array for options
    $dropdown_title = ''; // Initialize title

    if ($selected_group) {
        foreach ($dropdown_groups as $group) {
            if (create_underscored_value($group['name']) === $selected_group) {
                $dropdown_title = $group['title']; // Fetch the title dynamically

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

    return [
        'label' => $dropdown_label,
        'value' => $dropdown_value,
        'options' => $dropdown_options, // Ensure options are returned
        'title' => $dropdown_title // Include the dynamically fetched title
    ];
}




function ace_field_options_admin_styles($hook) {
    // Only load on plugin settings page
    if ('toplevel_page_adaptive-content-enhancer' !== $hook) {
        return;
    }

    wp_enqueue_style(
        'ace-field-options-admin',
        plugins_url('assets/styles/settings.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'assets/styles/settings.css')
    );
}
add_action('admin_enqueue_scripts', 'ace_field_options_admin_styles');




register_block_type('adaptive-content-enhancer/option', array(
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

// Extend REST API to include /meta endpoint for each post and specific meta fields
add_action('rest_api_init', function() {
    register_rest_route('wp/v2', '/posts/(?P<id>\d+)/meta', array(
        'methods' => 'GET',
        'callback' => 'get_post_meta_via_rest',
        'permission_callback' => '__return_true',
    ));

    // Register route to get specific meta field
    register_rest_route('wp/v2', '/posts/(?P<id>\d+)/meta/(?P<key>[\w\d_]+)', array(
        'methods' => 'GET',
        'callback' => 'get_specific_post_meta_via_rest',
        'permission_callback' => '__return_true',
    ));
});

function get_post_meta_via_rest($request) {
    $post_id = $request['id'];
    $post = get_post($post_id);

    if (!$post) {
        return new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), array('status' => 404));
    }

    // Make a request to fetch the full post data with context
    $rest_request = new WP_REST_Request('GET', "/wp/v2/posts/$post_id");
    $rest_request->set_param('context', 'view');
    $rest_request->set_param('from_meta_endpoint', 'true'); // Set flag for custom processing

    $rest_response = rest_do_request($rest_request);

    // Check if the response has data and is not an error
    if ($rest_response->is_error()) {
        return $rest_response;
    }

    // Retrieve and return only the meta data from the response
    $response_data = $rest_response->get_data();
    $meta = $response_data['meta'] ?? [];

    return new WP_REST_Response($meta);
}

// Handle requests for specific meta fields
function get_specific_post_meta_via_rest($request) {
    $post_id = $request['id'];
    $meta_key = $request['key'];
    $post = get_post($post_id);

    if (!$post) {
        return new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), array('status' => 404));
    }

    // Make a request to fetch the full post data with context
    $rest_request = new WP_REST_Request('GET', "/wp/v2/posts/$post_id");
    $rest_request->set_param('context', 'view');
    $rest_request->set_param('from_meta_endpoint', 'true'); // Set flag for custom processing

    $rest_response = rest_do_request($rest_request);

    // Check if the response has data and is not an error
    if ($rest_response->is_error()) {
        return $rest_response;
    }

    // Retrieve the meta data
    $response_data = $rest_response->get_data();
    $meta = $response_data['meta'] ?? [];

    // Check if the specific key exists
    if (!isset($meta[$meta_key])) {
        return new WP_Error('rest_meta_key_invalid', __('Invalid meta key.'), array('status' => 404));
    }

    return new WP_REST_Response($meta[$meta_key]);
}

// Modify existing filter to handle 'from_meta_endpoint' requests correctly
add_filter('rest_prepare_post', 'modify_post_meta_for_rest', 10, 3);
function modify_post_meta_for_rest($response, $post, $request) {
    $from_meta_endpoint = $request->get_param('from_meta_endpoint');
    
    // Skip non-view contexts and unrelated requests
    if ($request->get_param('context') !== 'view' && !$from_meta_endpoint) {
        return $response;
    }

    $meta = $response->data['meta'];
    $choices = array_merge(
        apply_filters('filter_wp_options_by_field_type', null, 'text'),
        apply_filters('filter_wp_options_by_field_type', null, 'select'),
        apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
        apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
        apply_filters('filter_wp_options_by_field_type', null, 'radio'),
    );

    if (!empty($choices)) {
        foreach ($choices as $group_data) {
            $meta_key = create_underscored_value($group_data['name']);
            if (empty($group_data['show_in_rest'])) {
                unset($response->data['meta'][$meta_key]);
            } else {
                $label = '';
                $value = '';

                if (isset($meta[$meta_key])) {
                    $stored_value = $meta[$meta_key];
                    foreach ($group_data['options'] as $option) {
                        if ($stored_value === $option['value']) {
                            $label = $option['label'];
                            $value = $option['value'];
                            break;
                        }
                    }
                }

                if ($label !== '' && $value !== '') {
                    $response->data['meta'][$meta_key] = array(
                        'label' => $label,
                        'value' => $value
                    );
                }
            }
        }
    }

    // Extend the links array to include the new /meta endpoint
    $post_id = $post->ID;
    $meta_link = rest_url("wp/v2/posts/{$post_id}/meta");
    $response->add_link('https://api.w.org/meta', $meta_link);

    return $response;
}


function filter_dynamic_meta_value($value, $source_name, $source_args, $block_instance, $attribute_name) {

    if ($source_name !== 'core/post-meta') {
        return $value;
    }


    // Fetch all options dynamically if it matches our keys
    $dynamic_keys  = array_merge(
        apply_filters('filter_wp_options_by_field_type', null, 'text'),
        apply_filters('filter_wp_options_by_field_type', null, 'select'),
        apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
        apply_filters('filter_wp_options_by_field_type', null, 'radio'),
        apply_filters('filter_wp_options_by_field_type', null, 'truefalse')
    );

    
    foreach ($dynamic_keys as $group) {
        $option_name = create_underscored_value($group['name']);
        if ($source_args['key'] === $option_name) {
            $fetched_option = get_option("choices_" . $option_name);

            // Iterate over options to find a label matching the value
            if (isset($fetched_option['options'])) {
                foreach ($fetched_option['options'] as $option) {
                    if ($option['value'] === $value) {
                        return $option['label'];
                    }
                }
            }
        }
    }

   return $value;
}
add_filter('block_bindings_source_value', 'filter_dynamic_meta_value', 10, 5);









add_action('wp_ajax_delete_wp_option', 'ace_delete_wp_option');

function ace_delete_wp_option() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }

    // Check if the option name is set
    if (isset($_POST['option_name'])) {
        $group_name = sanitize_text_field($_POST['option_name']);
        $option_name = create_underscored_value($group_name); // Use 'group_name' from the request

        // Fetch all groups (dropdowns and true/false) using the filter
        $all_groups = array_merge(
            apply_filters('filter_wp_options_by_field_type', null, 'text'),
            apply_filters('filter_wp_options_by_field_type', null, 'select'),
            apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
            apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
            apply_filters('filter_wp_options_by_field_type', null, 'radio'),
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
            wp_send_json_success('Option deleted successfully');
        } else {
            wp_send_json_error(print_r($all_groups, false));
        }
    } else {
        wp_send_json_error('Invalid option name');
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
                                    echo 'Fields <ul>';
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

            error_log('Deserialized Fields ' . print_r($choices, true));

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






function ace_register_roles_endpoint() {
    register_rest_route('ace/v1', '/roles', array(
        'methods' => 'GET',
        'callback' => 'ace_get_roles',
        'permission_callback' => function() {
            return current_user_can('manage_options'); // Adjust permissions as needed
        },
    ));
}
add_action('rest_api_init', 'ace_register_roles_endpoint');

function ace_get_roles() {
    // Fetch roles from WordPress
    global $wp_roles;

    if (!isset($wp_roles)) {
        $wp_roles = new WP_Roles();
    }

    $roles = [];
    foreach ($wp_roles->roles as $role_slug => $role_data) {
        $roles[] = [
            'slug' => $role_slug,
            'name' => $role_data['name'],
        ];
    }

    return new WP_REST_Response($roles, 200);
}






function ace_field_options_enqueue_settings_page_scripts() {
    $screen = get_current_screen();

    // Check if we are on the settings page for this plugin
    if ($screen->id === 'toplevel_page_adaptive-content-enhancer') {
        wp_enqueue_script(
            'acemedia-settings-react',
            plugins_url('/assets/js/index.js', __FILE__),
            array('wp-element', 'wp-components', 'wp-api-fetch', 'jquery', 'wp-data', 'wp-editor'),
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/index.js'),
            true
        );

        wp_localize_script('acemedia-settings-react', 'aceAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ace_nonce') // Create a nonce for security
        ));

        wp_enqueue_style('wp-block-editor', includes_url('css/dist/block-editor.css'));
    }
}
add_action('admin_enqueue_scripts', 'ace_field_options_enqueue_settings_page_scripts');




function adaptive_content_enhancer_choices_page_react() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <div id="ace-settings-root"></div>
    </div>
    <?php
}




add_action('rest_api_init', function() {
    register_rest_route('ace/v1', '/get-choices', array(
        'methods' => 'GET',
        'callback' => 'ace_get_choices',
        'permission_callback' => '__return_true', // Allow public access
    ));

    // Add endpoint for deleting a choice or true/false field
    register_rest_route('ace/v1', '/delete-option', array(
        'methods' => 'POST',
        'callback' => 'ace_delete_option',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ));

    // Add endpoint for adding/updating a choice or true/false field
    register_rest_route('ace/v1', '/save-options', array(
        'methods' => 'POST',
        'callback' => 'ace_save_options',
        'permission_callback' => function () {
            return current_user_can('manage_options'); // Ensure the user has permission
        }
    ));
});

function ace_delete_option(WP_REST_Request $request) {
    // Verify the nonce
    $nonce = $request->get_param('_wpnonce');
    if (!wp_verify_nonce($nonce, 'ace_nonce')) {
        return new WP_REST_Response(array('error' => 'Invalid nonce'), 403);
    }

    $option_name = $request->get_param('option_name');

    if (!$option_name) {
        return new WP_REST_Response(array('error' => 'Invalid option name'), 400);
    }

    if (delete_option($option_name)) {
        return new WP_REST_Response(array('success' => 'Option deleted successfully'), 200);
    } else {
        return new WP_REST_Response(array('error' => 'Failed to delete option'), 500);
    }
}
function ace_save_options(WP_REST_Request $request) {
    $choices = $request->get_param('choices');

    if (!is_array($choices)) {
        return new WP_REST_Response(array('error' => 'Missing or invalid data'), 400);
    }

    $updated_choice_names = []; // Track updated choice names for returning to the client

    foreach ($choices as $choice) {
        if (!isset($choice['name']) || !isset($choice['title']) || !isset($choice['field_type'])) {
            continue;
        }

        $option_name = 'choices_' . create_underscored_value($choice['name']);
        $existing_choice = get_option($option_name);

        $group_data = array(
            'name' => sanitize_text_field($choice['name']),
            'title' => sanitize_text_field($choice['title']),
            'description' => sanitize_textarea_field($choice['description'] ?? ''),
            'field_type' => sanitize_text_field($choice['field_type']),
            'show_in_rest' => !empty($choice['show_in_rest']) ? 1 : 0,
            'created' => $existing_choice['created'] ?? current_time('mysql'), // Keep the original created time
            'edited' => current_time('mysql'), // Always update edited timestamp
        );

        // Handle 'options' to ensure it's an array even if empty
        $group_data['options'] = isset($choice['options']) && is_array($choice['options']) ? array_map(function ($option) {
            return array(
                'label' => sanitize_text_field($option['label']),
                'value' => sanitize_text_field($option['value']),
            );
        }, $choice['options']) : [];

        // Process tags
        if (isset($choice['tags']) && is_array($choice['tags'])) {
            $group_data['tags'] = array_map('intval', $choice['tags']);
        } else {
            $group_data['tags'] = [];
        }

        // Process associated roles
        if (isset($choice['associated_roles']) && is_array($choice['associated_roles'])) {
            $group_data['associated_roles'] = array_map('sanitize_text_field', $choice['associated_roles']);
        } else {
            $group_data['associated_roles'] = [];
        }

        // Process post types
        if (isset($choice['post_types']) && is_array($choice['post_types'])) {
            $group_data['post_types'] = array_map('sanitize_text_field', $choice['post_types']);
        } else {
            $group_data['post_types'] = [];
        }

        update_option($option_name, $group_data);
        
        // Add to updated choice names list
        $updated_choice_names[] = $choice['name'];
    }

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Fields saved successfully',
        'updated_choices' => $updated_choice_names, // Return the updated names
    ), 200);
}

function ace_get_choices(WP_REST_Request $request) {
    $page = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;
    $sort_by = $request->get_param('sort') ?: 'edited';
    $field_type = $request->get_param('field_type');
    $tags = $request->get_param('tags');
    $search_query = $request->get_param('search_query');

    $offset = ($page - 1) * $per_page;

    // Fetch all choices to determine total count before any filtering
    $all_choices = array_merge(
        apply_filters('filter_wp_options_by_field_type', null, 'text'),
        apply_filters('filter_wp_options_by_field_type', null, 'select'),
        apply_filters('filter_wp_options_by_field_type', null, 'checkbox'),
        apply_filters('filter_wp_options_by_field_type', null, 'truefalse'),
        apply_filters('filter_wp_options_by_field_type', null, 'radio')
    );
    $total_choices = count($all_choices);

    // Apply filters if specified
    $choices = $all_choices;
    if ($field_type) {
        $field_types = explode(',', $field_type);
        $choices = array_filter($choices, function($choice) use ($field_types) {
            return in_array($choice['field_type'], $field_types);
        });
    }

    if ($tags) {
        $tag_list = explode(',', $tags);
        $choices = array_filter($choices, function($choice) use ($tag_list) {
            return isset($choice['tags']) && array_intersect($choice['tags'], $tag_list);
        });
    }

    if ($search_query && strlen($search_query) >= 3) {
        $choices = array_filter($choices, function($choice) use ($search_query) {
            $search_query = strtolower($search_query);
            return strpos(strtolower($choice['name']), $search_query) !== false ||
                   strpos(strtolower($choice['field_type']), $search_query) !== false ||
                   (isset($choice['description']) && strpos(strtolower($choice['description']), $search_query) !== false);
        });
    }

    // Sort and paginate the filtered choices
    usort($choices, function($a, $b) use ($sort_by) {
        switch ($sort_by) {
            case 'created':
                return strtotime($b['created']) - strtotime($a['created']);
            case 'edited':
                return strtotime($b['edited']) - strtotime($a['edited']);
            case 'title':
                return strcmp($a['title'], $b['title']);
            case 'field_type':
                return strcmp($a['field_type'], $b['field_type']);
            default:
                return 0;
        }
    });

    $paginated_choices = array_slice($choices, $offset, $per_page);
    $total_pages = ceil(count($choices) / $per_page);

    // Fetch all tags and include them in the response
    $tags = get_terms(array(
        'taxonomy' => 'field_tags',
        'hide_empty' => false
    ));
    
    $tags_with_descriptions = [];
    foreach ($tags as $tag) {
        $tags_with_descriptions[$tag->term_id] = [
            'name' => $tag->name,
            'description' => $tag->description,
            'meta' => get_term_meta($tag->term_id)
        ];
    }
    
    $response_data = [
        'choices' => array_map(function($choice) use ($tags_with_descriptions) {
            $choice['tags_with_descriptions'] = [];
            if (isset($choice['tags'])) {
                foreach ($choice['tags'] as $tag_id) {
                    if (isset($tags_with_descriptions[$tag_id])) {
                        $choice['tags_with_descriptions'][] = [
                            'id' => $tag_id,
                            'name' => $tags_with_descriptions[$tag_id]['name'],
                            'description' => $tags_with_descriptions[$tag_id]['description']
                        ];
                    }
                }
            }
            return $choice;
        }, $paginated_choices),
        'totalPages' => $total_pages,
        'totalChoices' => $total_choices,
        'tags' => $tags_with_descriptions
    ];

    return new WP_REST_Response($response_data, 200);
}



add_action('rest_api_init', function () {
    register_rest_route('ace/v1', '/get-choices', array(
        'methods' => 'GET',
        'callback' => 'ace_get_choices',
        'permission_callback' => '__return_true', // Adjust permissions as needed
    ));
});


function register_field_tags_taxonomy() {
    register_taxonomy(
        'field_tags',
        null, // No specific object type; these can be added programmatically
        array(
            'label' => __( 'Field Tags' ),
            'public' => true, // Not publicly queryable, but can be used internally
            'show_in_rest' => true, // Enable REST API access
            'hierarchical' => false,
            'rewrite' => array( 'slug' => 'field-tag' ),
        )
    );
}
add_action('init', 'register_field_tags_taxonomy');

function register_field_tags_meta() {
    register_meta('term', 'color', array(
        'type' => 'string',
        'single' => true,
        'sanitize_callback' => 'sanitize_hex_color',
        'show_in_rest' => true,
    ));
    
    register_meta('term', 'associated_roles', array(
        'type' => 'array',
        'single' => true,
        'show_in_rest' => array(
            'schema' => array(
                'type' => 'array',
                'items' => array('type' => 'string')
            )
        ),
    ));

    register_meta('term', 'post_types', array(
        'type' => 'array',
        'single' => true,
        'show_in_rest' => array(
            'schema' => array(
                'type' => 'array',
                'items' => array('type' => 'string')
            )
        ),
    ));
}
add_action('init', 'register_field_tags_meta');

// Meta box for Color, Associated Roles, and Post Types
function add_field_tags_meta_box($term) {
    $color = get_term_meta($term->term_id, 'color', true);
    $associated_roles = get_term_meta($term->term_id, 'associated_roles', true);
    $post_types = get_term_meta($term->term_id, 'post_types', true);
    ?>
    <tr class="form-field">
        <th scope="row" valign="top">
            <label for="field_tags_color"><?php _e('Color', 'textdomain'); ?></label>
        </th>
        <td>
            <input type="text" name="field_tags_color" id="field_tags_color" value="<?php echo esc_attr($color); ?>" class="color-field" />
            <p class="description"><?php _e('Select a color for this field tag.', 'textdomain'); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row" valign="top">
            <label for="associated_roles"><?php _e('Associated Roles', 'textdomain'); ?></label>
        </th>
        <td>
            <input type="text" name="associated_roles" id="associated_roles" value="<?php echo esc_attr(implode(', ', (array) $associated_roles)); ?>" />
            <p class="description"><?php _e('Comma-separated list of associated roles.', 'textdomain'); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row" valign="top">
            <label for="post_types"><?php _e('Post Types', 'textdomain'); ?></label>
        </th>
        <td>
            <input type="text" name="post_types" id="post_types" value="<?php echo esc_attr(implode(', ', (array) $post_types)); ?>" />
            <p class="description"><?php _e('Comma-separated list of associated post types.', 'textdomain'); ?></p>
        </td>
    </tr>
    <?php
}
add_action('field_tags_edit_form_fields', 'add_field_tags_meta_box');

// Save function for Color, Associated Roles, and Post Types
function save_field_tags_meta($term_id) {
    // Save color
    if (isset($_POST['field_tags_color'])) {
        $color = sanitize_hex_color($_POST['field_tags_color']);
        update_term_meta($term_id, 'color', $color);
    }

    // Save associated roles
    if (isset($_POST['associated_roles'])) {
        $roles = array_map('sanitize_text_field', explode(',', $_POST['associated_roles']));
        update_term_meta($term_id, 'associated_roles', $roles);
    }

    // Save post types
    if (isset($_POST['post_types'])) {
        $post_types = array_map('sanitize_text_field', explode(',', $_POST['post_types']));
        update_term_meta($term_id, 'post_types', $post_types);
    }
}
add_action('edited_field_tags', 'save_field_tags_meta', 10, 2);
add_action('create_field_tags', 'save_field_tags_meta', 10, 2);

// Enqueue color picker in the admin area
function enqueue_color_picker($hook_suffix) {
    if ($hook_suffix === 'edit-tags.php' || $hook_suffix === 'term.php') {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', 'jQuery(document).ready(function($){$(".color-field").wpColorPicker();});');
    }
}
add_action('admin_enqueue_scripts', 'enqueue_color_picker');


?>
