<?php

if(!defined('ABSPATH')) exit; // Exit if accessed directly

class KMI_Rental_Application
{
    private $options;
    private $general_settings_key = 'general_settings';
    private $waiting_list_settings_key = 'waiting_list_settings';
    private $plugin_options_key = 'kmi_rental_application_menu_option';
    private $plugin_settings_tabs = array();
    private $waiting_list_users = array();
    private $property_listings = array();
    private $message = array();
    
    public function __construct()
    {
        // SHORTCODES
        // Rental application UI
        add_shortcode('kmi_rental_application_form', array($this, 'Rental_Application_Form'));
        // Rental application request UI
        add_shortcode('kmi_rental_application_request_form', array($this, 'Rental_Application_Request_Form'));
        // Rental application view
        add_shortcode('kmi_rental_application_view', array($this, 'Rental_Application_View'));
        
        // FILTER HOOKS
        
        // ACTION HOOKS
        // Add option page in the admin panel
        add_action('admin_menu', array($this, 'Add_Rental_Application_Option_Page'));
        // Register the settings to use on the rental application page
        add_action('admin_init', array($this, 'Register_Rental_Application_Settings'));
        // Add function process on the header top Ex.: form submission process
        add_action('init', array($this, 'Header_Top_Functions'));
        // Add css and scripts
        add_action('wp_enqueue_scripts', array($this, 'Add_Styles_And_Scripts'));
    }
    
    /*
     * Adds an option page for the KMI settings
     */
    public function Add_Rental_Application_Option_Page()
    {
        global $menu, $submenu;
        
        if(!isset($menu['kmi_menu_options']))
            add_menu_page('KMI Options', 'KMI Options', 'manage_options', 'kmi_menu_options', array($this, 'KMI_Options_Page'));
        
        if(!isset($submenu[$this->plugin_options_key]))
        {
            $page = add_submenu_page('kmi_menu_options', 'KMI Rental Application', 'Rental Application', 'manage_options', $this->plugin_options_key, array($this, 'Rental_Application_Option_Page'));
            // Add css
            add_action('admin_print_styles-'.$page, array($this, 'Add_Rental_Application_Option_Page_Style'));
        }
    }
    
    /*
     * KMI option page UI
     */
    public function KMI_Options_Page()
    {
        ?>
        <div class="wrap">
            <h2>Welcome to KMI Technology plugins. You can select the items under this menu to edit the desired plugin's settings.</h2>
        </div>
        <?php
    }
    
