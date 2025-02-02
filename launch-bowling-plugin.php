<?php
/*
Plugin Name: Launch Bowling Plugin
Description: A plugin to handle fantasy bowling interactions.
Version: 1.0
Author: ioLaunch
*/

// Ensure the file is being run within the WordPress context
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue scripts and styles
function launch_bowling_enqueue_scripts() {
    wp_enqueue_script('launch-bowling-js', plugin_dir_url(__FILE__) . 'js/launch-bowling.js', array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'), '1.0', true);
    wp_localize_script('launch-bowling-js', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'user_id' => get_current_user_id()));
    wp_enqueue_style('launch-bowling-css', plugin_dir_url(__FILE__) . 'css/launch-bowling.css');
}
add_action('wp_enqueue_scripts', 'launch_bowling_enqueue_scripts');

// Determine the current week number based on the bowling_schedule table
function get_current_week_number() {
    global $wpdb;
    $table_schedule = $wpdb->prefix . 'bowling_schedule';
    $today = current_time('Y-m-d');

    $week_number = $wpdb->get_var($wpdb->prepare("
        SELECT week_number 
        FROM $table_schedule 
        WHERE start_date <= %s 
        ORDER BY start_date DESC 
        LIMIT 1
    ", $today));

    return $week_number;
}

// Get the event title based on the current date
function get_event_title() {
    global $wpdb;
    $table_schedule = $wpdb->prefix . 'bowling_schedule';
    $today = current_time('Y-m-d H:i:s');

    $event_title = $wpdb->get_var($wpdb->prepare("
        SELECT event_title 
        FROM $table_schedule 
        WHERE start_date <= %s AND end_date >= %s
        LIMIT 1
    ", $today, $today));

    if ($event_title) {
        return $event_title;
    } else {
        return 'Picks are closed at this time';
    }
}


// Function to get current selections for the user
function get_current_selections() {
    global $wpdb;
    $user_id = intval($_POST['user_id']);
    $table_selections = $wpdb->prefix . 'bowling_picks';
    $week_number = get_current_week_number();

    if (!$week_number) {
        wp_send_json_error('No valid week found.');
        return;
    }

    // Fetch the active user selection for the current week
    $selections = $wpdb->get_row($wpdb->prepare("
        SELECT selection_1, selection_2, selection_3, selection_4, selection_5, selection_wild 
        FROM $table_selections 
        WHERE user_id = %d AND week = %d AND status = 'active'
        LIMIT 1
    ", $user_id, $week_number), ARRAY_A);

    if ($selections) {
        wp_send_json_success($selections);
    } else {
        wp_send_json_success(array('message' => 'No current selections found.'));
    }
}
add_action('wp_ajax_get_current_selections', 'get_current_selections');
add_action('wp_ajax_nopriv_get_current_selections', 'get_current_selections');


// Function to save user selections
function save_selections() {
    global $wpdb;
    $user_id = intval($_POST['user_id']);
    $selections = $_POST['selections'];
    $week_number = get_current_week_number();

    if (!$week_number) {
        wp_send_json_error('No valid week found.');
        return;
    }

    $table_selections = $wpdb->prefix . 'bowling_picks';

    // Check if an entry already exists for the current week and user
    $existing_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_selections WHERE user_id = %d AND week = %d", $user_id, $week_number));

    $data = array(
        'selection_1' => $selections[0],
        'selection_2' => $selections[1],
        'selection_3' => $selections[2],
        'selection_4' => $selections[3],
        'selection_5' => $selections[4],
        'selection_wild' => $selections[5],
        'submission_date' => current_time('mysql'),
        'status' => 'active',
        'points' => 0
    );

    if ($existing_entry) {
        // Update the existing entry
        $wpdb->update(
            $table_selections,
            $data,
            array('user_id' => $user_id, 'week' => $week_number)
        );
        $entry_id = $existing_entry->id;
    } else {
        // Insert a new entry
        $data['user_id'] = $user_id;
        $data['week'] = $week_number;
        $wpdb->insert($table_selections, $data);
        $entry_id = $wpdb->insert_id;
    }

    // Ensure only one "active" entry exists per user per week
    $wpdb->query($wpdb->prepare("UPDATE $table_selections SET status = 'archived' WHERE user_id = %d AND week = %d AND id != %d", $user_id, $week_number, $entry_id));

    wp_send_json_success();
}
add_action('wp_ajax_save_selections', 'save_selections');
add_action('wp_ajax_nopriv_save_selections', 'save_selections');


// Schedule the event on plugin activation
function launch_bowling_schedule_event() {
    if (!wp_next_scheduled('launch_bowling_archive_old_entries')) {
        wp_schedule_event(time(), 'hourly', 'launch_bowling_archive_old_entries');
    }
}
register_activation_hook(__FILE__, 'launch_bowling_schedule_event');

// Clear the scheduled event on plugin deactivation
function launch_bowling_clear_scheduled_event() {
    wp_clear_scheduled_hook('launch_bowling_archive_old_entries');
}
register_deactivation_hook(__FILE__, 'launch_bowling_clear_scheduled_event');

// Function to archive old entries
function launch_bowling_archive_old_entries() {
    global $wpdb;
    $table_schedule = $wpdb->prefix . 'bowling_schedule';
    $table_selections = $wpdb->prefix . 'bowling_picks';
    $today = current_time('Y-m-d H:i:s');

    // Get the next week's start date
    $next_week = $wpdb->get_row($wpdb->prepare("
        SELECT week_number, start_date 
        FROM $table_schedule 
        WHERE start_date > %s
        ORDER BY start_date ASC
        LIMIT 1
    ", $today));

    if ($next_week && $today >= $next_week->start_date) {
        // Archive the active entries for the previous week
        $wpdb->query($wpdb->prepare("
            UPDATE $table_selections 
            SET status = 'archived' 
            WHERE week < %d AND status = 'active'
        ", $next_week->week_number));
    }
}
add_action('launch_bowling_archive_old_entries', 'launch_bowling_archive_old_entries');



// Shortcode to display current selections
function launch_bowling_current_selections() {
    ob_start();
    ?>
    <div id="current-selections-container">
        <h3 id="current-selections-title">Current Selections</h3>
        <div id="current-selections">
            <div class="current-selection" id="current-selection-1">1st: <span></span></div>
            <div class="current-selection" id="current-selection-2">2nd: <span></span></div>
            <div class="current-selection" id="current-selection-3">3rd: <span></span></div>
            <div class="current-selection" id="current-selection-4">4th: <span></span></div>
            <div class="current-selection" id="current-selection-5">5th: <span></span></div>
            <div class="current-selection" id="current-selection-wild">Wild: <span></span></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('launch_bowling_current_selections', 'launch_bowling_current_selections');


// Shortcode to display event title and form
function launch_bowling_title_form() {
    $event_title = get_event_title();

    ob_start();
    ?>
    <div id="selections-form-container">
        <h2 id="event-title"><?php echo esc_html($event_title); ?></h2>
        <h3 id="selections-title">Submit your pro bowler picks</h3>
        <form id="selections-form">
            <input type="text" id="selection-1" name="selection_1" placeholder="Selection 1" readonly>
            <input type="text" id="selection-2" name="selection_2" placeholder="Selection 2" readonly>
            <input type="text" id="selection-3" name="selection_3" placeholder="Selection 3" readonly>
            <input type="text" id="selection-4" name="selection_4" placeholder="Selection 4" readonly>
            <input type="text" id="selection-5" name="selection_5" placeholder="Selection 5" readonly>
            <input type="text" id="selection-wild" name="selection_wild" placeholder="Selection Wild" readonly>
            <button type="submit">Submit</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('launch_bowling_title_form', 'launch_bowling_title_form');


// Shortcode to display pro bowler names list and features
function launch_bowling_names_list() {
    global $wpdb;
    $table_names = $wpdb->prefix . 'pro_bowlers';
    $names = $wpdb->get_results("SELECT * FROM $table_names");

    ob_start();
    ?>
    <div id="names-list-container">
        <input type="text" id="search-box" placeholder="Search...">
        <select id="sort-options">
            <option value="az">A-Z</option>
            <option value="za">Z-A</option>
            <option value="pts-high-low">Pts High to Low</option>
            <option value="pts-low-high">Pts Low to High</option>
        </select>
        <div id="names-list">
            <?php foreach ($names as $name): ?>
                <div class="name-item" data-name="<?php echo esc_attr($name->Player); ?>" data-points="<?php echo esc_attr($name->Points); ?>">
                    <?php echo esc_html($name->Player . ' - ' . $name->Points); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('launch_bowling_names_list', 'launch_bowling_names_list');


// Shortcode to display picks open and close times
function launch_bowling_picks_times() {
    global $wpdb;
    $table_schedule = $wpdb->prefix . 'bowling_schedule';
    $today = current_time('Y-m-d H:i:s');

    // Get the current week's start and end dates
    $current_week = $wpdb->get_row($wpdb->prepare("
        SELECT start_date, end_date 
        FROM $table_schedule 
        WHERE start_date <= %s AND end_date >= %s
        LIMIT 1
    ", $today, $today));

    // Get the next week's start date
    $next_week = $wpdb->get_row($wpdb->prepare("
        SELECT start_date 
        FROM $table_schedule 
        WHERE start_date > %s
        ORDER BY start_date ASC
        LIMIT 1
    ", $today));

    ob_start();
    ?>
    <div id="picks-times-container">
        <?php if ($current_week): ?>
            <h3>Picks Open</h3>
            <p>Start: <?php echo date('F j, Y, g:i a', strtotime($current_week->start_date)); ?></p>
            <p>End: <?php echo date('F j, Y, g:i a', strtotime($current_week->end_date)); ?></p>
        <?php else: ?>
            <h3>Picks are currently closed</h3>
            <?php if ($next_week): ?>
                <p>Next picks start on: <?php echo date('F j, Y, g:i a', strtotime($next_week->start_date)); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('launch_bowling_picks_times', 'launch_bowling_picks_times');



// Shortcode to display current week number
function launch_bowling_week_info() {
    global $wpdb;
    $table_schedule = $wpdb->prefix . 'bowling_schedule';
    $today = current_time('Y-m-d H:i:s');

    // Get the current week's information and timer
    $current_week = $wpdb->get_row($wpdb->prepare("
        SELECT week_number, end_date 
        FROM $table_schedule 
        WHERE start_date <= %s AND end_date >= %s
        LIMIT 1
    ", $today, $today));
    ob_start();
    ?>
    <div id="week-info-container">
        <?php if ($current_week): ?>
            <h3>Current Week</h3>
            <p id="current-week-number"><?php echo esc_html($current_week->week_number); ?></p>
            <h3>Pick Time Remaining</h3>
            <p id="countdown-timer"></p>
        <?php else: ?>
            <h3>No Active Week</h3>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('launch_bowling_week_info', 'launch_bowling_week_info');


// Shortcode to display historical selections - move Jquery to JS file?
function launch_bowling_historical_selections() {
    ob_start();
    ?>
    <div id="historical-selections-container">
        <h3 id="historical-selections-title">Historical Selections</h3>
        <table id="historical-selections-table">
            <thead>
                <tr>
                    <th>Week</th>
                    <th>1st</th>
                    <th>2nd</th>
                    <th>3rd</th>
                    <th>4th</th>
                    <th>5th</th>
                    <th>Wild</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <tr id="week-<?php echo $i; ?>">
                        <td><?php echo $i; ?></td>
                        <td class="selection-1"></td>
                        <td class="selection-2"></td>
                        <td class="selection-3"></td>
                        <td class="selection-4"></td>
                        <td class="selection-5"></td>
                        <td class="selection-wild"></td>
                        <td class="points"></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <script>
        jQuery(document).ready(function($) {
            function loadHistoricalSelections() {
                $.ajax({
                    url: ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_historical_selections',
                        user_id: ajax_object.user_id
                    },
                    success: function(response) {
                        if (response.success) {
                            var selections = response.data;
                            selections.forEach(function(selection) {
                                var weekRow = $('#week-' + selection.week);
                                weekRow.find('.selection-1').text(selection.selection_1);
                                weekRow.find('.selection-2').text(selection.selection_2);
                                weekRow.find('.selection-3').text(selection.selection_3);
                                weekRow.find('.selection-4').text(selection.selection_4);
                                weekRow.find('.selection-5').text(selection.selection_5);
                                weekRow.find('.selection-wild').text(selection.selection_wild);
                                weekRow.find('.points').text(selection.points);
                            });
                        } else {
                            $('#historical-selections-table tbody').html('<tr><td colspan="8">No historical selections found.</td></tr>');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.log('AJAX error:', textStatus, errorThrown);
                    }
                });
            }

            // Load historical selections on page load
            loadHistoricalSelections();
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('launch_bowling_historical_selections', 'launch_bowling_historical_selections');


// Handle AJAX request for fetching historical selections
function get_historical_selections() {
    $user_id = intval($_POST['user_id']);

    // Fetch the archived user selections
    global $wpdb;
    $table_selections = $wpdb->prefix . 'bowling_picks';
    $selections = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_selections WHERE user_id = %d AND status = 'archived' ORDER BY week DESC", $user_id), ARRAY_A);

    if ($selections) {
        wp_send_json_success($selections);
    } else {
        wp_send_json_success(array('message' => 'No historical selections found.'));
    }
}
add_action('wp_ajax_get_historical_selections', 'get_historical_selections');
add_action('wp_ajax_nopriv_get_historical_selections', 'get_historical_selections');



// Shortcode to display the ADMIN submission form
function launch_bowling_tournament_placings_form() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['week_number'])) {
        global $wpdb;
        $week_number = intval($_POST['week_number']);
        $first_place = sanitize_text_field($_POST['first_place']);
        $second_place = sanitize_text_field($_POST['second_place']);
        $third_place = sanitize_text_field($_POST['third_place']);
        $fourth_place = sanitize_text_field($_POST['fourth_place']);
        $fifth_place = sanitize_text_field($_POST['fifth_place']);
        $wildcard = sanitize_text_field($_POST['wildcard']);

        // Get the tier from the bml_bowling_schedule table
        $table_schedule = $wpdb->prefix . 'bowling_schedule';
        $tier = $wpdb->get_var($wpdb->prepare("SELECT tier FROM $table_schedule WHERE week_number = %d", $week_number));

        if ($tier !== null) {
            // Insert the data into the bml_tournament_placings table
            $table_placings = $wpdb->prefix . 'tournament_placings';
            $wpdb->insert($table_placings, array(
                'week_number' => $week_number,
                'tier' => $tier,
                'first_place' => $first_place,
                'second_place' => $second_place,
                'third_place' => $third_place,
                'fourth_place' => $fourth_place,
                'fifth_place' => $fifth_place,
                'wildcard' => $wildcard
            ));

            // Trigger the scoring function
            calculate_and_update_scores();

            echo '<p>Placings saved successfully and scores updated.</p>';
        } else {
            echo '<p>Invalid week number.</p>';
        }
    }

    ob_start();
    ?>
    <form id="admin-submission-form" method="post">
        <label for="week_number">Week Number:</label>
        <input type="number" id="week_number" name="week_number" required>
        <label for="first_place">First Place:</label>
        <input type="text" id="first_place" name="first_place" required>
        <label for="second_place">Second Place:</label>
        <input type="text" id="second_place" name="second_place" required>
        <label for="third_place">Third Place:</label>
        <input type="text" id="third_place" name="third_place" required>
        <label for="fourth_place">Fourth Place:</label>
        <input type="text" id="fourth_place" name="fourth_place" required>
        <label for="fifth_place">Fifth Place:</label>
        <input type="text" id="fifth_place" name="fifth_place" required>
        <label for="wildcard">Wildcard:</label>
        <input type="text" id="wildcard" name="wildcard" required>
        <button type="submit">Submit</button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('launch_bowling_tournament_placings_form', 'launch_bowling_tournament_placings_form');



// Function to calculate and update scores
function calculate_and_update_scores() {
    global $wpdb;
    $table_selections = $wpdb->prefix . 'bowling_picks';
    $table_placings = $wpdb->prefix . 'tournament_placings';
    $table_users_bowling = $wpdb->prefix . 'users_bowling';

    // Fetch all user selections (removed status check)
    $user_selections = $wpdb->get_results("SELECT * FROM $table_selections", ARRAY_A);

    // Fetch the actual tournament results
    $tournament_results = $wpdb->get_results("SELECT * FROM $table_placings", ARRAY_A);

    // Create an associative array for quick lookup of tournament results by week number
    $results_by_week = array();
    foreach ($tournament_results as $result) {
        $results_by_week[$result['week_number']] = $result;
    }

    // Iterate through user selections and calculate scores
    foreach ($user_selections as $selection) {
        $week_number = $selection['week'];

        if (isset($results_by_week[$week_number])) {
            $result = $results_by_week[$week_number];
            $tier = intval($result['tier']);
            $score = 0;

            // Define point multipliers based on tier
            $multiplier = 1;
            if ($tier == 2) {
                $multiplier = 2;
            } elseif ($tier == 1) {
                $multiplier = 3;
            }

            // Correct Selection Point Awards
            $points_awards = array(
                'first_place' => 250,
                'second_place' => 150,
                'third_place' => 115,
                'fourth_place' => 95,
                'fifth_place' => 85,
                'wildcard' => 250
            );

            // Compare user selections with actual results and assign points
            foreach ($points_awards as $position => $points) {
                $selection_position = '';
                switch ($position) {
                    case 'first_place':
                        $selection_position = 'selection_1';
                        break;
                    case 'second_place':
                        $selection_position = 'selection_2';
                        break;
                    case 'third_place':
                        $selection_position = 'selection_3';
                        break;
                    case 'fourth_place':
                        $selection_position = 'selection_4';
                        break;
                    case 'fifth_place':
                        $selection_position = 'selection_5';
                        break;
                    case 'wildcard':
                        $selection_position = 'selection_wild';
                        break;
                }

                if (isset($selection[$selection_position]) && $selection[$selection_position] == $result[$position]) {
                    $score += $points * $multiplier;
                } elseif (isset($selection[$selection_position]) && in_array($selection[$selection_position], array($result['first_place'], $result['second_place'], $result['third_place'], $result['fourth_place'], $result['fifth_place']))) {
                    $actual_position = array_search($selection[$selection_position], array($result['first_place'], $result['second_place'], $result['third_place'], $result['fourth_place'], $result['fifth_place']));
                    $selected_position = array_search($position, array_keys($points_awards));
                    if ($actual_position !== false && abs($actual_position - $selected_position) == 1) {
                        $score += 75;
                    } else {
                        $score += 40;
                    }
                }
            }

            // Update the user's score in the database
            $wpdb->update(
                $table_selections,
                array('points' => $score),
                array('id' => $selection['id'])
            );

            // Update the cumulative points for the user for the 2025 season.  Is waiting for status to be Archived or ...complete?
            $user_id = $selection['user_id'];
            $current_points = $wpdb->get_var($wpdb->prepare("SELECT `2025_points` FROM $table_users_bowling WHERE user_id = %d", $user_id));
            if ($current_points === null) {
                // Insert new record if user does not exist
                $wpdb->insert(
                    $table_users_bowling,
                    array(
                        'user_id' => $user_id,
                        '2025_points' => $score
                    )
                );
            } else {
                // Update existing record
                $wpdb->update(
                    $table_users_bowling,
                    array('2025_points' => $current_points + $score),
                    array('user_id' => $user_id)
                );
            }
        } else {
            // Debugging: Log if no results found for the week
            error_log("No results found for Week: " . $week_number);
        }
    }
}

// // Schedule the scoring function to run periodically
// if (!wp_next_scheduled('calculate_and_update_scores_event')) {
//     wp_schedule_event(time(), 'hourly', 'calculate_and_update_scores_event');
// }
// add_action('calculate_and_update_scores_event', 'calculate_and_update_scores');

// // Clear the scheduled event on plugin deactivation
// function launch_bowling_clear_scheduled_scores_event() {
//     wp_clear_scheduled_hook('calculate_and_update_scores_event');
// }
// register_deactivation_hook(__FILE__, 'launch_bowling_clear_scheduled_scores_event');



// Shortcode to display current and cumulative scores
if (!function_exists('launch_bowling_display_scores')) {
    function launch_bowling_display_scores() {
        global $wpdb;
        $user_id = get_current_user_id();
        $table_selections = $wpdb->prefix . 'bowling_picks';
        $table_users_bowling = $wpdb->prefix . 'users_bowling';

        // Fetch the current week's score. Update status call to 'complete' when status types updated
        $current_week_score = $wpdb->get_var($wpdb->prepare("
            SELECT points 
            FROM $table_selections 
            WHERE user_id = %d AND status = 'archived' 
            ORDER BY week DESC 
            LIMIT 1
        ", $user_id));

        // Fetch the cumulative season score for 2025
        $cumulative_score = $wpdb->get_var($wpdb->prepare("
            SELECT `2025_points` 
            FROM $table_users_bowling 
            WHERE user_id = %d
        ", $user_id));

        ob_start();
        ?>
        <div id="display-scores-container">
            <h3>Last Event Week's Score</h3>
            <p class="score-value"><?php echo esc_html($current_week_score); ?></p>
            <h3>2025 Season Total Score</h3>
            <p class="score-value"><?php echo esc_html($cumulative_score); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
    add_shortcode('launch_bowling_display_scores', 'launch_bowling_display_scores');
}


// Shortcode to display the leaderboard
if (!function_exists('launch_bowling_leaderboard')) {
    function launch_bowling_leaderboard() {
        global $wpdb;
        $table_selections = $wpdb->prefix . 'bowling_picks';
        $table_users = $wpdb->prefix . 'users';

        // Fetch the top 5 users with the highest season total points 
        $leaderboard = $wpdb->get_results("
            SELECT u.display_name, SUM(s.points) as cumulative_score
            FROM $table_selections s
            JOIN $table_users u ON s.user_id = u.ID
            WHERE s.status = 'archived'
            GROUP BY s.user_id
            ORDER BY cumulative_score DESC
            LIMIT 5
        ");

        ob_start();
        ?>
        <div id="leaderboard-container">
            <h3>Overall Leaderboard</h3>
            <ul>
                <?php foreach ($leaderboard as $user): ?>
                    <li><?php echo esc_html($user->display_name. ' - ' . $user->cumulative_score . ' points'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
    add_shortcode('launch_bowling_leaderboard', 'launch_bowling_leaderboard');
}


// Shortcode to display the weekly top pickers
if (!function_exists('launch_bowling_weekly_top_pickers')) {
    function launch_bowling_weekly_top_pickers() {
        global $wpdb;
        $table_selections = $wpdb->prefix . 'bowling_picks';
        $table_schedule = $wpdb->prefix . 'bowling_schedule';
        $table_users = $wpdb->prefix . 'users';
        $today = current_time('Y-m-d');

        // Get the current week number
        $current_week = $wpdb->get_var($wpdb->prepare("
            SELECT week_number 
            FROM $table_schedule 
            WHERE start_date <= %s AND (SELECT start_date FROM $table_schedule WHERE start_date > %s ORDER BY start_date ASC LIMIT 1) > %s
            LIMIT 1
        ", $today, $today, $today));

        // Fetch the top 5 point getters for the current week - when statuses updated these should reflect 'completed'
        $weekly_top_pickers = $wpdb->get_results($wpdb->prepare("
            SELECT u.display_name, s.points
            FROM $table_selections s
            JOIN $table_users u ON s.user_id = u.ID
            WHERE s.week = %d AND s.status = 'archived'
            ORDER BY s.points DESC
            LIMIT 5
        ", $current_week));

        ob_start();
        ?>
        <div id="weekly-top-pickers-container">
            <h3>Weekly Top Scores - Week <?php echo esc_html($current_week); ?></h3>
            <ul>
                <?php foreach ($weekly_top_pickers as $user): ?>
                    <li><?php echo esc_html($user->display_name . ' - ' . $user->points . ' points'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
    add_shortcode('launch_bowling_weekly_top_pickers', 'launch_bowling_weekly_top_pickers');
}


// Function to check if picks are open or closed
function check_picks_status() {
    global $wpdb;
    $table_schedule = $wpdb->prefix . 'bowling_schedule';
    $today = current_time('Y-m-d H:i:s');

    // Get the current week's start and end dates and week number
    $current_week = $wpdb->get_row($wpdb->prepare("
        SELECT week_number, start_date, end_date 
        FROM $table_schedule 
        WHERE start_date <= %s AND end_date >= %s
        LIMIT 1
    ", $today, $today));

    if ($current_week) {
        wp_send_json_success(array('status' => 'open', 'week_number' => $current_week->week_number));
    } else {
        wp_send_json_success(array('status' => 'closed'));
    }
}
add_action('wp_ajax_check_picks_status', 'check_picks_status');
add_action('wp_ajax_nopriv_check_picks_status', 'check_picks_status');


