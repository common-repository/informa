<?php
class InformaSettingsPage
{
	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	/**
	 * Start up
	 */
	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page()
	{
		// This page will be under "Settings"
		add_options_page(
		'Informa Settings Admin',
		'Informa Settings',
		'manage_options',
		'informa-setting-admin',
		array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page()
	{
		// Set class property
		$this->options = get_option( 'informa' );
		if ($this->options['cookieExpire']=='') $this->options['cookieExpire']=365;
		?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>Informa Plugin Settings</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'option_group' );   
                do_settings_sections( 'informa-setting-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'option_group', // Option group
            'informa', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'section_id', // ID
            'Informa DQS Parameters', // Title
            array( $this, 'print_section_info' ), // Callback
            'informa-setting-admin' // Page
        );  

        add_settings_field(
        'platform', // ID
        'Platform', // Title
        array( $this, 'platform_callback' ), // Callback
        'informa-setting-admin', // Page
        'section_id' // Section
        );

        add_settings_field(
        'client', // ID
        'Client Code', // Title
        array( $this, 'client_callback' ), // Callback
        'informa-setting-admin', // Page
        'section_id' // Section
        );
        
        add_settings_field(
        'user', // ID
        'Username', // Title
        array( $this, 'user_callback' ), // Callback
        'informa-setting-admin', // Page
        'section_id' // Section
        );        
        
        add_settings_field(
        'pass', // ID
        'Secret', // Title
        array( $this, 'pass_callback' ), // Callback
        'informa-setting-admin', // Page
        'section_id' // Section
        );        

        add_settings_field(
        'cookieExpire', // ID
        'Cookie Expiration', // Title
        array( $this, 'cookieExpire_callback' ), // Callback
        'informa-setting-admin', // Page
        'section_id' // Section
        );        
       
        add_settings_field(
        'defKey', // ID
        'Default Identifier', // Title
        array( $this, 'defKey_callback' ), // Callback
        'informa-setting-admin', // Page
        'section_id' // Section
        );        
    
        add_settings_field(
        'pageHistoryQS', // ID
        'Page Impression History Question Set', // Title
        array( $this, 'pageHistoryQS_callback' ), // Callback
        'informa-setting-admin', // Page
        'section_id' // Section
        );        
    
        add_settings_field(
        'redirect_src', // ID
        'Redirect pages ending in', // Title
        array( $this, 'redirect_src_callback' ), // Callback
        'informa-setting-admin', // Page
        'section_id' // Section
        );        
    
        add_settings_field(
        'dqs_redirect_q', // ID
        'Redirect to this DQS Question', // Title
        array( $this, 'dqs_redirect_q_callback' ), // Callback
        'informa-setting-admin', // Page
        'section_id' // Section
        );        
    
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
    	switch ($input['platform']){
    		case 'dev':
    		case 'build':
    		case 'test':
    		case 'uat':
    		case 'live':
    			break;
    		default: $input['platform'] = 'dev';
    	} 

        if( !empty( $input['client'] ) )
            $input['client'] = sanitize_text_field( $input['client'] );

        if( !empty( $input['user'] ) )
        	$input['user'] = sanitize_text_field( $input['user'] );        
        
    	switch ($input['defKey']){
    		case 'session':
    		case 'cookie':
    			break;
    		default: $input['defKey'] = '';
    	} 
        
    	if (!is_numeric( $input['cookieExpire'] )) $input['cookieExpire'] = 365;
    	
		// Remove any trailing '/' from redirect_src and 
		if ($input['redirect_src'][strlen($input['redirect_src'])-1]=='/') {
        	$input['redirect_src'] = substr($input['redirect_src'],0,strlen($input['redirect_src'])-1);
        }
        if ($input['dqs_redirect_q'][strlen($input['dqs_redirect_q'])-1]=='/') {
        	$input['dqs_redirect_q'] = substr($input['dqs_redirect_q'],0,strlen($input['dqs_redirect_q'])-1);
        }
        
       	return $input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Your <a href="http://www.alchemetrics.co.uk">Alchemetrics</a> account manager will provide you with these settings. Enter the informa settings below:';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function platform_callback()
    { 
        echo "<select id='platform' name='informa[platform]'>\n";
        foreach (array('dev','build','test','uat','live') as $pl) {
    		echo "<option value='{$pl}' "; 
    		if ($this->options['platform']==$pl) echo "selected='selected' "; 
    		echo ">{$pl}</option>\n";
        }
    	echo "</select>";
    }
    public function client_callback()
    {
    	printf(
    	'<input type="text" id="client" name="informa[client]" value="%s" />',
    	esc_attr( $this->options['client'])
    	);
    }
    public function user_callback()
    {
    	printf(
    	'<input type="text" id="user" name="informa[user]" value="%s" autocomplete="off" />',
    	esc_attr( $this->options['user'])
    	);
    }
    public function pass_callback()
    {
    	printf(
    	'<input type="password" id="pass" name="informa[pass]" value="%s" autocomplete="off" />',
    	esc_attr( $this->options['pass'])
    	);
    }
    
    public function cookieExpire_callback()
    {
    	printf(
    	'<input type="text" id="cookieExpire" name="informa[cookieExpire]" value="%s" />',
    	esc_attr( $this->options['cookieExpire'])
    	);
?>
<p>Enter the number of days DQS will remember a visitor before their unique identifier is forgotten. This defaults to 1 year.</p>
<?php 
	}
    public function defKey_callback()
    {
   /* 	printf(
    	'<input type="text" id="defKey" name="informa[defKey]" value="%s" />',
    	esc_attr( $this->options['defKey'])
    	); */
    	echo "<select id='defKey' name='informa[defKey]'>\n";
    	echo "<option value=''>None</option>";
    	echo "<option value='session' "; if ($this->options['defKey']=='session') echo "selected='selected' "; echo ">Session</option>\n";
    	echo "<option value='cookie' "; if ($this->options['defKey']=='cookie') echo "selected='selected' "; echo ">Cookie</option>\n";
    	echo "</select>";
?>
<p>If 'None' DQS will only serve blank forms by default. 'Session' will remember values until the browser closes, 'Cookie' will remember them longer. Specific DQS shortcodes can override this value with the "key" option.</p>
<?php 
    }
    
    public function pageHistoryQS_callback()
    {
    	printf(
    	'<input type="text" id="pageHistoryQS" name="informa[pageHistoryQS]" value="%s" />',
    	esc_attr( $this->options['pageHistoryQS'])
    	);
    	?>
    <p>If set, this contains a QuestionSet name in DQS that will be saved for every page viewed. The URL, date and time will be stored.</p>
    <?php 
	}
	public function redirect_src_callback()
	{
		printf(
		'<input type="text" id="redirect_src" name="informa[redirect_src]" value="%s" />',
		esc_attr( $this->options['redirect_src'])
		);
		?>
	    <p>If set, a page URI that ends with this string will be redirected based on the DQS Question below.</p>
	    <?php 
	}
	        
	public function dqs_redirect_q_callback()
	{
		printf(
		'<input type="text" id="dqs_redirect_q" name="informa[dqs_redirect_q]" value="%s" />',
		esc_attr( $this->options['dqs_redirect_q'])
		);
		?>
	    <p>If set, this DQS Question will be retrieved and used to redirect the matching page.</p>
	    <?php 
	}
		        
				
}

if( is_admin() )
    $settings_page = new InformaSettingsPage();
?>