<?php
/**
 * Plugin Name: Adaptive Content Enhancer (legacy entry)
 * Description: Compatibility entry point for sites still activated through the original plugin file.
 * Version: 0.500.0
 * Author: Shane Rounce of Ace Media
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: adaptive-content-enhancer
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Keep this historical entry point active while the plugin is migrated to the
// namespaced bootstrap. Existing sites store this filename in active_plugins.
if ( ! defined( 'ACE_ACE_LOADER_FILE' ) ) {
    define( 'ACE_ACE_LOADER_FILE', __FILE__ );
}
require_once __DIR__ . '/adaptive-content-enhancer.php';

// Register the admin menu page for managing Option Groups.
add_action( 'admin_menu', 'ace_field_options_admin_menu' );

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
    


}



function ace_field_options_enqueue_editor_styles() {
    wp_enqueue_style(
        'acemedia-editor', // Handle
        plugins_url('/assets/styles/editor.css', __FILE__), // Path to the CSS file
        array(), // Dependencies (if any)
        filemtime(plugin_dir_path(__FILE__) . 'assets/styles/editor.css') // Version number
    );
}
add_action('enqueue_block_editor_assets', 'ace_field_options_enqueue_editor_styles');


function create_underscored_value($string) {
    $numberWords = [
        '0' => 'zero',
        '1' => 'one',
        '2' => 'two',
        '3' => 'three',
        '4' => 'four',
        '5' => 'five',
        '6' => 'six',
        '7' => 'seven',
        '8' => 'eight',
        '9' => 'nine'
    ];

    $string = preg_replace_callback('/\d/', function($matches) use ($numberWords) {
        return $numberWords[$matches[0]];
    }, $string);

    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9_ ]+/', '', $string);
    $string = preg_replace('/\s+/', '_', trim($string));

    return $string;
}

// Load all options and filter based on custom logic

// Define a cache key for all options

function get_filtered_choices_options($cache_key) {
    $cache_key = is_string($cache_key) && $cache_key !== '' ? $cache_key : 'ace_all_options_cache';
    // Attempt to load options from the cache
    $all_options = wp_cache_get($cache_key);

    // Check if options are not cached
    if ($all_options === false) {
        // Load all options into memory
        $all_options = wp_load_alloptions();

        // Initialize an array to hold the filtered options
        $filtered_options = [];

        // Check if all options were loaded successfully
        if ($all_options !== false) {
            // Loop through the options and filter by prefix
            foreach ($all_options as $option_name => $option_value) {
                if (strpos($option_name, 'choices_') === 0) { // Check if the option name starts with 'choices_'
                    $filtered_options[$option_name] = maybe_unserialize($option_value); // Unserialize if needed
                }
            }

            // Cache the filtered results for future use
            wp_cache_set($cache_key, $filtered_options);
        } else {
            // Handle the error case if necessary
            return []; // Return an empty array if loading options failed
        }
    } else {
        // Use cached options if available
        $filtered_options = $all_options;
    }

    return $filtered_options;
}

function ace_get_choice_groups() {
    global $cache_key;

    return get_filtered_choices_options($cache_key);
}

function ace_get_form_field_name($attributes) {
    if (!empty($attributes['selectedMetaKey'])) {
        return create_underscored_value($attributes['selectedMetaKey']);
    }

    if (!empty($attributes['selectedGroup'])) {
        return create_underscored_value($attributes['selectedGroup']);
    }

    return '';
}

function ace_get_form_field_label($attributes, $fallback = '') {
    if (!empty($attributes['label'])) {
        return sanitize_text_field($attributes['label']);
    }

    if (!empty($attributes['title'])) {
        return sanitize_text_field($attributes['title']);
    }

    return sanitize_text_field($fallback);
}

function ace_get_form_field_help_text($attributes, $group_data = []) {
    if (!empty($attributes['helpText'])) {
        return sanitize_textarea_field($attributes['helpText']);
    }

    if (!empty($group_data['description']) && empty($attributes['placeholder'])) {
        return sanitize_textarea_field($group_data['description']);
    }

    return '';
}

function ace_get_form_field_placeholder($attributes, $fallback = '') {
    if (!empty($attributes['placeholder'])) {
        return sanitize_text_field($attributes['placeholder']);
    }

    return sanitize_text_field($fallback);
}

function ace_get_form_input_type($attributes) {
    $allowed_types = ['text', 'email', 'tel', 'url', 'number', 'textarea'];
    $input_type = sanitize_key($attributes['inputType'] ?? 'text');

    return in_array($input_type, $allowed_types, true) ? $input_type : 'text';
}

function ace_get_form_field_constraint_attributes($attributes) {
    $html_attributes = [];

    if (!empty($attributes['isRequired'])) {
        $html_attributes['required'] = 'required';
        $html_attributes['aria-required'] = 'true';
    }

    if (!empty($attributes['autocomplete'])) {
        $html_attributes['autocomplete'] = sanitize_text_field($attributes['autocomplete']);
    }

    $min_length = isset($attributes['minLength']) ? (int) $attributes['minLength'] : 0;
    if ($min_length > 0) {
        $html_attributes['minlength'] = (string) $min_length;
    }

    $max_length = isset($attributes['maxLength']) ? (int) $attributes['maxLength'] : 0;
    if ($max_length > 0) {
        $html_attributes['maxlength'] = (string) $max_length;
    }

    return $html_attributes;
}

function ace_build_html_attributes($attributes) {
    $serialized = [];

    foreach ($attributes as $attribute_name => $attribute_value) {
        if ($attribute_value === '' || $attribute_value === null || $attribute_value === false) {
            continue;
        }

        $serialized[] = sprintf(
            '%s="%s"',
            esc_attr($attribute_name),
            esc_attr((string) $attribute_value)
        );
    }

    return implode(' ', $serialized);
}

function ace_format_form_value_for_email($value) {
    if (is_array($value)) {
        return implode(', ', array_map('ace_format_form_value_for_email', $value));
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    return (string) $value;
}

function ace_find_reply_to_email($fields) {
    foreach ($fields as $value) {
        $values = is_array($value) ? $value : [$value];

        foreach ($values as $candidate) {
            $candidate = sanitize_email($candidate);

            if ($candidate && is_email($candidate)) {
                return $candidate;
            }
        }
    }

    return '';
}

function ace_build_form_email_message($config, $fields, $page_url = '') {
    $template = isset($config['emailTemplate']) ? (string) $config['emailTemplate'] : '';

    if ($template !== '') {
        foreach ($fields as $field_name => $value) {
            $template = str_replace(
                '{' . $field_name . '}',
                ace_format_form_value_for_email($value),
                $template
            );
        }

        if ($page_url !== '') {
            $template .= "\n\nSubmitted from: " . esc_url_raw($page_url);
        }

        return $template;
    }

    $lines = [];

    foreach ($fields as $field_name => $value) {
        $label = ucwords(str_replace('_', ' ', $field_name));
        $lines[] = sprintf('%s: %s', $label, ace_format_form_value_for_email($value));
    }

    if ($page_url !== '') {
        $lines[] = 'Submitted from: ' . esc_url_raw($page_url);
    }

    return implode("\n\n", $lines);
}

function ace_normalize_form_submission($fields) {
    $normalized = [];

    if (!is_array($fields)) {
        return $normalized;
    }

    foreach ($fields as $field_name => $value) {
        $sanitized_key = create_underscored_value((string) $field_name);

        if ($sanitized_key === '') {
            continue;
        }

        if (is_array($value)) {
            $normalized[$sanitized_key] = array_values(array_filter(array_map(function($item) {
                return sanitize_textarea_field((string) $item);
            }, $value), function($item) {
                return $item !== '';
            }));
            continue;
        }

        $normalized[$sanitized_key] = sanitize_textarea_field((string) $value);
    }

    return array_filter($normalized, function($value) {
        return $value !== '' && $value !== [];
    });
}

function ace_sign_contact_form_config($payload) {
    return wp_hash($payload);
}

function ace_is_woocommerce_available() {
    return class_exists('WooCommerce') && function_exists('wc_get_product');
}

function ace_get_product_context_data($product_id = 0) {
    if (!ace_is_woocommerce_available()) {
        return null;
    }

    if (!$product_id) {
        $product_id = get_the_ID();
    }

    if (!$product_id) {
        return null;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return null;
    }

    return [
        'id' => $product->get_id(),
        'name' => $product->get_name(),
        'sku' => $product->get_sku(),
        'price' => wp_strip_all_tags($product->get_price_html()),
        'permalink' => get_permalink($product->get_id()),
    ];
}

function ace_get_latest_product_context_sample() {
    if (!ace_is_woocommerce_available()) {
        return null;
    }

    $products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => 1,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
    ]);

    if (empty($products)) {
        return null;
    }

    return ace_get_product_context_data((int) $products[0]);
}

function ace_enqueue_contact_form_frontend_assets() {
    static $assets_enqueued = false;

    if ($assets_enqueued || is_admin()) {
        return;
    }

    $dir = plugin_dir_path(__FILE__);
    $url = plugin_dir_url(__FILE__);
    $script_handle = 'ace-contact-form-frontend';
    $script_asset = "{$dir}assets/js/form-submit.asset.php";
    $script_path = "{$dir}assets/js/form-submit.js";

    if (file_exists($script_asset)) {
        $asset = include $script_asset;

        wp_enqueue_script(
            $script_handle,
            "{$url}assets/js/form-submit.js",
            $asset['dependencies'],
            $asset['version'],
            true
        );
    } elseif (file_exists($script_path)) {
        wp_enqueue_script(
            $script_handle,
            "{$url}assets/js/form-submit.js",
            [],
            filemtime($script_path),
            true
        );
    }

    wp_localize_script($script_handle, 'aceContactForm', [
        'restUrl' => esc_url_raw(rest_url('ace/v1/submit-form')),
    ]);

    $style_path = "{$dir}assets/styles/form.css";
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'ace-contact-form',
            "{$url}assets/styles/form.css",
            [],
            filemtime($style_path)
        );
    }

    $assets_enqueued = true;
}

function ace_render_form_block($attributes, $content) {
    ace_enqueue_contact_form_frontend_assets();

    $recipient = sanitize_email($attributes['recipient'] ?? '');
    $subject = sanitize_text_field($attributes['subject'] ?? '');
    $success_message = sanitize_text_field($attributes['successMessage'] ?? 'Thank you for your message!');
    $error_message = sanitize_text_field($attributes['errorMessage'] ?? 'There was an error sending your message.');
    $form_id = sanitize_html_class($attributes['formId'] ?? '');
    $email_template = sanitize_textarea_field($attributes['emailTemplate'] ?? '');
    $field_styles = is_array($attributes['fieldStyles'] ?? null) ? $attributes['fieldStyles'] : [];
    $use_floating_placeholders = !empty($field_styles['useFloatingPlaceholders']);
    $show_required_indicator = !array_key_exists('showRequiredIndicator', $field_styles) || !empty($field_styles['showRequiredIndicator']);

    $config = [
        'formId' => $form_id,
        'recipient' => $recipient,
        'subject' => $subject,
        'successMessage' => $success_message,
        'errorMessage' => $error_message,
        'emailTemplate' => $email_template,
    ];

    $config_payload = base64_encode(wp_json_encode($config));
    $config_signature = ace_sign_contact_form_config($config_payload);
    $style_map = [
        '--ace-form-field-gap' => $field_styles['fieldGap'] ?? '',
        '--ace-form-label-gap' => $field_styles['labelGap'] ?? '',
        '--ace-form-input-padding' => $field_styles['inputPadding'] ?? '',
        '--ace-form-input-radius' => $field_styles['inputBorderRadius'] ?? '',
        '--ace-form-input-border-width' => $field_styles['inputBorderWidth'] ?? '',
        '--ace-form-input-border-color' => $field_styles['inputBorderColor'] ?? '',
        '--ace-form-input-background' => $field_styles['inputBackgroundColor'] ?? '',
        '--ace-form-input-color' => $field_styles['inputTextColor'] ?? '',
        '--ace-form-placeholder-color' => $field_styles['placeholderColor'] ?? '',
        '--ace-form-focus-border-color' => $field_styles['focusBorderColor'] ?? '',
        '--ace-form-focus-background' => $field_styles['focusBackgroundColor'] ?? '',
    ];
    $style_declarations = [];
    foreach ($style_map as $property => $value) {
        $value = sanitize_text_field((string) $value);
        if ($value !== '') {
            $style_declarations[] = $property . ': ' . $value;
        }
    }

    $wrapper_attributes = get_block_wrapper_attributes([
        'class' => trim(
            'ace-form-container' .
            ($use_floating_placeholders ? ' ace-form-container--floating-placeholders' : '') .
            ($show_required_indicator ? ' ace-form-container--show-required' : '')
        ),
        'style' => implode('; ', $style_declarations),
    ]);

    return sprintf(
        '<form %1$s method="post" data-ace-form="true" data-success-message="%2$s" data-error-message="%3$s">
            <input type="hidden" name="ace_form_nonce" value="%4$s" />
            <input type="hidden" name="ace_form_config" value="%5$s" />
            <input type="hidden" name="ace_form_signature" value="%6$s" />
            <input type="text" class="ace-form-honeypot" name="ace_hp" value="" tabindex="-1" autocomplete="off" />
            %7$s
            <div class="ace-form-status" data-ace-form-status hidden aria-live="polite"></div>
        </form>',
        $wrapper_attributes,
        esc_attr($success_message),
        esc_attr($error_message),
        esc_attr(wp_create_nonce('ace_submit_form')),
        esc_attr($config_payload),
        esc_attr($config_signature),
        $content
    );
}

function ace_register_form_block() {
    register_block_type('adaptive-content-enhancer/form', [
        'render_callback' => 'ace_render_form_block',
        'attributes' => [
            'formId' => ['type' => 'string', 'default' => ''],
            'recipient' => ['type' => 'string', 'default' => ''],
            'subject' => ['type' => 'string', 'default' => ''],
            'successMessage' => ['type' => 'string', 'default' => 'Thank you for your message!'],
            'errorMessage' => ['type' => 'string', 'default' => 'There was an error sending your message.'],
            'enableRecaptcha' => ['type' => 'boolean', 'default' => false],
            'enableFileUpload' => ['type' => 'boolean', 'default' => false],
            'maxFileSize' => ['type' => 'number', 'default' => 2],
            'allowedFileTypes' => ['type' => 'array', 'default' => ['pdf', 'doc', 'docx']],
            'emailTemplate' => ['type' => 'string', 'default' => ''],
            'customClass' => ['type' => 'string', 'default' => ''],
            'formAction' => ['type' => 'string', 'default' => 'email'],
            'fieldStyles' => [
                'type' => 'object',
                'default' => [
                    'inputPadding' => '0.875rem 1rem',
                    'fieldGap' => '1rem',
                    'labelGap' => '0.5rem',
                    'inputBorderRadius' => '0.5rem',
                    'inputBorderWidth' => '1px',
                    'inputBorderColor' => '#cfd4dc',
                    'inputBackgroundColor' => '#ffffff',
                    'inputTextColor' => '#1e1e1e',
                    'placeholderColor' => '#6b7280',
                    'focusBorderColor' => '#1d4ed8',
                    'focusBackgroundColor' => '#ffffff',
                    'useFloatingPlaceholders' => false,
                    'showRequiredIndicator' => true,
                ],
            ],
            'smtpSettings' => [
                'type' => 'object',
                'default' => [
                    'host' => '',
                    'port' => '',
                    'username' => '',
                    'password' => '',
                    'encryption' => 'tls',
                ],
            ],
            'validation' => [
                'type' => 'object',
                'default' => [
                    'requireValidation' => true,
                    'validateEmail' => true,
                    'validatePhone' => false,
                ],
            ],
        ],
        'supports' => [
            'html' => false,
            'align' => true,
            'customClassName' => true,
            'spacing' => [
                'blockGap' => true,
                'padding' => true,
                'margin' => true,
            ],
            'border' => [
                'color' => true,
                'radius' => true,
                'style' => true,
                'width' => true,
            ],
            'color' => [
                'text' => true,
                'background' => true,
                'gradients' => true,
                'link' => true,
            ],
            'typography' => [
                'fontSize' => true,
                'lineHeight' => true,
                'textAlign' => true,
            ],
            'layout' => true,
            'dimensions' => [
                'minHeight' => true,
            ],
            'shadow' => true,
        ],
    ]);
}
// The block-native form is registered from build/blocks by Plugin::register_foundation().

function ace_render_product_context_block() {
    if (!ace_is_woocommerce_available() || !is_singular('product')) {
        return '';
    }

    $product_context = ace_get_product_context_data();
    if (!$product_context) {
        return '';
    }

    $fields = [
        'product_id' => $product_context['id'],
        'product_name' => $product_context['name'],
        'product_sku' => $product_context['sku'],
        'product_price' => $product_context['price'],
        'product_permalink' => $product_context['permalink'],
    ];

    $output = '';
    foreach ($fields as $field_name => $field_value) {
        $output .= sprintf(
            '<input type="hidden" name="%1$s" value="%2$s" readonly="readonly" aria-hidden="true" />',
            esc_attr($field_name),
            esc_attr((string) $field_value)
        );
    }

    return $output;
}

function ace_register_product_context_block() {
    if (!ace_is_woocommerce_available()) {
        return;
    }

    register_block_type('adaptive-content-enhancer/product-context', [
        'render_callback' => 'ace_render_product_context_block',
        'attributes' => [],
        'supports' => [
            'html' => false,
            'multiple' => false,
            'reusable' => false,
            'customClassName' => false,
        ],
        'parent' => ['adaptive-content-enhancer/form'],
    ]);
}
add_action('init', 'ace_register_product_context_block');


$cache_key = 'ace_all_options_cache';
$all_options = get_filtered_choices_options($cache_key);


   // $current_user_roles = wp_get_current_user()->roles; // Get the current user roles
add_filter('filter_wp_options_by_field_type', 'filter_options_by_field_type', 10, 2);
function filter_options_by_field_type($all_options, $type) {
global $cache_key;


    if ($all_options === null) {
        $all_options = get_filtered_choices_options($cache_key);
    }

    $filtered_options = array();
            

    foreach ($all_options as $option_name => $value) {
        $field_data = (array) get_option($option_name, array());

 

        if (!empty($field_data) && isset($field_data['field_type']) && $field_data['field_type'] === $type) {
            // Initialize roles and post types as empty arrays
            $field_data['roles'] = [];
            $field_data['post_types'] = [];

            // Check for tags and fetch associated roles and post types
            if (!empty($field_data['tags'])) {
                foreach ($field_data['tags'] as $tag_id) {
                    $tag_meta = get_term_meta($tag_id);

                    // Unserialize roles if they exist
                    if (isset($tag_meta['associated_roles'][0])) {
                        $associated_roles = maybe_unserialize($tag_meta['associated_roles'][0]);
                        if (is_array($associated_roles)) {
                            $field_data['roles'] = array_unique(array_merge($field_data['roles'], $associated_roles));
                        }
                    }

                  // Fetch post types if they exist
                  if (isset($tag_meta['post_types'][0])) {
                    $post_types = maybe_unserialize($tag_meta['post_types'][0]);
                    if (is_array($post_types)) {
                        $field_data['post_types'] = array_unique(array_merge($field_data['post_types'], $post_types));
                    }
                }
                }
            }


            $is_editor = is_admin() && !wp_doing_ajax();

            if ($is_editor) {


    $current_post_type = get_post_type(); 


   if (!empty($field_data['post_types'])) {
        if(!in_array($current_post_type, $field_data['post_types'])) {
         //   continue; // This break the text blocks showing their correct labels. Remind me to fix it later.
        }
   }

// if (!empty($field_data['roles']) && !array_intersect($current_user_roles, $field_data['roles'])) {
 //   continue; // Skip to the next iteration if the current user does not have permission
//}

            }


            $filtered_options[] = $field_data;

        }
    }
    return $filtered_options;
}



function ace_field_options_enqueue_editor_assets() {
    global $all_options;

    // Fetch dropdown groups after saving dynamically from updated options
    $choices = array_merge(
        apply_filters('filter_wp_options_by_field_type', $all_options, 'text'),
        apply_filters('filter_wp_options_by_field_type', $all_options, 'select'),
        apply_filters('filter_wp_options_by_field_type', $all_options, 'truefalse'),
        apply_filters('filter_wp_options_by_field_type', $all_options, 'checkbox'),
        apply_filters('filter_wp_options_by_field_type', $all_options, 'radio')
    );

    // Editor (backend) script for dropdown block (index.js)
    wp_enqueue_script(
        'acemedia-block-editor',
        plugins_url('/assets/js/options.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/options.js'), // Version number
        true // Load in the footer
    );

    // Localize the dropdown groups for the dropdown block editor script
    wp_localize_script('acemedia-block-editor', 'choiceGroups', $choices);
}
add_action('enqueue_block_editor_assets', 'ace_field_options_enqueue_editor_assets');



// Register post meta fields dynamically based on Option Groups and True/False fields
add_action('init', 'acefields_register_dynamic_meta_and_bindings');

function acefields_register_dynamic_meta_and_bindings() {
    global $all_options;

    // Define a mapping of field types to how they should be treated
    $field_types = [
        'text' => 'string',
        'select' => 'string',
        'truefalse' => 'boolean',
        'checkbox' => 'array',
        'radio' => 'array',
    ];

    // Loop over each field type and register its meta fields and associated block bindings sources
    foreach ($field_types as $type => $meta_type) {
        // Fetch fields using the filter based on their type
        $fields = apply_filters('filter_wp_options_by_field_type', $all_options, $type);

        if (!empty($fields)) {
            foreach ($fields as $field_data) {
                // Create the sanitized meta key from the field name
                $field_name_key = create_underscored_value($field_data['name']);

                // Skip if the field data is null or has no relevant value
                if (is_null($field_data) || empty($field_data['name'])) {
                    continue; // Skip this iteration
                }

                // Determine the sanitize callback based on the field type
                $sanitize_callback = 'acefields_sanitize_serialized_meta';

                // Define schema for array items if field type is checkbox or radio
                $schema = ['type' => $meta_type];

                if ($meta_type === 'array') {
                    $schema['items'] = ['type' => 'string']; // Adjust as needed based on what you expect in the array
                }

                // Register each field as a post meta field
                register_meta(
                    'post',
                    $field_name_key,
                    array(
                        'show_in_rest'      => ['schema' => $schema],
                        'single'            => true,
                        'type'              => $meta_type,
                        'sanitize_callback' => $sanitize_callback,
                        'auth_callback'     => static function ( $allowed, $meta_key, $post_id, $user_id ) {
                            return user_can( (int) $user_id, 'edit_post', (int) $post_id );
                        },
                    )
                );

                
            }
        }
    }
}


function acefields_sanitize_serialized_meta($meta_value, $meta_key, $meta_type) {
    // WordPress serialises array meta itself; return a clean array to REST.
    if (is_array($meta_value)) {
        return array_values(array_map('sanitize_text_field', $meta_value));
    }
    if (is_bool($meta_value)) {
        return $meta_value;
    }
    return sanitize_text_field($meta_value);
}




// Function to retrieve unserialized meta
function acefields_get_unserialized_meta($meta_value) {
    return maybe_unserialize($meta_value);
}




register_block_type('adaptive-content-enhancer/text-field', array(
    'render_callback' => 'ace_field_options_render_block',
    'attributes' => array(
        'selectedMetaKey' => array(
            'type' => 'string',
            'default' => ''
        ),
        'selectedGroup' => array(
            'type' => 'string',
            'default' => ''
        ),
        'content' => array(
            'type' => 'string',
            'default' => ''
        ),
        'title' => array(
            'type' => 'string',
            'default' => ''
        ),
        'description' => array(
            'type' => 'string',
            'default' => ''
        ),
        'label' => array(
            'type' => 'string',
            'default' => ''
        ),
        'placeholder' => array(
            'type' => 'string',
            'default' => ''
        ),
        'helpText' => array(
            'type' => 'string',
            'default' => ''
        ),
        'inputType' => array(
            'type' => 'string',
            'default' => 'text'
        ),
        'autocomplete' => array(
            'type' => 'string',
            'default' => ''
        ),
        'isRequired' => array(
            'type' => 'boolean',
            'default' => false
        ),
        'rows' => array(
            'type' => 'number',
            'default' => 4
        ),
        'minLength' => array(
            'type' => 'number',
            'default' => 0
        ),
        'maxLength' => array(
            'type' => 'number',
            'default' => 0
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
    'isInsideForm' => ['adaptive-content-enhancer/isInsideForm'],
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

function ace_field_options_render_block($attributes, $content, $block) {
    $is_inside_form = !empty($attributes['isInsideForm']);
    $wrapper_attributes = get_block_wrapper_attributes();
    $selected_group = $attributes['selectedGroup'] ?? '';
    $selected_field_type = $attributes['selectedFieldType'] ?? '';
    $show_as_input = $is_inside_form || !empty($attributes['showAsInputOnFrontend']);
    $show_labels = $is_inside_form || !empty($attributes['showLabelsOnFrontend']);
    $selected_meta_key = $attributes['selectedMetaKey'] ?? '';
    $content = $attributes['content'] ?? '';
    $output = '';
    $field_name = ace_get_form_field_name($attributes);
    $field_id = $field_name ? 'ace-field-' . $field_name : wp_unique_id('ace-field-');
    $group_data = [];
    $title = $attributes['title'] ?? '';
    $is_required = !empty($attributes['isRequired']);
    $input_type = ace_get_form_input_type($attributes);

    if (!empty($selected_meta_key)) {
        $option_name = 'choices_' . create_underscored_value($selected_meta_key);
        $group_data = get_option($option_name, []);

        if (!empty($group_data['title'])) {
            $title = $group_data['title'];
        }
    }

    if (!empty($selected_group)) {
        $option_name = 'choices_' . create_underscored_value($selected_group);
        $selected_group_data = get_option($option_name, []);
        if (!empty($selected_group_data) && is_array($selected_group_data)) {
            $group_data = array_merge($selected_group_data, $group_data);
        }
    }

    $field_label = ace_get_form_field_label($attributes, $title ?: $selected_meta_key ?: $selected_group);
    $field_help_text = ace_get_form_field_help_text($attributes, $group_data);
    $placeholder_fallback = $group_data['description'] ?? '';
    if ($placeholder_fallback === '') {
        $placeholder_fallback = 'Enter ' . ($field_label ?: $selected_meta_key ?: $selected_group);
    }
    $field_placeholder = ace_get_form_field_placeholder($attributes, $placeholder_fallback);
    $constraint_attributes = ace_get_form_field_constraint_attributes($attributes);
    $required_class = $is_required ? ' is-required' : '';
    $required_description = $is_required ? '<span class="screen-reader-text">(required)</span>' : '';
    $help_markup = $field_help_text !== '' ? sprintf('<p class="ace-form-field__help">%s</p>', esc_html($field_help_text)) : '';

    if (!empty($selected_meta_key)) {
        if ($is_inside_form) {
            $input_attributes = array_merge($constraint_attributes, [
                'id' => $field_id,
                'name' => $field_name,
                'class' => 'ace-form-field__input ace-form-field__input--' . $input_type,
                'placeholder' => $field_placeholder,
            ]);

            if ($input_type === 'textarea') {
                $input_attributes['rows'] = max(2, (int) ($attributes['rows'] ?? 4));
                $input_markup = sprintf(
                    '<textarea %1$s></textarea>',
                    ace_build_html_attributes($input_attributes)
                );
            } else {
                $input_attributes['type'] = $input_type;
                $input_attributes['value'] = '';
                $input_markup = sprintf(
                    '<input %1$s />',
                    ace_build_html_attributes($input_attributes)
                );
            }

            $output = sprintf(
                '<div class="ace-form-field ace-form-field--text ace-form-field--%1$s">
                    <label class="ace-form-field__label ace-form-field__label--static%2$s" for="%3$s">%4$s%5$s</label>
                    <div class="ace-form-field__control-wrap">
                        %6$s
                        <label class="ace-form-field__label ace-form-field__label--floating%2$s" for="%3$s">%4$s%5$s</label>
                    </div>
                    %7$s
                </div>',
                esc_attr($input_type),
                esc_attr($required_class),
                esc_attr($field_id),
                esc_html($field_label ?: $selected_meta_key),
                $required_description,
                $input_markup,
                $help_markup
            );
        } else {
            $output = sprintf(
                '<p class="acemedia-text-field" data-meta-key="%s">%s</p>',
                esc_attr($selected_meta_key),
                esc_html($content)
            );
        }

        return sprintf('<div %s>%s</div>', $wrapper_attributes, $output);
    }

    $group_options = get_group_options($selected_group, $attributes);
    $dropdown_label = $group_options['label'] ?? '';
    $dropdown_title = $group_options['title'] ?? '';

    if ($show_as_input && $selected_field_type !== 'text') {
        switch ($selected_field_type) {
            case 'truefalse':
                $true_label = $field_label ?: $dropdown_title ?: ($group_options['label'] ?? '');
                $is_checked = in_array($attributes['trueFalseValue'], ['1', true, 'true'], true) ? 'checked' : '';
                $output = sprintf(
                    '<div class="ace-form-field ace-form-field--toggle"><label class="ace-form-field__label ace-form-field__label--toggle%5$s" for="%1$s"><input id="%1$s" type="checkbox" class="acemedia-checkbox" name="%2$s" value="1" %3$s %6$s> %4$s%7$s</label>%8$s</div>',
                    esc_attr($field_id),
                    esc_attr($field_name),
                    $is_checked,
                    esc_html($true_label),
                    esc_attr($required_class),
                    $is_required ? 'required aria-required="true"' : '',
                    $required_description,
                    $help_markup
                );
                break;

            case 'checkbox':
            case 'radio':
                $input_type = ($selected_field_type === 'checkbox') ? 'checkbox' : 'radio';
                $selected_values = $attributes['choiceValue'] ?? [];
                if (!is_array($selected_values)) {
                    $selected_values = [$selected_values];
                }

                if ($show_labels && ($field_label || $dropdown_title)) {
                    $output .= sprintf(
                        '<fieldset class="ace-form-field ace-form-field--%1$s"><legend class="ace-form-field__label%2$s">%3$s%4$s</legend>',
                        esc_attr($selected_field_type),
                        esc_attr($required_class),
                        esc_html($field_label ?: $dropdown_title),
                        $required_description
                    );
                } else {
                    $output .= sprintf('<fieldset class="ace-form-field ace-form-field--%1$s">', esc_attr($selected_field_type));
                }

                foreach ($group_options['options'] as $index => $option) {
                    $is_selected = in_array((string) $option['value'], array_map('strval', $selected_values), true) ? 'checked' : '';
                    $output .= sprintf(
                        '<label><input type="%1$s" name="%2$s" value="%3$s" class="acemedia-%1$s" %4$s %6$s> %5$s</label>',
                        esc_attr($input_type),
                        esc_attr($field_name),
                        esc_attr($option['value']),
                        $is_selected,
                        esc_html($option['label']),
                        ($is_required && $index === 0) ? 'required aria-required="true"' : ''
                    );
                }
                $output .= $help_markup . '</fieldset>';
                break;

            default:
                $select_attributes = [
                    'id' => $field_id,
                    'name' => $field_name,
                    'class' => 'acemedia-input ace-form-field__input ace-form-field__input--select',
                ];
                if ($is_required) {
                    $select_attributes['required'] = 'required';
                    $select_attributes['aria-required'] = 'true';
                }

                if ($show_labels) {
                    $output .= sprintf(
                        '<label class="ace-form-field ace-form-field--select ace-form-field__label-wrapper" for="%1$s"><span class="ace-form-field__label%3$s">%2$s%4$s</span>',
                        esc_attr($field_id),
                        esc_html($field_label ?: $dropdown_title),
                        esc_attr($required_class),
                        $required_description
                    );
                }

                $select = sprintf('<select %1$s>', ace_build_html_attributes($select_attributes));
                if ($field_placeholder !== '') {
                    $select .= sprintf(
                        '<option value="" %1$s>%2$s</option>',
                        $is_required ? 'disabled selected' : '',
                        esc_html($field_placeholder)
                    );
                }
                foreach ($group_options['options'] as $option) {
                    $is_selected = $attributes['choiceValue'] === $option['value'] ? 'selected' : '';
                    $select .= sprintf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($option['value']),
                        $is_selected,
                        esc_html($option['label'])
                    );
                }
                $select .= '</select>';
                $output .= $select;

                if ($show_labels) {
                    $output .= $help_markup . '</label>';
                } else {
                    $output .= $help_markup;
                }
                break;
        }
    } else {
        if ($selected_field_type === 'truefalse') {
            $dropdown_label = $group_options['label'] ?? '';
        } elseif ($selected_field_type === 'text') {
            $dropdown_label = get_post_meta(get_the_ID(), $selected_group, true);
        }

        $output = sprintf('<p>%s</p>', esc_html($dropdown_label));
    }

    return sprintf('<div %s>%s</div>', $wrapper_attributes, $output);
}




add_action('enqueue_block_editor_assets', 'ace_enqueue_text_block');

function ace_enqueue_text_block() {
    $dir = plugin_dir_path(__FILE__);
    $url = plugin_dir_url(__FILE__);

    if (file_exists("{$dir}/assets/js/text.asset.php")) {
        $asset = include "{$dir}/assets/js/text.asset.php";

        wp_enqueue_script(
            'ace-text-block',
            "{$url}/assets/js/text.js",
            $asset['dependencies'],
            $asset['version'],
            true
        );
    } else {
        wp_enqueue_script(
            'ace-text-block',
            "{$url}/assets/js/text.js",
            ['wp-blocks', 'wp-element', 'wp-editor'],
            filemtime("{$dir}/assets/js/text.js"),
            true
        );
    }

    wp_set_script_translations('ace-text-block', 'ace');
}


// The old editor script is retained for compatibility reference, but the new form block owns this name.

function ace_enqueue_form_block() {
    $dir = plugin_dir_path(__FILE__);
    $url = plugin_dir_url(__FILE__);

    if (file_exists("{$dir}/assets/js/form.asset.php")) {
        $asset = include "{$dir}/assets/js/form.asset.php";

        wp_enqueue_script(
            'ace-form-block',
            "{$url}/assets/js/form.js",
            $asset['dependencies'],
            $asset['version'],
            true
        );
    } else {
        wp_enqueue_script(
            'ace-form-block',
            "{$url}/assets/js/form.js",
            ['wp-blocks', 'wp-element', 'wp-editor'],
            filemtime("{$dir}/assets/js/form.js"),
            true
        );
    }

    wp_set_script_translations('ace-form-block', 'ace');
}

add_action('enqueue_block_editor_assets', 'ace_enqueue_product_context_block');

function ace_enqueue_product_context_block() {
    if (!ace_is_woocommerce_available()) {
        return;
    }

    $dir = plugin_dir_path(__FILE__);
    $url = plugin_dir_url(__FILE__);

    if (!file_exists("{$dir}/assets/js/product-context.js")) {
        return;
    }

    if (file_exists("{$dir}/assets/js/product-context.asset.php")) {
        $asset = include "{$dir}/assets/js/product-context.asset.php";

        wp_enqueue_script(
            'ace-product-context-block',
            "{$url}/assets/js/product-context.js",
            $asset['dependencies'],
            $asset['version'],
            true
        );
    } else {
        wp_enqueue_script(
            'ace-product-context-block',
            "{$url}/assets/js/product-context.js",
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],
            filemtime("{$dir}/assets/js/product-context.js"),
            true
        );
    }

    wp_localize_script('ace-product-context-block', 'aceWooProductContext', [
        'isAvailable' => true,
        'sampleProduct' => ace_get_latest_product_context_sample(),
    ]);
}




function get_group_options($selected_group, $attributes) {
    global $all_options;

    $selected_group = create_underscored_value($selected_group);
    $dropdown_value_ref = $attributes['choiceValue'] ?? '';

    $dropdown_groups = array_merge(
        apply_filters('filter_wp_options_by_field_type', $all_options, 'select'),
        apply_filters('filter_wp_options_by_field_type', $all_options, 'checkbox'),
        apply_filters('filter_wp_options_by_field_type', $all_options, 'radio'),
        apply_filters('filter_wp_options_by_field_type', $all_options, 'truefalse')
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
        filemtime(plugin_dir_path(__FILE__) . 'assets/styles/settings.css') // Version number
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
        'title' => array(
            'type' => 'string',
            'default' => ''
        ),
        'description' => array(
            'type' => 'string',
            'default' => ''
        ),
        'label' => array(
            'type' => 'string',
            'default' => ''
        ),
        'placeholder' => array(
            'type' => 'string',
            'default' => ''
        ),
        'helpText' => array(
            'type' => 'string',
            'default' => ''
        ),
        'isRequired' => array(
            'type' => 'boolean',
            'default' => false
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



function get_post_meta_via_rest($request) {
    $post_id = $request['id'];
    $post = get_post($post_id);

    if (!$post) {
        return new WP_Error('rest_post_invalid_id', __('Invalid post ID.', 'adaptive-content-enhancer'), array('status' => 404));
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
        return new WP_Error('rest_post_invalid_id', __('Invalid post ID.', 'adaptive-content-enhancer'), array('status' => 404));
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
        return new WP_Error('rest_meta_key_invalid', __('Invalid meta key.', 'adaptive-content-enhancer'), array('status' => 404));
    }

    return new WP_REST_Response($meta[$meta_key]);
}

// Modify existing filter to handle 'from_meta_endpoint' requests correctly
add_filter('rest_prepare_post', 'modify_post_meta_for_rest', 10, 3);
function modify_post_meta_for_rest($response, $post, $request) {
    global $all_options;
    $from_meta_endpoint = $request->get_param('from_meta_endpoint');
    
    // Skip non-view contexts and unrelated requests
    if ($request->get_param('context') !== 'view' && !$from_meta_endpoint) {
        return $response;
    }

    $meta = $response->data['meta'];
    $choices = $all_options;

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
    global $all_options;
    if ($source_name !== 'core/post-meta') {
        return $value;
    }


    // Fetch all options dynamically if it matches our keys
    $dynamic_keys  = $all_options;

    
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
    if (!isset($_POST['_wpnonce'])) {
        wp_send_json_error('Nonce not set', 403);
    }
    
    // Unslash the entire $_POST array
    $unslashed_post = wp_unslash($_POST);
    
    // Now verify the nonce with the unslashed variable
    if (!wp_verify_nonce($unslashed_post['_wpnonce'], 'ace_nonce')) {
        wp_send_json_error('Invalid nonce', 403);
    }

    // Check if the option name is set
    if (isset($unslashed_post['option_name'])) {
        $group_name = sanitize_text_field($unslashed_post['option_name']); // Now use the unslashed variable
        $option_name = create_underscored_value($group_name);
        // Fetch all groups (dropdowns and true/false) using the filter
        $all_groups = $all_options;

        // Check if the group exists in the filtered options
        $group_exists = false;
        foreach ($all_groups as $group) {
            if (create_underscored_value($group['name']) === $option_name) {
                $group_exists = true;
                break;
            }
        }

        if (delete_option($option_name)) {
            wp_cache_delete('ace_all_options_cache');
            wp_send_json_success('Option deleted successfully');
        } else {
            wp_send_json_error($all_groups);
        }
    } else {
        wp_send_json_error('Invalid option name');
    }
}



function get_all_acf_fields_and_types($cache_key = 'acf_field_types_cache') {
    // Attempt to load ACF field keys and types from the cache
    $acf_fields = wp_cache_get($cache_key);

    // Check if ACF fields are not cached
    if ($acf_fields === false) {
        // Initialize an array to hold ACF field keys and their types
        $acf_fields = [];

        // Retrieve all posts (you may want to paginate this if you have many)
        $args = [
            'numberposts' => -1, // Get all posts
            'post_type'   => 'any', // Get all post types, or specify as needed
        ];
        $all_posts = get_posts($args);

        // Loop through each post to gather ACF field keys
        foreach ($all_posts as $post) {
            // Get all post meta for the current post
            $meta = get_post_meta($post->ID);

            // Loop through meta keys and check for ACF-related keys
            foreach ($meta as $meta_key => $meta_value) {
                if (strpos($meta_key, '_') === 0 && strpos($meta_key, 'field_') === 0) {
                    // Only include ACF keys
                    $actual_field_key = substr($meta_key, 1); // Remove leading underscore
                    $acf_field_data = get_acf_field_data_by_key($meta_value); // Fetch the field data

                    // Check if the type exists
                    if ($acf_field_data && isset($acf_field_data['type'])) {
                        $acf_fields[$actual_field_key] = $acf_field_data['type'];
                    } else {
                        $acf_fields[$actual_field_key] = 'unknown';
                    }
                }
            }
        }

        // Cache the results for future use
        wp_cache_set($cache_key, $acf_fields);
    }

    return $acf_fields; // Return the cached or newly retrieved fields
}


function get_post_meta_by_key($field_key) {
    // Check if ACF is active
    if (function_exists('get_field')) {
        // Use ACF's get_field function to retrieve the field data
        $acf_field_data = get_field($field_key);

        // If the field data exists and is an array, return its type
        if ($acf_field_data && is_array($acf_field_data)) {
            return $acf_field_data['type'] ?? 'unknown'; // Return 'type' if present, or 'unknown'
        }
    }

    // Fallback: Use get_post_meta to retrieve the meta value directly
    $meta_value = get_post_meta(get_the_ID(), $field_key, true); // Replace get_the_ID() with actual post ID if necessary

    if ($meta_value) {
        // ACF stores field definitions as serialized data, so we need to unserialize
        $acf_field_data = maybe_unserialize($meta_value);
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
    // Use get_posts to fetch the ACF field post by post_name
    $acf_field_posts = get_posts(array(
        'name'        => $acf_field_key,
        'post_type'   => 'acf-field',
        'numberposts' => 1,
    ));

    if (!empty($acf_field_posts)) {
        // Since we expect only one post, we can safely access the first element
        $acf_field_post = $acf_field_posts[0];

        // Unserialize the field data stored in post_content
        $acf_field_data = maybe_unserialize($acf_field_post->post_content);

        // Add post_excerpt to the field data for type comparison
        $acf_field_data['post_excerpt'] = $acf_field_post->post_excerpt;

        return $acf_field_data;
    }

    wp_debug_log('ACF field data not found for key: ' . $acf_field_key);
    return null; // Return null if not found
}

function get_acf_field_content_by_excerpt($field_key) {
    // Use get_posts to fetch the ACF field post by post_excerpt
    $acf_field_posts = get_posts(array(
        'post_excerpt' => $field_key,
        'post_type'    => 'acf-field',
        'numberposts'  => 1,
    ));

    // Check if we got a result
    if (!empty($acf_field_posts)) {
        $acf_field_post = $acf_field_posts[0];

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


    // Unslash the entire $_POST array
    $unslashed_post = wp_unslash($_POST);
    
    // Now verify the nonce with the unslashed variable
    if (!wp_verify_nonce($unslashed_post['_wpnonce'], 'save_acf_imports')) {
        wp_die('Invalid nonce');
    }

    // Process the form if there are fields to import
    if (isset($_POST['acf_import_fields'])) {
        // Unsplash the entire array of ACF import fields
        $acf_import_fields = array_map('sanitize_text_field', wp_unslash($_POST['acf_import_fields']));
    
        // Sanitize each item in the array
        
        

        foreach ($acf_import_fields as $index => $serialized_choices) {
               // Remove any escaping before unserializing
            $cleaned_choices = stripslashes($serialized_choices);

            // Deserialize the cleaned choices array
            $choices = unserialize($cleaned_choices);

            // Fetch the corresponding group name (post_excerpt)
            $group_name = isset($_POST['acf_group_names'][$index]) ? sanitize_text_field(wp_unslash($_POST['acf_group_names'][$index])) : '';


            wp_debug_log('Deserialized Fields ' . $choices);

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
                wp_debug_log('Importing ACF group: ' . $group_name);

                // Save the group data as an option in the required serialized format
                $updated = update_option($option_name, $group_data);
                $updated = true; // Simulate successful update
                // Log success for each imported group
                if ($updated) {
                    wp_debug_log("Successfully imported ACF group: " . $group_name);

                    // Step 2: Scan for posts and match ACF data to update as post meta
                    $posts = get_posts(array('numberposts' => -1)); // Get all posts

                    foreach ($posts as $post) {
                        // Fetch all meta for this post
                        $post_meta = get_post_meta($post->ID);
                    
                        // wp_debug_log('Post Meta Data for Post #' . $post->ID . ': ' . $post_meta); // Log all post meta for the post
                    
                        // Loop through each meta to find ACF fields that match the group choices
                        foreach ($post_meta as $meta_key => $meta_value) {
                            // Check if the meta key is related to ACF (ACF fields typically start with an underscore)
                            if (strpos($meta_key, '_') === 0) {
                                $acf_meta_key = substr($meta_key, 1); // Remove the leading underscore
                                $acf_meta_value = maybe_unserialize($meta_value[0]); // Unserialize if needed
                                
                                // Check if we have the regular version of this meta key in the post's metadata
                                if (isset($post_meta[$acf_meta_key])) {
                                    $regular_meta_value = maybe_unserialize($post_meta[$acf_meta_key][0]); // Unserialize the corresponding regular meta value if needed
                                    wp_debug_log('ACF Meta Key: ' . $acf_meta_key . ' | Value: ' . $regular_meta_value);
                                } else {
                                    wp_debug_log('No matching non-prefixed meta value found for ACF key: ' . $acf_meta_key);
                                }
                            }
                        }
                    }
                    
                    
                    

                                    } else {
                    wp_debug_log("Failed to import ACF group: " . $group_name);
                }
            } else {
                wp_debug_log("Invalid choices or group name");
            }
        }
    }

    // Redirect back to the import page
    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=ace-acf-import');
    wp_redirect(add_query_arg('acf_imported', '1', $redirect_url));
    exit;
}

function get_acf_sample_data($field_key) {
    // Attempt to load the sample value from the cache
    $cache_key = 'acf_sample_data_' . sanitize_key($field_key);
    $sample_value = wp_cache_get($cache_key);

    if ($sample_value === false) {
        // Instead of querying posts directly with meta_key, consider if you can use an alternative method
        // You can also check for the most recent post in a specific custom post type, if applicable
        $args = array(
            'post_type'   => 'your_custom_post_type', // Replace with your post type
            'posts_per_page' => 1, // Only fetch the most recent post
            'fields'      => 'ids', // Only retrieve the post IDs for performance
        );

        // Load all posts with the specified post type
        $posts = get_posts($args);

        // Check if we have any posts
        if (!empty($posts)) {
            // Instead of getting meta data, consider other fields if possible
            $sample_value = get_post_meta($posts[0], $field_key, true);

            // Fallback logic for a better experience
            if ($sample_value) {
                $sample_value = maybe_unserialize($sample_value);
                if (is_array($sample_value) || is_object($sample_value)) {
                    $sample_value = wp_json_encode($sample_value); // Convert to JSON for display
                }
            } else {
                $sample_value = 'No data found'; // Fallback message if no sample data is found
            }
        } else {
            $sample_value = 'No data found'; // Fallback if no posts found
        }

        // Cache the result for future use
        wp_cache_set($cache_key, $sample_value);
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
    // Get all ACF fields for the post using ACF's built-in function
    $acf_fields = get_field_objects($post_id);

    // Initialize an array to hold fields to register
    $fields_to_register = [];

    // Check if ACF fields exist
    if ($acf_fields) {
        foreach ($acf_fields as $field_key => $field_data) {
            // Remove leading underscore from the field key
            $actual_field_key = ltrim($field_key, '_'); 

            // Retrieve the stored value for the field using caching logic
            $meta_value = get_acf_field_value_with_cache($post_id, $field_key); 

            // Determine the field type based on the stored value
            $field_type = ace_determine_field_type($meta_value);

            // Handle arrays: dynamically create the schema for array types
            if ($field_type === 'array') {
                $items_schema = ace_determine_array_items_schema($meta_value);

                $fields_to_register[] = [
                    'type' => 'array',
                    'schema' => [
                        'items' => $items_schema, // Define the schema for each array item dynamically
                    ]
                ];

            } else {
                // Register other non-array field types
                $fields_to_register[] = [
                    'type' => $field_type
                ];
            }

            // Log for debugging purposes
            wp_debug_log("Determined field type: $field_type for field: $actual_field_key");
        }
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

//add_action('rest_api_init', 'conditionally_register_acf_fields_for_current_post');

function conditionally_register_acf_fields_for_current_post() {
    // Only run this inside the WordPress admin and the post editor screen.
    if (is_admin() && isset($_GET['post'])) {
        
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (!wp_verify_nonce($nonce, 'your_nonce_action')) {
            wp_die('Nonce verification failed.');
        }

        $post_id = intval($_GET['post']); 

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

                wp_debug_log('Schema for field: ' . $field['meta_key'] . ' | ' . wp_json_encode($schema));

                if (function_exists('register_post_meta')) {
                    try {
                        register_post_meta(
                            'post',
                            sanitize_key($field['meta_key']), // Sanitize the meta key
                            array(
                                'show_in_rest'      => [
                                    'schema' => $schema // Dynamically assign schema based on the field type
                                ],
                                'single'            => true,
                                'type'              => $field['type'],
                            )
                        );
                        wp_debug_log('Successfully registered field: ' . sanitize_key($field['meta_key']));
                    } catch (Exception $e) {
                        wp_debug_log('Failed to register field: ' . sanitize_key($field['meta_key']) . ' | Error: ' . $e->getMessage());
                    }
                } else {
                    wp_debug_log('register_post_meta function not available.');
                }
            }
        } else {
            wp_debug_log('Post ID is not valid');
        }
    }
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

        wp_enqueue_style('wp-block-editor', includes_url('css/dist/block-editor.css'), array(), $GLOBALS['wp_version']);    
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

    register_rest_route('ace/v1', '/submit-form', array(
        'methods' => 'POST',
        'callback' => 'ace_submit_form',
        'permission_callback' => '__return_true',
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


        // Clear the cache for this option after updating
        wp_cache_delete('ace_all_options_cache'); // Clear the cache for all options
        
        
        // Add to updated choice names list
        $updated_choice_names[] = $choice['name'];
    }

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Fields saved successfully',
        'updated_choices' => $updated_choice_names, // Return the updated names
    ), 200);
}

function ace_submit_form(WP_REST_Request $request) {
    $honeypot = sanitize_text_field((string) $request->get_param('ace_hp'));
    if ($honeypot !== '') {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Spam detected.',
        ), 400);
    }

    $nonce = (string) $request->get_param('ace_form_nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'ace_submit_form')) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid form nonce.',
        ), 403);
    }

    $config_payload = (string) $request->get_param('ace_form_config');
    $config_signature = (string) $request->get_param('ace_form_signature');

    if (
        $config_payload === '' ||
        $config_signature === '' ||
        !hash_equals(ace_sign_contact_form_config($config_payload), $config_signature)
    ) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid form configuration.',
        ), 400);
    }

    $decoded_config = base64_decode($config_payload, true);
    $config = $decoded_config ? json_decode($decoded_config, true) : null;

    if (!is_array($config)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Could not read the form configuration.',
        ), 400);
    }

    $recipient = sanitize_email($config['recipient'] ?? '');
    if (!$recipient || !is_email($recipient)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'This form is missing a valid recipient email address.',
        ), 400);
    }

    $fields = ace_normalize_form_submission($request->get_param('fields'));
    if (empty($fields)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Please complete at least one field before sending the form.',
        ), 400);
    }

    $reply_to = ace_find_reply_to_email($fields);
    $page_url = esc_url_raw((string) $request->get_param('page_url'));
    $subject = sanitize_text_field($config['subject'] ?? '');
    if ($subject === '') {
        $subject = sprintf('New contact form submission from %s', wp_parse_url(home_url(), PHP_URL_HOST));
    }

    $message = ace_build_form_email_message($config, $fields, $page_url);
    $headers = array('Content-Type: text/plain; charset=UTF-8');

    if ($reply_to) {
        $headers[] = 'Reply-To: ' . $reply_to;
    }

    $sent = wp_mail($recipient, $subject, $message, $headers);

    /**
     * Fires after a front-end form submission has been processed.
     *
     * Runs whether or not the notification email was delivered, so
     * listeners can capture the lead even when mail transport fails.
     *
     * @param array           $fields  Normalised field values keyed by underscored field name.
     * @param array           $config  Verified form configuration (recipient, subject, messages).
     * @param WP_REST_Request $request The submission request.
     * @param bool            $sent    Whether the notification email was sent.
     */
    do_action('ace_form_submitted', $fields, $config, $request, $sent);

    if (!$sent) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => sanitize_text_field($config['errorMessage'] ?? 'There was an error sending your message.'),
        ), 500);
    }

    return new WP_REST_Response(array(
        'success' => true,
        'message' => sanitize_text_field($config['successMessage'] ?? 'Thank you for your message!'),
    ), 200);
}