    /*
     * Rental application option page UI
     */
    public function Rental_Application_Option_Page()
    {
        $this->options = get_option('kmi_rental_application_option_name');
        
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $this->general_settings_key;
        
        ?>
        <div class="wrap">
            <?php $this->Plugin_Options_Tabs(); ?>
            <form method="POST" action="options.php">
                <?php
                    settings_fields($tab);
                    
                    do_settings_sections($tab);
                    
                    if($tab != $this->waiting_list_settings_key)
                        submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /*
     * Adding css for the rental application option page
     */
    public function Add_Rental_Application_Option_Page_Style()
    {
        wp_enqueue_style('kmi_rental_application_global_css');
    }
    
    /*
     * Register all the settings tab for the rental application
     * option page and other data processing before rendering the
     * option page.
     */
    public function Register_Rental_Application_Settings()
    {
        // Register general settings tab
        $this->plugin_settings_tabs[$this->general_settings_key] = 'General';
        register_setting($this->general_settings_key, $this->general_settings_key, array($this, 'Sanitize_Rental_Application_General_Settings'));
        // Add general settings section
        add_settings_section('general_section', 'General Settings', array($this, 'General_Description_Section'), $this->general_settings_key);
        // Add fields on the general settings tab
        add_settings_field('kmi_rental_application_request_url', 'Rental Application Request URL', array($this, 'Display_Rental_Application_Request_URL_Field'), $this->general_settings_key, 'general_section');
        add_settings_field('kmi_rental_application_form_url', 'Rental Application Form URL', array($this, 'Display_Rental_Application_Form_URL_Field'), $this->general_settings_key, 'general_section');
        add_settings_field('kmi_rental_application_url', 'Rental Application URL', array($this, 'Display_Rental_Application_URL_Field'), $this->general_settings_key, 'general_section');
        
        // Register waiting list settings tab
        $this->plugin_settings_tabs[$this->waiting_list_settings_key] = 'Waiting List';
        register_setting($this->waiting_list_settings_key, $this->waiting_list_settings_key);
        add_settings_section('waiting_list_section', 'Rental Application Waiting List', array($this, 'Waiting_List_Section'), $this->waiting_list_settings_key);
        
        // Register rental application option page's css
        wp_register_style('kmi_rental_application_global_css', plugins_url('css/global.css', __FILE__));
        
        // Delete user from the waiting list queue
        $page = sanitize_text_field($_GET['page']);
        $tab = sanitize_text_field($_GET['tab']);
        $user_id = !empty($_GET['id']) ? absint($_GET['id']) : 0;
        
        if($page == $this->plugin_options_key && $tab == $this->waiting_list_settings_key && $user_id > 0)
        {
            if(update_user_meta($user_id, 'kmi_rental_terms_conditions', 'no') === true)
            {
                $deleted_user = get_user_meta($user_id);
                
                $this->message['success']['waiting_list_deletion'] = $deleted_user['kmi_rental_first_name'][0].' '.$deleted_user['kmi_rental_last_name'][0].'\'s application is removed from the waiting list.';
            }
        }
        
        // Retrieve all the users currently in the waiting list
        $this->waiting_list_users = get_users(array(
            'meta_key'      =>'kmi_rental_terms_conditions',
            'meta_value'    =>'yes',
            'orderby'       =>'kmi_rental_timestamp',
            'order'         => 'ASC'
        ));
    }
    
    /*
     * General settings section
     */
    public function General_Description_Section() { echo 'General settings section goes here.'; }
    
    /*
     * Waiting list settings section
     */
    public function Waiting_List_Section()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $this->general_settings_key;
        
        if(!empty($this->message['success']['waiting_list_deletion']))
        {
            ?><p class="success"><?php echo $this->message['success']['waiting_list_deletion']; ?></p><?php
            $this->message['success']['waiting_list_deletion'] = '';
        }
        ?>
        <table class="kmi-rental-application full-width">
            <tr>
                <th class="bg-grey align-left quarter-width">Name</th>
                <th class="bg-grey align-left quarter-width">Desired Home</th>
                <th class="bg-grey align-left quarter-width">Desired Start Date</th>
                <th class="bg-grey quarter-width">Actions</th>
            </tr>
            <?php if(count($this->waiting_list_users) > 0): ?>
                <?php foreach($this->waiting_list_users as $user): ?>
                    <tr>
                        <td class="quarter-width"><?php echo $user->kmi_rental_first_name.' '.$user->kmi_rental_last_name; ?></td>
                        <td class="quarter-width"><?php echo $user->kmi_rental_house_address; ?></td>
                        <td class="quarter-width"><?php echo $user->kmi_rental_start_date; ?></td>
                        <td class="quarter-width align-center">
                            <a href="<?php echo $this->general_settings['kmi_rental_application_url']; ?>?id=<?php echo $user->ID; ?>" target="_blank" class="dashicons dashicons-media-spreadsheet" title="View application form"></a>
                            <a href="?page=<?php echo $this->plugin_options_key; ?>&tab=<?php echo $current_tab; ?>&id=<?php echo $user->ID; ?>" class="dashicons dashicons-trash" title="Remove application form"></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                    <tr>
                        <td colspan="4" class="quarter-width align-center">No currently enqueued application forms.</td>
                    </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    /*
     * Validating general settings field data
     */
    public function Sanitize_Rental_Application_General_Settings($input)
    {
        $new_input = array();
        
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $this->general_settings_key;
        
        switch($current_tab)
        {
            case 'general_settings':
                if(isset($input['kmi_rental_application_request_url']))
                    $new_input['kmi_rental_application_request_url'] = esc_url(sanitize_text_field($input['kmi_rental_application_request_url']));

                if(isset($input['kmi_rental_application_form_url']))
                    $new_input['kmi_rental_application_form_url'] = esc_url(sanitize_text_field($input['kmi_rental_application_form_url']));

                if(isset($input['kmi_rental_application_url']))
                    $new_input['kmi_rental_application_url'] = esc_url(sanitize_text_field($input['kmi_rental_application_url']));
                
                break;
            case 'waiting_list_settings':
                break;
        }

        return $new_input;
    }
    
    /*
     * Outputs rental application request URL field
     */
    public function Display_Rental_Application_Request_URL_Field()
    {
        ?>
        <input type="text" id="kmi_rental_application_request_url" class="regular-text" name="<?php echo $this->general_settings_key; ?>[kmi_rental_application_request_url]" value="<?php echo $this->general_settings['kmi_rental_application_request_url']; ?>" />
        <?php
    }
    
    /*
     * Outputs rental application form URL field
     */
    public function Display_Rental_Application_Form_URL_Field()
    {
        ?>
        <input type="text" id="kmi_rental_application_form_url" class="regular-text" name="<?php echo $this->general_settings_key; ?>[kmi_rental_application_form_url]" value="<?php echo esc_url($this->general_settings['kmi_rental_application_form_url']); ?>" />
        <?php
    }
    
    /*
     * Outputs rental application URL field
     */
    public function Display_Rental_Application_URL_Field()
    {
        ?>
        <input type="text" id="kmi_rental_application_url" class="regular-text" name="<?php echo $this->general_settings_key; ?>[kmi_rental_application_url]" value="<?php echo $this->general_settings['kmi_rental_application_url']; ?>" />
        <?php
    }
    
    /*
     * Data processing before rendering rental application
     * fron-end pages.
     */
    public function Header_Top_Functions()
    {
        if(empty($this->options))
            $this->options = get_option($this->general_settings_key);
//            $this->options = get_option('kmi_rental_application_option_name');
        
        // For the tab control
        $this->general_settings = (array) get_option($this->general_settings_key);
	$this->waiting_list_settings = (array) get_option($this->waiting_list_settings);
        
        // Merge with defaults
	$this->general_settings = array_merge(
            array(
                'general_option' => 'General value'
            ),
            $this->general_settings
        );
		
	$this->advanced_settings = array_merge(
            array(
                'waiting_list_option' => 'Waiting List value'
            ),
            $this->advanced_settings
        );
        
        // Get all property listings
        $post_args = array(
            'post_type'     => 'listing',
            'post_status'   => 'publish',
            'orderby'       => 'post_title',
            'order'         => 'ASC'
        );
        $this->property_listings = get_posts($post_args);
        
        // For form submission
        if(isset($_POST['kmi_submit']))
        {
            if(!isset($_POST['kmi_nonce_field']) || (!wp_verify_nonce($_POST['kmi_nonce_field'], 'kmi_application_request_form') && !wp_verify_nonce($_POST['kmi_nonce_field'], 'kmi_application_submit_form')))
            {
                echo 'Sorry, form submission is not verified.';
                exit;
            }
            
            $current_user = wp_get_current_user();
            
            switch(strtoupper($_POST['kmi_submit']))
            {
                case 'REQUEST FOR RENTAL APPLICATION FORM':
                    $form_key = $this->Generate_Key();
                    
                    // Email recipient
                    $to = $current_user->user_email;
                    // Email subject
                    $subject = '['.get_option('blogname').'] Rental Application Form Request';
                    // Email message
                    $message = 'Thank you for the interest on renting our properties.'."\r\n\r\n";
                    $message .= 'To fill up the application form please visit the following address below, otherwise just ignore this email and nothing will happen.'."\r\n\r\n";
                    $message .= 'Here\'s the link of the application form you requested:'."\r\n\r\n";
                    $message .= esc_url($this->options['kmi_rental_application_form_url']).'?form='.$form_key."\r\n\r\n";
                    
                    // If successfully sent email and updated the new user rental application form key
                    if($this->Send_Email_Notification($to, $subject, $message) && update_user_meta($current_user->ID, 'rental_application_form_key', $form_key) === true)
                    {
                        // Set success message
                        $this->message['success']['kmi_application_request_form'] = 'Rental application form request successfully sent. Please check your email for the application form link.';
                    }
                    else
                    {
                        // Set error message
                        $this->message['error']['kmi_application_request_form'] = 'Sorry, we are unable to process your rental application form request. Please try again.';
                    }
                    
                    break;
                case 'SUBMIT RENTAL APPLICATION FORM':
                    if(isset($_POST['kmi_rental_terms_conditions']))
                    {
                        foreach($_POST as $key => $value)
                        {
                            if(strpos($key, 'kmi_') === false || strpos($key, 'nonce_field') !== false || strpos($key, 'http_referer') !== false || strpos($key, 'submit') !== false)
                                continue;
                            
                            if(empty($value))
                            {
                                $this->message['error']['kmi_application_submit_form'] .= 'Please fill up all the form fields.';
                                break;
                            }
                            
                            update_user_meta($current_user->ID, sanitize_text_field($key), sanitize_text_field($value));
                        }
                        
                        $this->message['success']['kmi_application_submit_form'] .= 'Congratulations you\'re application form was successfully queued into the waiting list.';
                        
                        if(empty($current_user->kmi_rental_timestamp) || strtolower($current_user->kmi_rental_terms_conditions) !== 'yes')
                        {
                            // Set timezone first
                            date_default_timezone_set('America/Denver');
                            // Get current timestamp
                            $timestamp = date('Y-m-d H:i:s');
                            // Add timestamp to the user's rental application data
                            update_user_meta($current_user->ID, 'kmi_rental_timestamp', $timestamp);
                        }
                    }
                    else
                        $this->message['error']['kmi_application_submit_form'] = 'You need to agree on the terms and conditions of this application form.';
                    
                    break;
            }
        }
    }
    
    /*
     * Rental application request form UI
     */
    public function Rental_Application_Request_Form()
    {
        if(is_user_logged_in()):
            if(!empty($this->message['success']['kmi_application_request_form']))
            {
                ?><p class="success"><?php echo $this->message['success']['kmi_application_request_form']; ?></p><?php
                $this->message['success']['kmi_application_request_form'] = '';
            }
            else if(!empty($this->message['error']['kmi_application_request_form']))
            {
                ?><p class="error"><?php echo $this->message['error']['kmi_application_request_form']; ?></p><?php
                $this->message['error']['kmi_application_request_form'] = '';
            }
            ?>
            <form method="POST" action="">
                <?php wp_nonce_field('kmi_application_request_form', 'kmi_nonce_field'); ?>
                <input type="submit" name="kmi_submit" value="Request for Rental Application Form" />
            </form>
            <?php
        else:
            ?>
            <h3>You need to <a href="#" data-toggle="collapse" data-target="#login-panel">login</a> first. 
                If you don't have an account yet you can register <a href="<?php echo site_url('/register/'); ?>">here</a>.
            </h3>
            <?php
        endif;
    }
    
    /*
     * Rental application form UI
     */
    public function Rental_Application_Form()
    {
        global $current_user;
        
        get_currentuserinfo();
        
        $form = !empty($_GET['form']) ? sanitize_text_field($_GET['form']) : '';
        
        $user_form = get_user_meta($current_user->ID, 'rental_application_form_key', true);
        
        if(!is_user_logged_in()):
            ?>
            <h3>You need to <a href="#" data-toggle="collapse" data-target="#login-panel">login</a> first. 
                If you don't have an account yet you can register <a href="<?php echo site_url('/register/'); ?>">here</a>.
            </h3>
            <?php
        elseif(empty($form) || $form !== $user_form):
            ?>
            <h3>
                Sorry, you have an invalid form. Make sure to follow the link sent to you via email upon your form request. 
                Otherwise you can make a request again <a href="<?php echo esc_url($this->general_settings['kmi_rental_application_request_url']); ?>">here</a>.
            </h3>
            <?php
        else:
            if(!empty($this->message['error']['kmi_application_submit_form']))
            {
                ?><p class="error"><?php echo $this->message['error']['kmi_application_submit_form']; ?></p><?php
                $this->message['error']['kmi_application_submit_form'] = '';
            }
            else if(!empty($this->message['success']['kmi_application_submit_form']))
            {
                ?><p class="success"><?php echo $this->message['success']['kmi_application_submit_form']; ?></p><?php
                $this->message['success']['kmi_application_submit_form'] = '';
            }
            
            $selected_house = !empty($_POST['kmi_rental_house_address']) ? sanitize_text_field($_POST['kmi_rental_house_address']) : $current_user->kmi_rental_house_address;
            $selected_rent_price = !empty($_POST['kmi_rental_rent']) ? sanitize_text_field($_POST['kmi_rental_rent']) : $current_user->kmi_rental_rent;
            $selected_current_state = !empty($_POST['kmi_rental_current_state']) ? sanitize_text_field($_POST['kmi_rental_current_state']) : $current_user->kmi_rental_current_state;
            $selected_previous_state = !empty($_POST['kmi_rental_previous_state']) ? sanitize_text_field($_POST['kmi_rental_previous_state']) : $current_user->kmi_rental_previous_state;
            $selected_vehicle_info_year_1 = !empty($_POST['kmi_rental_vehicle_info_year_1']) ? sanitize_text_field($_POST['kmi_rental_vehicle_info_year_1']) : $current_user->kmi_rental_vehicle_info_year_1;
            $selected_vehicle_info_year_2 = !empty($_POST['kmi_rental_vehicle_info_year_2']) ? sanitize_text_field($_POST['kmi_rental_vehicle_info_year_2']) : $current_user->kmi_rental_vehicle_info_year_2;
            
            $us_states = array(
                'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California',
                'Colorado', 'Connecticut', 'Delaware', 'Florida', 'Georgia',
                'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa',
                'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland',
                'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri',
                'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey',
                'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio',
                'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina',
                'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont',
                'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming'
            );
            
            ?>
            <h5>Click <a href="<?php echo esc_url($this->general_settings['kmi_rental_application_url']); ?>" target="_blank">here</a> to view the application.</h5>
            <form method="POST" action="">
                <h2 class="align-center">Rental Application for Mansfield Properties</h2>
                <h4 class="align-center">Lehi, UT 84043</h4>
                <p style="color: red;">Notice:  All adult applicants (18 years or older) must complete a separate application for rental.<br/>Please  answer all areas completely.</p>
                <label class="bold">House Address:</label>
                <select name="kmi_rental_house_address" class="half-width">
                    <?php if(count($this->property_listings) > 0): ?>
                        <?php foreach($this->property_listings as $listing): ?>
                            <option <?php if(strtolower($selected_house) === strtolower($listing->post_title.', '.$listing->citystatezip_value)){echo 'selected="selected"';} ?>>
                                <?php echo $listing->post_title.', '.$listing->citystatezip_value; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <label class="bold">Rent / Month:</label>
                <select name="kmi_rental_rent" class="half-width">
                    <?php if(count($this->property_listings) > 0): ?>
                        <?php foreach($this->property_listings as $listing): ?>
                            <option <?php if(strtolower($selected_rent_price) === strtolower($listing->price_value)){echo 'selected="selected"';} ?>>
                                <?php echo $listing->price_value; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <!--<input type="text" name="kmi_rental_rent" class="half-width" value="<?php echo !empty($_POST['kmi_rental_rent']) ? sanitize_text_field($_POST['kmi_rental_rent']) : $current_user->kmi_rental_rent; ?>" />-->
                <label class="bold">Start Date:</label>
                <input type="text" id="kmi_rental_start_date" name="kmi_rental_start_date" class="half-width" value="<?php echo !empty($_POST['kmi_rental_start_date']) ? sanitize_text_field($_POST['kmi_rental_start_date']) : $current_user->kmi_rental_start_date; ?>" />
                <label class="bold">Referred By:</label>
                <input type="text" name="kmi_rental_referred_by" class="half-width" value="<?php echo !empty($_POST['kmi_rental_referred_by']) ? sanitize_text_field($_POST['kmi_rental_referred_by']) : $current_user->kmi_rental_referred_by; ?>" />
                <h3>Applicant Information</h3>
                <label class="bold">Last Name:</label>
                <input type="text" name="kmi_rental_last_name" class="half-width" value="<?php echo !empty($_POST['kmi_rental_last_name']) ? sanitize_text_field($_POST['kmi_rental_last_name']) : $current_user->kmi_rental_last_name; ?>" />
                <label class="bold">First Name:</label>
                <input type="text" name="kmi_rental_first_name" class="half-width" value="<?php echo !empty($_POST['kmi_rental_first_name']) ? sanitize_text_field($_POST['kmi_rental_first_name']) : $current_user->kmi_rental_first_name; ?>" />
                <label class="bold">Middle Initial:</label>
                <input type="text" name="kmi_rental_middle_initial" class="half-width" value="<?php echo !empty($_POST['kmi_rental_middle_initial']) ? sanitize_text_field($_POST['kmi_rental_middle_initial']) : $current_user->kmi_rental_middle_initial; ?>" />
                <label class="bold">SSN:</label>
                <input type="text" name="kmi_rental_ssn" class="half-width" value="<?php echo !empty($_POST['kmi_rental_ssn']) ? sanitize_text_field($_POST['kmi_rental_ssn']) : $current_user->kmi_rental_ssn; ?>" />
                <label class="bold">Driver's License Number:</label>
                <input type="text" name="kmi_rental_drivers_license_number" class="half-width" value="<?php echo !empty($_POST['kmi_rental_drivers_license_number']) ? sanitize_text_field($_POST['kmi_rental_drivers_license_number']) : $current_user->kmi_rental_drivers_license_number; ?>" />
                <label class="bold">Birth Date:</label>
                <input type="text" id="kmi_rental_birthdate" name="kmi_rental_birthdate" class="half-width" value="<?php echo !empty($_POST['kmi_rental_birthdate']) ? sanitize_text_field($_POST['kmi_rental_birthdate']) : $current_user->kmi_rental_birthdate; ?>" />
                <label class="bold">Home Phone: Sample [(123) 456-7890]</label>
                <input type="text" name="kmi_rental_home_phone" class="half-width" value="<?php echo !empty($_POST['kmi_rental_home_phone']) ? sanitize_text_field($_POST['kmi_rental_home_phone']) : $current_user->kmi_rental_home_phone; ?>" />
                <label class="bold">Work Phone: Sample [(123) 456-7890]</label>
                <input type="text" name="kmi_rental_work_phone" class="half-width" value="<?php echo !empty($_POST['kmi_rental_work_phone']) ? sanitize_text_field($_POST['kmi_rental_work_phone']) : $current_user->kmi_rental_work_phone; ?>" />
                <label class="bold">Email Address:</label>
                <input type="email" name="kmi_rental_email" class="half-width" value="<?php echo !empty($_POST['kmi_rental_email']) ? sanitize_text_field($_POST['kmi_rental_email']) : $current_user->kmi_rental_email; ?>" />
                <h3>Current Address</h3>
                <label class="bold">Street Address:</label>
                <input type="text" name="kmi_rental_current_street_address" class="half-width" value="<?php echo !empty($_POST['kmi_rental_current_street_address']) ? sanitize_text_field($_POST['kmi_rental_current_street_address']) : $current_user->kmi_rental_current_street_address; ?>" />
                <label class="bold">City:</label>
                <input type="text" name="kmi_rental_current_city" class="half-width" value="<?php echo !empty($_POST['kmi_rental_current_city']) ? sanitize_text_field($_POST['kmi_rental_current_city']) : $current_user->kmi_rental_current_city; ?>" />
                <label class="bold">State:</label>
                <select name="kmi_rental_current_state" class="half-width">
                    <?php foreach($us_states as $state): ?>
                    <option <?php if(strtolower($selected_current_state) == strtolower($state)){echo 'selected="selected"';} ?>>
                        <?php echo $state; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label class="bold">Zip:</label>
                <input type="text" name="kmi_rental_current_zip" class="half-width" value="<?php echo !empty($_POST['kmi_rental_current_zip']) ? sanitize_text_field($_POST['kmi_rental_current_zip']) : $current_user->kmi_rental_current_zip; ?>" />
                <label class="bold">Date In:</label>
                <input type="text" id="kmi_rental_current_date_in" name="kmi_rental_current_date_in" class="half-width" value="<?php echo !empty($_POST['kmi_rental_current_date_in']) ? sanitize_text_field($_POST['kmi_rental_current_date_in']) : $current_user->kmi_rental_current_date_in; ?>" />
                <label class="bold">Date Out:</label>
                <input type="text" id="kmi_rental_current_date_out" name="kmi_rental_current_date_out" class="half-width" value="<?php echo !empty($_POST['kmi_rental_current_date_out']) ? sanitize_text_field($_POST['kmi_rental_current_date_out']) : $current_user->kmi_rental_current_date_out; ?>" />
                <label class="bold">Landlord Name:</label>
                <input type="text" name="kmi_rental_current_landlord_name" class="half-width" value="<?php echo !empty($_POST['kmi_rental_current_landlord_name']) ? sanitize_text_field($_POST['kmi_rental_current_landlord_name']) : $current_user->kmi_rental_current_landlord_name; ?>" />
                <label class="bold">Landlord's Phone Number:</label>
                <input type="text" name="kmi_rental_current_landlord_phone_number" class="half-width" value="<?php echo !empty($_POST['kmi_rental_current_landlord_phone_number']) ? sanitize_text_field($_POST['kmi_rental_current_landlord_phone_number']) : $current_user->kmi_rental_current_landlord_phone_number; ?>" />
                <label class="bold">Monthly Rent: Sample [$100]</label>
                <input type="text" name="kmi_rental_current_monthly_rent" class="half-width" value="<?php echo !empty($_POST['kmi_rental_current_monthly_rent']) ? sanitize_text_field($_POST['kmi_rental_current_monthly_rent']) : $current_user->kmi_rental_current_monthly_rent; ?>" />
                <label class="bold">Reason for leaving:</label>
                <input type="text" name="kmi_rental_current_reason_for_leaving" class="half-width" value="<?php echo !empty($_POST['kmi_rental_current_reason_for_leaving']) ? sanitize_text_field($_POST['kmi_rental_current_reason_for_leaving']) : $current_user->kmi_rental_current_reason_for_leaving; ?>" />
                <h3>Previous Address</h3>
                <label class="bold">Street Address:</label>
                <input type="text" name="kmi_rental_previous_street_address" class="half-width" value="<?php echo !empty($_POST['kmi_rental_previous_street_address']) ? sanitize_text_field($_POST['kmi_rental_previous_street_address']) : $current_user->kmi_rental_previous_street_address; ?>" />
                <label class="bold">City:</label>
                <input type="text" name="kmi_rental_previous_city" class="half-width" value="<?php echo !empty($_POST['kmi_rental_previous_city']) ? sanitize_text_field($_POST['kmi_rental_previous_city']) : $current_user->kmi_rental_previous_city; ?>" />
                <label class="bold">State:</label>
                <select name="kmi_rental_previous_state" class="half-width">
                    <?php foreach($us_states as $state): ?>
                    <option <?php if(strtolower($selected_previous_state) == strtolower($state)){echo 'selected="selected"';} ?>>
                        <?php echo $state; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label class="bold">Zip:</label>
                <input type="text" name="kmi_rental_previous_zip" class="half-width" value="<?php echo !empty($_POST['kmi_rental_previous_zip']) ? sanitize_text_field($_POST['kmi_rental_previous_zip']) : $current_user->kmi_rental_previous_zip; ?>" />
                <label class="bold">Date In:</label>
                <input type="text" id="kmi_rental_previous_date_in" name="kmi_rental_previous_date_in" class="half-width" value="<?php echo !empty($_POST['kmi_rental_previous_date_in']) ? sanitize_text_field($_POST['kmi_rental_previous_date_in']) : $current_user->kmi_rental_previous_date_in; ?>" />
                <label class="bold">Date Out:</label>
                <input type="text" id="kmi_rental_previous_date_out" name="kmi_rental_previous_date_out" class="half-width" value="<?php echo !empty($_POST['kmi_rental_previous_date_out']) ? sanitize_text_field($_POST['kmi_rental_previous_date_out']) : $current_user->kmi_rental_previous_date_out; ?>" />
                <label class="bold">Landlord Name:</label>
                <input type="text" name="kmi_rental_previous_landlord_name" class="half-width" value="<?php echo !empty($_POST['kmi_rental_previous_landlord_name']) ? sanitize_text_field($_POST['kmi_rental_previous_landlord_name']) : $current_user->kmi_rental_previous_landlord_name; ?>" />
                <label class="bold">Landlord's Phone Number:</label>
                <input type="text" name="kmi_rental_previous_landlord_phone_number" class="half-width" value="<?php echo !empty($_POST['kmi_rental_previous_landlord_phone_number']) ? sanitize_text_field($_POST['kmi_rental_previous_landlord_phone_number']) : $current_user->kmi_rental_previous_landlord_phone_number; ?>" />
                <label class="bold">Monthly Rent: Sample [$100]</label>
                <input type="text" name="kmi_rental_previous_monthly_rent" class="half-width" value="<?php echo !empty($_POST['kmi_rental_previous_monthly_rent']) ? sanitize_text_field($_POST['kmi_rental_previous_monthly_rent']) : $current_user->kmi_rental_previous_monthly_rent; ?>" />
                <label class="bold">Reason for leaving:</label>
                <input type="text" name="kmi_rental_previous_reason_for_leaving" class="half-width" value="<?php echo !empty($_POST['kmi_rental_previous_reason_for_leaving']) ? sanitize_text_field($_POST['kmi_rental_previous_reason_for_leaving']) : $current_user->kmi_rental_previous_reason_for_leaving; ?>" />
                <h3>Other Occupants</h3>
                <label class="bold">List Names and Birth Dates of All Additional Occupants 18 years or Older:<br/>Sample [John Doe | MM/DD/YY,]</label>
                <textarea name="kmi_rental_other_occupants_18_older" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_other_occupants_18_older']) ? sanitize_text_field($_POST['kmi_rental_other_occupants_18_older']) : $current_user->kmi_rental_other_occupants_18_older; ?></textarea>
                <label class="bold">List Names and Birth Dates of All Additional Occupants 17 years or Younger:<br/>Sample [John Doe | MM/DD/YY,]</label>
                <textarea name="kmi_rental_other_occupants_17_younger" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_other_occupants_17_younger']) ? sanitize_text_field($_POST['kmi_rental_other_occupants_17_younger']) : $current_user->kmi_rental_other_occupants_17_younger; ?></textarea>
                <h3>Pets</h3>
                <label class="bold">Pets? Sample [Pet | Describe,]</label>
                <textarea name="kmi_rental_pets" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_pets']) ? sanitize_text_field($_POST['kmi_rental_pets']) : $current_user->kmi_rental_pets; ?></textarea>
                <h3>Employment & Income Information</h3>
                <label class="bold">Occupation 1:</label>
                <input type="text" name="kmi_rental_occupation_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_occupation_1']) ? sanitize_text_field($_POST['kmi_rental_occupation_1']) : $current_user->kmi_rental_occupation_1; ?>" />
                <label class="bold">Employer / Company:</label>
                <input type="text" name="kmi_rental_employer_company_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_employer_company_1']) ? sanitize_text_field($_POST['kmi_rental_employer_company_1']) : $current_user->kmi_rental_employer_company_1; ?>" />
                <label class="bold">Monthly Salary: Sample [$100]</label>
                <input type="text" name="kmi_rental_monthly_salary_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_monthly_salary_1']) ? sanitize_text_field($_POST['kmi_rental_monthly_salary_1']) : $current_user->kmi_rental_monthly_salary_1; ?>" />
                <label class="bold">Supervisor's Name:</label>
                <input type="text" name="kmi_rental_supervisor_name_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_supervisor_name_1']) ? sanitize_text_field($_POST['kmi_rental_supervisor_name_1']) : $current_user->kmi_rental_supervisor_name_1; ?>" />
                <label class="bold">Supervisor's Phone:</label>
                <input type="text" name="kmi_rental_supervisor_phone_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_supervisor_phone_1']) ? sanitize_text_field($_POST['kmi_rental_supervisor_phone_1']) : $current_user->kmi_rental_supervisor_phone_1; ?>" />
                <label class="bold">Start Date:</label>
                <input type="text" id="kmi_rental_occupation_start_date_1" name="kmi_rental_occupation_start_date_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_occupation_start_date_1']) ? sanitize_text_field($_POST['kmi_rental_occupation_start_date_1']) : $current_user->kmi_rental_occupation_start_date_1; ?>" />
                <label class="bold">End Date:</label>
                <input type="text" id="kmi_rental_occupation_end_date_1" name="kmi_rental_occupation_end_date_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_occupation_end_date_1']) ? sanitize_text_field($_POST['kmi_rental_occupation_end_date_1']) : $current_user->kmi_rental_occupation_end_date_1; ?>" />
                <label class="bold">Occupation 2:</label>
                <input type="text" name="kmi_rental_occupation_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_occupation_2']) ? sanitize_text_field($_POST['kmi_rental_occupation_2']) : $current_user->kmi_rental_occupation_2; ?>" />
                <label class="bold">Employer / Company:</label>
                <input type="text" name="kmi_rental_employer_company_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_employer_company_2']) ? sanitize_text_field($_POST['kmi_rental_employer_company_2']) : $current_user->kmi_rental_employer_company_2; ?>" />
                <label class="bold">Monthly Salary: Sample [$100]</label>
                <input type="text" name="kmi_rental_monthly_salary_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_monthly_salary_2']) ? sanitize_text_field($_POST['kmi_rental_monthly_salary_2']) : $current_user->kmi_rental_monthly_salary_2; ?>" />
                <label class="bold">Supervisor's Name:</label>
                <input type="text" name="kmi_rental_supervisor_name_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_supervisor_name_2']) ? sanitize_text_field($_POST['kmi_rental_supervisor_name_2']) : $current_user->kmi_rental_supervisor_name_2; ?>" />
                <label class="bold">Supervisor's Phone:</label>
                <input type="text" name="kmi_rental_supervisor_phone_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_supervisor_phone_2']) ? sanitize_text_field($_POST['kmi_rental_supervisor_phone_2']) : $current_user->kmi_rental_supervisor_phone_2; ?>" />
                <label class="bold">Start Date:</label>
                <input type="text" id="kmi_rental_occupation_start_date_2" name="kmi_rental_occupation_start_date_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_occupation_start_date_2']) ? sanitize_text_field($_POST['kmi_rental_occupation_start_date_2']) : $current_user->kmi_rental_occupation_start_date_2; ?>" />
                <label class="bold">End Date:</label>
                <input type="text" id="kmi_rental_occupation_end_date_2" name="kmi_rental_occupation_end_date_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_occupation_end_date_2']) ? sanitize_text_field($_POST['kmi_rental_occupation_end_date_2']) : $current_user->kmi_rental_occupation_end_date_2; ?>" />
                <label class="bold">1. Other Income Description:</label>
                <input type="text" name="kmi_rental_other_income_description_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_other_income_description_1']) ? sanitize_text_field($_POST['kmi_rental_other_income_description_1']) : $current_user->kmi_rental_other_income_description_1; ?>" />
                <label class="bold">Monthly Salary: Sample [$100]</label>
                <input type="text" name="kmi_rental_other_income_monthly_salary_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_other_income_monthly_salary_1']) ? sanitize_text_field($_POST['kmi_rental_other_income_monthly_salary_1']) : $current_user->kmi_rental_other_income_monthly_salary_1; ?>" />
                <label class="bold">2. Other Income Description:</label>
                <input type="text" name="kmi_rental_other_income_description_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_other_income_description_2']) ? sanitize_text_field($_POST['kmi_rental_other_income_description_2']) : $current_user->kmi_rental_other_income_description_2; ?>" />
                <label class="bold">Monthly Salary: Sample [$100]</label>
                <input type="text" name="kmi_rental_other_income_monthly_salary_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_other_income_monthly_salary_2']) ? sanitize_text_field($_POST['kmi_rental_other_income_monthly_salary_2']) : $current_user->kmi_rental_other_income_monthly_salary_2; ?>" />
                <h3>Emergency Contact</h3>
                <label class="bold">1. Name:</label>
                <input type="text" name="kmi_rental_emergency_contact_name_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_emergency_contact_name_1']) ? sanitize_text_field($_POST['kmi_rental_emergency_contact_name_1']) : $current_user->kmi_rental_emergency_contact_name_1; ?>" />
                <label class="bold">Address:</label>
                <input type="text" name="kmi_rental_emergency_contact_address_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_emergency_contact_address_1']) ? sanitize_text_field($_POST['kmi_rental_emergency_contact_address_1']) : $current_user->kmi_rental_emergency_contact_address_1; ?>" />
                <label class="bold">Phone: Sample [(123) 456-7890]</label>
                <input type="text" name="kmi_rental_emergency_contact_phone_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_emergency_contact_phone_1']) ? sanitize_text_field($_POST['kmi_rental_emergency_contact_phone_1']) : $current_user->kmi_rental_emergency_contact_phone_1; ?>" />
                <label class="bold">Relationship:</label>
                <input type="text" name="kmi_rental_emergency_contact_relationship_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_emergency_contact_relationship_1']) ? sanitize_text_field($_POST['kmi_rental_emergency_contact_relationship_1']) : $current_user->kmi_rental_emergency_contact_relationship_1; ?>" />
                <label class="bold">2. Name:</label>
                <input type="text" name="kmi_rental_emergency_contact_name_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_emergency_contact_name_2']) ? sanitize_text_field($_POST['kmi_rental_emergency_contact_name_2']) : $current_user->kmi_rental_emergency_contact_name_2; ?>" />
                <label class="bold">Address:</label>
                <input type="text" name="kmi_rental_emergency_contact_address_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_emergency_contact_address_2']) ? sanitize_text_field($_POST['kmi_rental_emergency_contact_address_2']) : $current_user->kmi_rental_emergency_contact_address_2; ?>" />
                <label class="bold">Phone: Sample [(123) 456-7890]</label>
                <input type="text" name="kmi_rental_emergency_contact_phone_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_emergency_contact_phone_2']) ? sanitize_text_field($_POST['kmi_rental_emergency_contact_phone_2']) : $current_user->kmi_rental_emergency_contact_phone_2; ?>" />
                <label class="bold">Relationship:</label>
                <input type="text" name="kmi_rental_emergency_contact_relationship_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_emergency_contact_relationship_2']) ? sanitize_text_field($_POST['kmi_rental_emergency_contact_relationship_2']) : $current_user->kmi_rental_emergency_contact_relationship_2; ?>" />
                <h3>Personal References</h3>
                <label class="bold">1. Name:</label>
                <input type="text" name="kmi_rental_personal_reference_name_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_name_1']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_name_1']) : $current_user->kmi_rental_personal_reference_name_1; ?>" />
                <label class="bold">Address:</label>
                <input type="text" name="kmi_rental_personal_reference_address_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_address_1']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_address_1']) : $current_user->kmi_rental_personal_reference_address_1; ?>" />
                <label class="bold">Phone: Sample [(123) 456-7890]</label>
                <input type="text" name="kmi_rental_personal_reference_phone_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_phone_1']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_phone_1']) : $current_user->kmi_rental_personal_reference_phone_1; ?>" />
                <label class="bold">Relationship:</label>
                <input type="text" name="kmi_rental_personal_reference_relationship_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_relationship_1']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_relationship_1']) : $current_user->kmi_rental_personal_reference_relationship_1; ?>" />
                <label class="bold">2. Name:</label>
                <input type="text" name="kmi_rental_personal_reference_name_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_name_2']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_name_2']) : $current_user->kmi_rental_personal_reference_name_2; ?>" />
                <label class="bold">Address:</label>
                <input type="text" name="kmi_rental_personal_reference_address_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_address_2']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_address_2']) : $current_user->kmi_rental_personal_reference_address_2; ?>" />
                <label class="bold">Phone: Sample [(123) 456-7890]</label>
                <input type="text" name="kmi_rental_personal_reference_phone_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_phone_2']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_phone_2']) : $current_user->kmi_rental_personal_reference_phone_2; ?>" />
                <label class="bold">Relationship:</label>
                <input type="text" name="kmi_rental_personal_reference_relationship_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_relationship_2']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_relationship_2']) : $current_user->kmi_rental_personal_reference_relationship_2; ?>" />
                <label class="bold">3. Name:</label>
                <input type="text" name="kmi_rental_personal_reference_name_3" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_name_3']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_name_3']) : $current_user->kmi_rental_personal_reference_name_3; ?>" />
                <label class="bold">Address:</label>
                <input type="text" name="kmi_rental_personal_reference_address_3" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_address_3']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_address_3']) : $current_user->kmi_rental_personal_reference_address_3; ?>" />
                <label class="bold">Phone: Sample [(123) 456-7890]</label>
                <input type="text" name="kmi_rental_personal_reference_phone_3" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_phone_3']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_phone_3']) : $current_user->kmi_rental_personal_reference_phone_3; ?>" />
                <label class="bold">Relationship:</label>
                <input type="text" name="kmi_rental_personal_reference_relationship_3" class="half-width" value="<?php echo !empty($_POST['kmi_rental_personal_reference_relationship_3']) ? sanitize_text_field($_POST['kmi_rental_personal_reference_relationship_3']) : $current_user->kmi_rental_personal_reference_relationship_3; ?>" />
                <h3>BACKGROUND INFORMATION - Have you ever:</h3>
                <label class="bold">Filed for Bankruptcy?</label>
                <textarea name="kmi_rental_bankruptcy" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_bankruptcy']) ? sanitize_text_field($_POST['kmi_rental_bankruptcy']) : $current_user->kmi_rental_bankruptcy; ?></textarea>
                <label class="bold">Willfully or intentionally refused to pay rent when due?</label>
                <textarea name="kmi_rental_refuse_to_pay_rent" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_refuse_to_pay_rent']) ? sanitize_text_field($_POST['kmi_rental_refuse_to_pay_rent']) : $current_user->kmi_rental_refuse_to_pay_rent; ?></textarea>
                <label class="bold">Been evicted from a tenancy or left owing money? (Yes / No) If yes, please provide Property Name,City,State and Landlord Name</label>
                <textarea name="kmi_rental_evicted" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_evicted']) ? sanitize_text_field($_POST['kmi_rental_evicted']) : $current_user->kmi_rental_evicted; ?></textarea>
                <label class="bold">Been convicted of a crime? (Yes / No) If yes, please provide Type of Offense,County and State</label>
                <textarea name="kmi_rental_convicted_of_crime" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_convicted_of_crime']) ? sanitize_text_field($_POST['kmi_rental_convicted_of_crime']) : $current_user->kmi_rental_convicted_of_crime; ?></textarea>
                <label class="bold">Any judgements or liens? If yes, please list, explain and amount.</label>
                <textarea  name="kmi_rental_judgements_liens"class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_judgements_liens']) ? sanitize_text_field($_POST['kmi_rental_judgements_liens']) : $current_user->kmi_rental_judgements_liens; ?></textarea>
                <label class="bold">Please list all names you have personally used: (like: maiden/married names, initials, etc.)</label>
                <textarea name="kmi_rental_names_used" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_names_used']) ? sanitize_text_field($_POST['kmi_rental_names_used']) : $current_user->kmi_rental_names_used; ?></textarea>
                <h3>VEHICLE INFORMATION</h3>
                <label class="bold">1. Make & Model</label>
                <input type="text" name="kmi_rental_vehicle_info_model_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_vehicle_info_model_1']) ? sanitize_text_field($_POST['kmi_rental_vehicle_info_model_1']) : $current_user->kmi_rental_vehicle_info_model_1; ?>" />
                <label class="bold">Year:</label>
                <select name="kmi_rental_vehicle_info_year_1" class="half-width">
                    <?php for($i = date('Y'); $i >= 1905; $i--): ?>
                        <option <?php if($i == $selected_vehicle_info_year_1) {echo 'selected="selected"';} ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
               <label class="bold">License Number and State:</label>
                <input type="text" name="kmi_rental_vehicle_info_license_number_state_1" class="half-width" value="<?php echo !empty($_POST['kmi_rental_vehicle_info_license_number_state_1']) ? sanitize_text_field($_POST['kmi_rental_vehicle_info_license_number_state_1']) : $current_user->kmi_rental_vehicle_info_license_number_state_1; ?>" />
                <label class="bold">2. Make & Model</label>
                <input type="text" name="kmi_rental_vehicle_info_model_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_vehicle_info_model_2']) ? sanitize_text_field($_POST['kmi_rental_vehicle_info_model_2']) : $current_user->kmi_rental_vehicle_info_model_2; ?>" />
                <label class="bold">Year:</label>
                <select name="kmi_rental_vehicle_info_year_2" class="half-width">
                    <?php for($i = date('Y'); $i >= 1905; $i--): ?>
                        <option <?php if($i == $selected_vehicle_info_year_2) {echo 'selected="selected"';} ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <label class="bold">License Number and State:</label>
                <input type="text" name="kmi_rental_vehicle_info_license_number_state_2" class="half-width" value="<?php echo !empty($_POST['kmi_rental_vehicle_info_license_number_state_2']) ? sanitize_text_field($_POST['kmi_rental_vehicle_info_license_number_state_2']) : $current_user->kmi_rental_vehicle_info_license_number_state_2; ?>" />
                <label class="bold">Other Vehicles:</label>
                <textarea name="kmi_rental_vehicle_info_other" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_vehicle_info_other']) ? sanitize_text_field($_POST['kmi_rental_vehicle_info_other']) : $current_user->kmi_rental_vehicle_info_other; ?></textarea>
                <h3>OTHER INFORMATION</h3>
                <label class="bold">How did you hear about this property?</label>
                <textarea name="kmi_rental_hear_the_property" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_hear_the_property']) ? sanitize_text_field($_POST['kmi_rental_hear_the_property']) : $current_user->kmi_rental_hear_the_property; ?></textarea>
                <label class="bold">Please include any other information you believe would help to evaluate this application.</label>
                <textarea name="kmi_rental_other_info_to_evaluate_application" class="half-width vertical-resize"><?php echo !empty($_POST['kmi_rental_other_info_to_evaluate_application']) ? sanitize_text_field($_POST['kmi_rental_other_info_to_evaluate_application']) : $current_user->kmi_rental_other_info_to_evaluate_application; ?></textarea>
                <p>I, the undersigned, authorize Mansfield Properties (Kim and/or Julie Mansfield), owners and its agents to obtain an investigative consumer credit report including but not limited to credit history, OFAC search, landlord/tenant court record search, criminal record search and registered sex offender search.  I authorize the release of information from previous or current landlords, employers and bank representatives.  This investigation is for resident screening purposes only and is strictly confidential.  This report contains information compiled from sources believed to be reliable, but the accuracy of which cannot be guaranteed.  I hereby hold Mansfield Properties (Kim and/or Julie Mansfield), owners and its agents free and harmless of any liability for any damages arising out of any improper use of this information.  I understand that a nonrefundable fee of $20 with this application is for the background check.</p>
                <label class="bold"><input type="checkbox" name="kmi_rental_terms_conditions" value="yes" <?php echo isset($_POST['kmi_rental_terms_conditions']) ? 'checked="checked"' : ''; ?> /> I agree to the terms and conditions.</label>
                <?php wp_nonce_field('kmi_application_submit_form', 'kmi_nonce_field'); ?>
                <br/><input type="submit" name="kmi_submit" value="Submit Rental Application Form" />
            </form>
        <?php endif;
    }
    
    /*
     * Rental application data table view
     */
    public function Rental_Application_View()
    {
        global $current_user;
        
        get_currentuserinfo();
        
        $user = !empty($_GET['id']) && current_user_can('manage_options') ? get_userdata(absint($_GET['id'])) : $current_user;
        
        if(is_user_logged_in()):
        ?>
            <h2 class="align-center">Rental Application for Mansfield Properties</h2>
            <h4 class="align-center">Lehi, UT 84043</h4>
            <table class="kmi-rental-application full-width">
                <tr>
                    <th class="bg-grey align-left">House Address:</th>
                    <th class="bg-grey align-left">Rent:</th>
                    <th class="bg-grey align-left">Start Date:</th>
                    <th class="bg-grey align-left">Referred By:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_house_address; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_rent; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_start_date; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_referred_by; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bolder align-center">APPLICANT INFORMATION</th>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Last Name:</th>
                    <th class="bg-grey align-left">First Name:</th>
                    <th class="bg-grey align-left">Middle Initial:</th>
                    <th class="bg-grey align-left">SSN:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_last_name; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_first_name; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_middle_initial; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_ssn; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Driver's License Number:</th>
                    <th class="bg-grey align-left">Birth Date:</th>
                    <th class="bg-grey align-left">Home Phone:</th>
                    <th class="bg-grey align-left">Work Phone:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_drivers_license_number; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_birthdate; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_home_phone; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_work_phone; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Email:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_email; ?>
                    </td>
                </tr>
                 <tr>
                    <th colspan="4" class="bolder align-center">CURRENT ADDRESS</th>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Street Address:</th>
                    <th class="bg-grey align-left">City:</th>
                    <th class="bg-grey align-left">State:</th>
                    <th class="bg-grey align-left">Zip:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_current_street_address; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_current_city; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_current_state; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_current_zip; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Date In:</th>
                    <th class="bg-grey align-left">Date Out:</th>
                    <th class="bg-grey align-left">Landlord Name:</th>
                    <th class="bg-grey align-left">Landlonrd Phone Number:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_current_date_in; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_current_date_out; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_current_landlord_name; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_current_landlord_phone_number; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Monthly Rent:</th>
                    <th class="bg-grey align-left">Reason For Leaving:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_current_monthly_rent; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_current_reason_for_leaving; ?>
                    </td>
                </tr>
                 <tr>
                    <th colspan="4" class="bolder align-center">PREVIOUS ADDRESS</th>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Street Address:</th>
                    <th class="bg-grey align-left">City:</th>
                    <th class="bg-grey align-left">State:</th>
                    <th class="bg-grey align-left">Zip:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_previous_street_address; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_previous_city; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_previous_state; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_previous_zip; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Date In:</th>
                    <th class="bg-grey align-left">Date Out:</th>
                    <th class="bg-grey align-left">Landlord Name:</th>
                    <th class="bg-grey align-left">Landlonrd Phone Number:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_previous_date_in; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_previous_date_out; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_previous_landlord_name; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_previous_landlord_phone_number; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Monthly Rent:</th>
                    <th class="bg-grey align-left">Reason For Leaving:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_previous_monthly_rent; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_previous_reason_for_leaving; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bolder align-center">OTHER OCCUPANTS</th>
                </tr>
                <tr>
                    <th colspan="4" class="bg-grey align-left">List Names and Birth Dates of <span style="color: red;">All Additional Occupants 18 years or Older</span>:</th>
                </tr>
                <tr>
                    <td colspan="4" class="quarter-width">
                        <?php echo $user->kmi_rental_other_occupants_18_older; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bg-grey align-left">List Names and Birth Dates of <span style="color: red;">All Additional Occupants 17 years or Younger</span>:</th>
                </tr>
                <tr>
                    <td colspan="4" class="quarter-width">
                        <?php echo $user->kmi_rental_other_occupants_17_younger; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bolder align-center">PETS</th>
                </tr>
                <tr>
                    <th colspan="4" class="bg-grey align-left">Pets | Description:</th>
                </tr>
                <tr>
                    <td colspan="4" class="quarter-width">
                        <?php echo $user->kmi_rental_pets; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bolder align-center">EMPLOYMENT AND INCOME INFORMATION</th>
                </tr>
                <tr>
                    <th colspan="2" class="bg-grey align-left">1. Occupation:</th>
                    <th class="bg-grey align-left">Employer / Company:</th>
                    <th class="bg-grey align-left">Monthly Salary:</th>
                </tr>
                <tr>
                    <td colspan="2" class="quarter-width">
                        <?php echo $user->kmi_rental_occupation_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_employer_company_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_monthly_salary_1; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Supervisor Name:</th>
                    <th class="bg-grey align-left">Supervisor Phone Number:</th>
                    <th class="bg-grey align-left">Start Date:</th>
                    <th class="bg-grey align-left">End Date:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_supervisor_name_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_supervisor_phone_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_occupation_start_date_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_occupation_end_date_1; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="2" class="bg-grey align-left">2. Occupation:</th>
                    <th class="bg-grey align-left">Employer / Company:</th>
                    <th class="bg-grey align-left">Monthly Salary:</th>
                </tr>
                <tr>
                    <td colspan="2" class="quarter-width">
                        <?php echo $user->kmi_rental_occupation_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_employer_company_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_monthly_salary_2; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">Supervisor Name:</th>
                    <th class="bg-grey align-left">Supervisor Phone Number:</th>
                    <th class="bg-grey align-left">Start Date:</th>
                    <th class="bg-grey align-left">End Date:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_supervisor_name_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_supervisor_phone_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_occupation_start_date_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_occupation_end_date_2; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="3" class="bg-grey align-left">1. Other Income Description:</th>
                    <th class="bg-grey align-left">Monthly Income:</th>
                </tr>
                <tr>
                    <td colspan="3" class="quarter-width">
                        <?php echo $user->kmi_rental_other_income_description_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_other_income_monthly_salary_1; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="3" class="bg-grey align-left">2. Other Income Description:</th>
                    <th class="bg-grey align-left">Monthly Income:</th>
                </tr>
                <tr>
                    <td colspan="3" class="quarter-width">
                        <?php echo $user->kmi_rental_other_income_description_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_other_income_monthly_salary_2; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bolder align-center">EMERGENCY CONTACT</th>
                </tr>
                <tr>
                    <th class="bg-grey align-left">1. Name:</th>
                    <th class="bg-grey align-left">Address:</th>
                    <th class="bg-grey align-left">Phone:</th>
                    <th class="bg-grey align-left">Relationship:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_emergency_contact_name_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_emergency_contact_address_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_emergency_contact_phone_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_emergency_contact_relationship_1; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">2. Name:</th>
                    <th class="bg-grey align-left">Address:</th>
                    <th class="bg-grey align-left">Phone:</th>
                    <th class="bg-grey align-left">Relationship:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_emergency_contact_name_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_emergency_contact_address_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_emergency_contact_phone_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_emergency_contact_relationship_2; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bolder align-center">PERSONAL REFERENCES</th>
                </tr>
                <tr>
                    <th class="bg-grey align-left">1. Name:</th>
                    <th class="bg-grey align-left">Address:</th>
                    <th class="bg-grey align-left">Phone:</th>
                    <th class="bg-grey align-left">Relationship:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_name_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_address_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_phone_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_relationship_1; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">2. Name:</th>
                    <th class="bg-grey align-left">Address:</th>
                    <th class="bg-grey align-left">Phone:</th>
                    <th class="bg-grey align-left">Relationship:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_name_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_address_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_phone_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_relationship_2; ?>
                    </td>
                </tr>
                <tr>
                    <th class="bg-grey align-left">3. Name:</th>
                    <th class="bg-grey align-left">Address:</th>
                    <th class="bg-grey align-left">Phone:</th>
                    <th class="bg-grey align-left">Relationship:</th>
                </tr>
                <tr>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_name_3; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_address_3; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_phone_3; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_personal_reference_relationship_3; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bolder align-center">BACKGROUND INFORMATION - Have you ever.</th>
                </tr>
                <tr>
                    <th colspan="2" class="bg-grey align-left">Filed for Bankruptcy?</th>
                    <th colspan="2" class="bg-grey align-left">Willfully or intentionally refused to pay rent when due?</th>
                </tr>
                <tr>
                    <td colspan="2" class="quarter-width">
                        <?php echo $user->kmi_rental_bankruptcy; ?>
                    </td>
                    <td colspan="2" class="quarter-width">
                        <?php echo $user->kmi_rental_refuse_to_pay_rent; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bg-grey align-left">Been evicted from a tenancy or left owing money? (Yes / No) If yes, please provide Property Name,City,State and Landlord Name</th>
                </tr>
                <tr>
                    <td colspan="4" class="quarter-width">
                        <?php echo $user->kmi_rental_evicted; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bg-grey align-left">Been convicted of a crime? (Yes / No) If yes, please provide Type of Offense,County and State</th>
                </tr>
                <tr>
                    <td colspan="4" class="quarter-width">
                        <?php echo $user->kmi_rental_convicted_of_crime; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bg-grey align-left">Any judgements or liens? (Yes / No) If yes, please list, explain and amount.</th>
                </tr>
                <tr>
                    <td colspan="4" class="quarter-width">
                        <?php echo $user->kmi_rental_judgements_liens; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bg-grey align-left">Please list all names you have personally used: (like: maiden/married names, initials, etc.)</th>
                </tr>
                <tr>
                    <td colspan="4" class="quarter-width">
                        <?php echo $user->kmi_rental_names_used; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bolder align-center">VEHICLE INFORMATION</th>
                </tr>
                <tr>
                    <th colspan="2" class="bg-grey align-left">1. Make and Model</th>
                    <th class="bg-grey align-left">Year</th>
                    <th class="bg-grey align-left">License Number and State</th>
                </tr>
                <tr>
                    <td colspan="2" class="quarter-width">
                        <?php echo $user->kmi_rental_vehicle_info_model_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_vehicle_info_year_1; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_vehicle_info_license_number_state_1; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="2" class="bg-grey align-left">2. Make and Model</th>
                    <th class="bg-grey align-left">Year</th>
                    <th class="bg-grey align-left">License Number and State</th>
                </tr>
                <tr>
                    <td colspan="2" class="quarter-width">
                        <?php echo $user->kmi_rental_vehicle_info_model_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_vehicle_info_year_2; ?>
                    </td>
                    <td class="quarter-width">
                        <?php echo $user->kmi_rental_vehicle_info_license_number_state_2; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bg-grey align-left">Other Vehicles:</th>
                </tr>
                <tr>
                    <td colspan="4" class="quarter-width">
                        <?php echo $user->kmi_rental_vehicle_info_other; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bolder align-center">OTHER INFORMATION</th>
                </tr>
                <tr>
                    <th colspan="4" class="bg-grey align-left">How did you hear about this property?</th>
                </tr>
                <tr>
                    <td colspan="4" class="quarter-width">
                        <?php echo $user->kmi_rental_hear_the_property; ?>
                    </td>
                </tr>
                <tr>
                    <th colspan="4" class="bg-grey align-left">Please include any other information you believe would help to evaluate this application.</th>
                </tr>
                <tr>
                    <td colspan="4" class="quarter-width">
                        <?php echo $user->kmi_rental_other_info_to_evaluate_application; ?>
                    </td>
                </tr>
            </table>
        <?php
        else:
            ?>
            <h3>You need to <a href="#" data-toggle="collapse" data-target="#login-panel">login</a> first. 
                If you don't have an account yet you can register <a href="<?php echo site_url('/register/'); ?>">here</a>.
            </h3>
            <?php
        endif;
    }
    
    /*
     * Adding css and scripts in to the front end pages
     */
    public function Add_Styles_And_Scripts()
    {
        if(!wp_style_is('kmi_global_style', 'registered'))
        {
            wp_register_style('kmi_global_style', plugins_url('css/global.css', __FILE__));
        }
        
        if(!wp_style_is('kmi_global_style', 'enqueued'))
        {
            wp_enqueue_style('kmi_global_style');
        }
        
        if(!wp_script_is('kmi_rental_application_form_script', 'registered'))
        {
            // Register the jquery ui css for the datepicker style
            wp_register_style('jquery_ui_css', 'http://code.jquery.com/ui/1.11.0/themes/smoothness/jquery-ui.css');
            // Register the script used for the rental application form
            wp_register_script('kmi_rental_application_form_script', plugins_url('js/rental-application-form.js', __FILE__), array('jquery', 'jquery-ui-datepicker'), false, true);
        }
        
        if(!wp_script_is('kmi_rental_application_form_script', 'enqueued'))
        {
            // Enqueue the jquery ui css
            wp_enqueue_style('jquery_ui_css');
            // Enqueue the rental application form script
            wp_enqueue_script('kmi_rental_application_form_script');
        }
    }
    
    /*
     * The header tabs in the rental application option page
     */
    private function Plugin_Options_Tabs()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $this->general_settings_key;
        
        screen_icon();
        
        echo '<h2 class="nav-tab-wrapper">';
	foreach($this->plugin_settings_tabs as $tab_key => $tab_caption)
        {
            $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
            echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->plugin_options_key . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';	
        }
	echo '</h2>';
    }
    
    private function Generate_Key($length=15)
    {
        return substr(md5(rand()), 0, $length);
    }
    
    private function Send_Email_Notification($to, $subject='KMI Rental Application', $message='Welcome to KMI Rental Application plugin.')
    {
        //send email notification message
        return wp_mail($to, $subject, $message);
    }
}
 
$kmi_rental_application = new KMI_Rental_Application();