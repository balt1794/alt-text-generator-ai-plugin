<?php
/*
Plugin Name: Alt Text Generator AI
Plugin URI: https://alttextgeneratorai.com
Description: Automatically generates alt text for uploaded images.
Version: 1.0
Author: Bryam Loaiza
Author URI: https://alttextgeneratorai.com
License: GPLv2
*/

// Activation hook
register_activation_hook(__FILE__, 'alt_text_activate');

function alt_text_activate()
{
    // Activation tasks if any
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'alt_text_deactivate');

function alt_text_deactivate()
{
    // Deactivation tasks if any
}

// Add settings page
add_action('admin_menu', 'alt_text_add_settings_page');
function alt_text_add_settings_page()
{
    add_menu_page('Alt Text Generator AI Settings', 'Alt Text Generator AI', 'manage_options', 'alt-text', 'alt_text_settings_page');
}

// Settings page content
function alt_text_settings_page()
{
    // Get the verified status and free rewrites left if available
    $verified_status = get_option('api_key_verified', false);
    $free_rewrites_left = '';
     
    // If verified, get the free rewrites left
    if ($verified_status) {
        $free_rewrites_left = get_option('free_rewrites_left', '');
    }

    // Handle form submission
    if (isset($_POST['verify'])) {
        // Verify the API key
        $api_key = sanitize_text_field($_POST['api_key']);
        
        // Save the API key regardless of verification result
        update_option('api_key', $api_key);

        // Check if the API key is saved properly
        $saved_api_key = get_option('api_key');
        echo '<div class="updated"><p>Saved API Key: ' . $saved_api_key . '</p></div>';

        $result = verify_api_key($api_key);
        
        // Display verification result
        if ($result !== false) {
            // Update the verified status and free rewrites left
            update_option('api_key_verified', true);
            update_option('free_rewrites_left', $result);
            $free_rewrites_left = $result;
            echo '<div class="updated"><p>You are verified! Thanks</p></div>';
        } else {
            update_option('api_key_verified', false);
            $free_rewrites_left = 'N/A';
            echo '<div class="error"><p>Invalid API Key</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h2>Alt Text Generator AI Settings</h2>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API Key:</th>
                    <td><input type="text" name="api_key" value="<?php echo esc_attr(get_option('api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Credits:</th>
                    <td><?php echo $free_rewrites_left !== '' ? $free_rewrites_left : 'N/A'; ?></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Please click on verify to see current credits</th>
                </tr>
                <tr valign="top">
                    <th scope="row"><a href="https://alttextgeneratorai.com" target="_blank">alttextgeneratorai.com</a></th>
                </tr>
            </table>
            <?php submit_button('Verify', 'secondary', 'verify', false); ?>
        </form>
    </div>
    <?php
}

// Function to verify API key via cURL call
function verify_api_key($api_key)
{
    // Endpoint URL for API key verification
    $endpoint = 'https://alttextgeneratorai.com/api/verify';

    // Prepare data for cURL request
    $data = array(
        'apiKey' => $api_key,
    );

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt_array($ch, array(
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
        ),
    ));

    // Execute cURL request
    $response = curl_exec($ch);

    // Check for cURL error
    if ($response === false) {
        return false;
    }

    // Decode JSON response
    $result = json_decode($response, true);

    // Check if response contains freeRewritesLeft
    if (isset($result['freeRewritesLeft'])) {
        return $result['freeRewritesLeft'];
    } else {
        return false;
    }
}

// Hook into the image upload process to generate alt text
add_filter('wp_generate_attachment_metadata', 'process_images_on_raw_upload', 10, 2);
function process_images_on_raw_upload($data, $attachment_id)
{
    // Generate alt text for the uploaded image
    generate_alt_text($attachment_id);
    return $data;
}

// Function to generate alt text for an image
function generate_alt_text($attachment_id)
{
    // Get uploaded image data
    $image_meta = wp_get_attachment_metadata($attachment_id);

    // Check if it's an image
    if ($image_meta && isset($image_meta['file'])) {
        $file_folder = explode("/", $image_meta['file']);
        array_pop($file_folder);
        $final_file_path = implode('/', $file_folder);
        $image_path = wp_upload_dir()['basedir'] . '/' . $final_file_path . '/' . $image_meta['sizes']['thumbnail']['file'];

        // Get the API key from plugin settings
        $api_key = get_option('api_key');

        // Generate alt text using your site's endpoint
        $generated_alt_text = generate_alt_text_from_site_endpoint($image_path, $api_key);

        // Log the generated alt text for debugging
        error_log('Generated Alt Text: ' . $generated_alt_text);

        // Update alt text in WordPress media library
        if ($generated_alt_text) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $generated_alt_text);
        }
    }
}

// Function to trigger alt text generation via your site's endpoint
function generate_alt_text_from_site_endpoint($image_path, $api_key)
{
    // Extract the filename from the image path
    $image_filename = basename($image_path);

    // Endpoint URL of your site
    $endpoint = 'https://alttextgeneratorai.com/api/wp';

    // Prepare the request data
    $data = array(
        'image' => $image_filename,
        'wpkey' => $api_key,
    );

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt_array($ch, array(
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
        ),
    ));

    // Execute the request
    $response = curl_exec($ch);

    // Check for cURL error
    if ($response === false) {
        error_log('cURL Error: ' . curl_error($ch));
        return 'Alt text generation failed.';
    }

    // Return the response from the endpoint
    return $response;
}
?>