function get_user_roles($object, $field_name, $request) {
    return get_userdata($object['id'])->roles;
  }
  
  add_action('rest_api_init', function() {
    register_rest_field('user', 'roles', array(
      'get_callback' => 'get_user_roles',
      'update_callback' => null,
      'schema' => array(
        'type' => 'array'
      )
    ));
  });

function ace_get_choices(WP_REST_Request $request) {
    global $all_options;
    $page = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;
    $sort_by = $request->get_param('sort') ?: 'edited';
    $field_type = $request->get_param('field_type');
    $tags = $request->get_param('tags');
    $search_query = $request->get_param('search_query');
    $choice_name = $request->get_param('choice_name');
    $names = $request->get_param('names');

    $offset = ($page - 1) * $per_page;

    $all_choices = $all_options;

    $choices = $all_choices;
    if ($choice_name) {
        $normalized_choice_name = create_underscored_value($choice_name);
        $choices = array_filter($choices, function($choice) use ($normalized_choice_name) {
            return create_underscored_value($choice['name'] ?? '') === $normalized_choice_name;
        });
    }

    if ($names) {
        $requested_names = array_filter(array_map('create_underscored_value', explode(',', $names)));
        $choices = array_filter($choices, function($choice) use ($requested_names) {
            return in_array(create_underscored_value($choice['name'] ?? ''), $requested_names, true);
        });
    }

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

    $total_choices = count($choices);

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
    $total_pages = max(1, (int) ceil(count($choices) / $per_page));

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

    // Add roles and post types to each choice based on its tags
    foreach ($paginated_choices as &$choice) {
        $choice['roles'] = [];
        $choice['post_types'] = [];

        if (!empty($choice['tags'])) {
            foreach ($choice['tags'] as $tag_id) {
                if (isset($tags_with_descriptions[$tag_id])) {
                    // Unserialize associated roles and post types if they exist
                    $associated_roles = maybe_unserialize($tags_with_descriptions[$tag_id]['meta']['associated_roles'][0] ?? []);
                    $post_types = maybe_unserialize($tags_with_descriptions[$tag_id]['meta']['post_types'][0] ?? []);
                    
                    // Merge associated roles and post types
                    $choice['roles'] = array_unique(array_merge($choice['roles'], $associated_roles));
                    $choice['post_types'] = array_unique(array_merge($choice['post_types'], $post_types));
                }
            }
        }
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
        'totalFields' => $total_choices,
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
            'label' => __( 'Field Tags', 'adaptive-content-enhancer' ),
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
            <label for="field_tags_color"><?php echo esc_html__('Color', 'adaptive-content-enhancer'); ?></label>
        </th>
        <td>
            <input type="text" name="field_tags_color" id="field_tags_color" value="<?php echo esc_attr($color); ?>" class="color-field" />
            <p class="description"><?php echo esc_html__('Select a color for this field tag.', 'adaptive-content-enhancer'); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row" valign="top">
            <label for="associated_roles"><?php echo esc_html__('Associated Roles', 'adaptive-content-enhancer'); ?></label>
        </th>
        <td>
            <input type="text" name="associated_roles" id="associated_roles" value="<?php echo esc_attr(implode(', ', (array) $associated_roles)); ?>" />
            <p class="description"><?php echo esc_html__('Comma-separated list of associated roles.', 'adaptive-content-enhancer'); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row" valign="top">
            <label for="post_types"><?php echo esc_html__('Post Types', 'adaptive-content-enhancer'); ?></label>
        </th>
        <td>
            <input type="text" name="post_types" id="post_types" value="<?php echo esc_attr(implode(', ', (array) $post_types)); ?>" />
            <p class="description"><?php echo esc_html__('Comma-separated list of associated post types.', 'adaptive-content-enhancer'); ?></p>
        </td>
    </tr>
    <?php
}
add_action('field_tags_edit_form_fields', 'add_field_tags_meta_box');

// Save function for Color, Associated Roles, and Post Types
function save_field_tags_meta($term_id) {
    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'save_field_tags_meta')) {
        return; // Exit if nonce is invalid
    }

    // Save color
    if (isset($_POST['field_tags_color'])) {
        $color = sanitize_hex_color(wp_unslash($_POST['field_tags_color']));
        update_term_meta($term_id, 'color', $color);
    }

    // Save associated roles
     // Save associated roles
     if (isset($_POST['associated_roles'])) {
        // Unsplash the associated roles input and then sanitize
        $roles = array_map('sanitize_text_field', wp_unslash($_POST['associated_roles']));
        $roles = array_map('trim', explode(',', $roles));
        update_term_meta($term_id, 'associated_roles', $roles);
    }

    // Save post types
    if (isset($_POST['post_types'])) {
        // Unsplash the post types input and then sanitize

        $post_types = array_map('sanitize_text_field', wp_unslash($_POST['post_types']));
        $post_types = array_map('trim', explode(',', $post_types));
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
