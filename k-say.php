<?php
/*
Plugin Name: K-Say
Description: Allows users to input text, sends a query to the ChatGPT API using the gpt-3.5-turbo-1106 model, and formats the response in a stylish way.
Version: 1.0
Author: Shawn Clark
Author URI: https://linuxlawyer.com/wordpress-plugins/
License: GPLv2 or later
Text Domain: linuxlawyer
*/

// Function to format API response into a bulleted list
function format_gpt_response($response) {
    $formatted_response = '';
    $lines = explode("\n", $response);
    $is_list = false;

    foreach ($lines as $line) {
        if (preg_match('/^\d+\)/', $line)) {
            if (!$is_list) {
                $formatted_response .= '<ul>';
                $is_list = true;
            }
            $formatted_response .= '<li>' . substr($line, strpos($line, ')') + 1) . '</li>';
        } else {
            if ($is_list) {
                $formatted_response .= '</ul>';
                $is_list = false;
            }
            $formatted_response .= '<p>' . $line . '</p>';
        }
    }

    if ($is_list) {
        $formatted_response .= '</ul>';
    }

    return $formatted_response;
}

// Add shortcode to display the form and fetched content
function webpage_fetcher_shortcode() {
    ob_start();
    $formDisplayed = !isset($_POST['textContent']); // Display form only if textContent is not set
    ?>
    <style>
        #loadingBar, .disabled-input, .error-message {
            display: none;
            background-color: blue;
            color: white;
            padding: 10px;
            margin-top: 10px;
        }

        .disabled-input {
            background-color: #ddd;
        }

        .error-message {
            display: block;
            color: red;
        }

        .gpt-response ul {
            list-style-type: disc;
            margin-left: 20px;
        }

        .gpt-response li {
            margin-bottom: 10px;
        }

        .contract-term-inputs {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .contract-term-inputs input {
            width: 30%; /* Adjust the width as needed */
        }
    </style>

    <?php if ($formDisplayed): ?>
        <form method="post" id="webpageFetcherForm">
            <div class="contract-term-inputs">
                <input type="text" name="section1" value="limitation of liability" placeholder="Contract Term 1" />
                <input type="text" name="section2" value="intellectual property" placeholder="Contract Term 2" />
                <input type="text" name="section3" value="termination" placeholder="Contract Term 3" />
            </div>
            <label for="textContent"><?php _e('Paste your text here:', 'linuxlawyer'); ?></label>
            <textarea name="textContent" id="textContent" rows="10" style="width: 100%;"></textarea>
            <input type="submit" value="<?php _e('Analyze Text', 'linuxlawyer'); ?>" />
        </form>
    <?php endif; ?>

    <?php
    if (isset($_POST['textContent'])) {
        echo '<div id="loadingBar"><div>Loading...</div></div>';

        $section1 = sanitize_text_field($_POST['section1']);
        $section2 = sanitize_text_field($_POST['section2']);
        $section3 = sanitize_text_field($_POST['section3']);

        $content = sanitize_textarea_field($_POST['textContent']);
        $content = strip_tags($content); // Remove HTML tags from the content

        if (strlen($content) < 600) {
            echo '<div class="error-message">Please put in real terms and conditions! These are too short...</div>';
        } else {
            $question = "For each 1) $section1, 2) $section2, 3) $section3, ENTER YOUR AI PROMPT HERE!";
            $combined_prompt = $question . "\n\n" . $content; // Combine question with the content

            $gpt_response = query_chat_gpt($combined_prompt);
            $formatted_response = format_gpt_response($gpt_response);
            echo '<h2>Results</h2>'; // Add a heading for the results
            echo '<div class="gpt-response">' . $formatted_response . '</div>';
        }
    }

    return ob_get_clean();
}

function query_chat_gpt($input_text) {
    // Assuming OPENAI_API_KEY is defined in wp-config.php
    if (!defined('OPENAI_API_KEY') or !OPENAI_API_KEY) {
        return 'OpenAI API key is not set or not defined.';
    }

    $api_url = 'https://api.openai.com/v1/chat/completions'; // Endpoint for chat completions

    $data = array(
        'model' => 'gpt-3.5-turbo-1106', // Specifying the gpt-3.5-turbo-1106 model
        'messages' => array(array('role' => 'user', 'content' => $input_text))
    );

    $headers = array(
        'Authorization' => 'Bearer ' . OPENAI_API_KEY,
        'Content-Type' => 'application/json'
    );

    $response = wp_safe_remote_post($api_url, array(
        'method' => 'POST',
        'headers' => $headers,
        'body' => json_encode($data),
        'data_format' => 'body',
        'timeout' => 300
    ));

    if (is_wp_error($response)) {
        error_log('Query ChatGPT error: ' . $response->get_error_message());
        return 'Error querying ChatGPT: ' . $response->get_error_message();
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!$body) {
        error_log('Query ChatGPT response error: Invalid response body');
        return 'Invalid response from ChatGPT';
    }

    if (isset($body['error'])) {
        error_log('ChatGPT API Error: ' . print_r($body['error'], true));
        return 'Error from ChatGPT API: ' . $body['error']['message'];
    }

    return $body['choices'][0]['message']['content'] ?? 'No response from ChatGPT';
}

add_shortcode('webpage_fetcher', 'webpage_fetcher_shortcode');
?>
