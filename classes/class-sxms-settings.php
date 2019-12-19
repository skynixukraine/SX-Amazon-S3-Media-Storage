<?php
/**
 * @vendor Skynix LLC
 * @see https://skynix.co/
 * SX Media Storage Settings Class
 */

/**
 * Class SXMS_Settings
 */
class SXMS_Settings {

    /**
     * @var mixed|string
     */
	public $textdomain = '';

    /**
     * @var string
     */
	public $menu_slug = 'sxms_settings';

    /**
     * @var array
     */
	public $settings_fields;

    /**
     * @var \Aws\S3\S3Client|bool
     */
	public $s3 = false;

    /**
     * @var string
     */
	public $bucket_name = '';

    /**
     * SXMS_Settings constructor.
     * @param array $args
     */
	public function __construct( $args = [] ) {
		// Set up plugins textdomain
		if ( !empty( $args["textdomain"] ) ) $this->textdomain = $args["textdomain"];
		// Set up settigns fields
		$this->settings_fields = [
			"as3_bucket_name" => [
				"key"   => "sxms_as3_bucket_name",
				"label" => __( 'Amazon S3 bucket name', $this->textdomain ),
				"type"  => "text",
				"class" => "sxms-input sxms-text",
			],
			"as3_region" => [
				"key"   => "sxms_as3_region",
				"label" => __( 'Amazon S3 region', $this->textdomain ),
				"type"  => "text",
				"class" => "sxms-input sxms-text",
			],
			"as3_access_key" => [
				"key"   => "sxms_as3_access_key",
				"label" => __( 'Amazon S3 access key', $this->textdomain ),
                "type"  => "text",
                "class" => "sxms-input sxms-text"
			],
			"as3_secret_key" => [
				"key"   => "sxms_as3_secret_key",
				"label" => __( 'Amazon S3 secret key', $this->textdomain ),
				"type"  => "password",
				"class" => "sxms-input sxms-password",
			],
		];
        // Try setting connection with s3
		$this->s3 = $this->get_s3_instance();
	}

    /**
     *
     */
	public function init(){
		$plugin = basename( dirname( __FILE__ , 2 ) ) . '/init.php';
		// Register settings options
		add_action( 'admin_init', array( $this, 'sxms_settings_fields' ) );
		// Create settings subpage
		add_action( 'admin_menu', array( $this, 'sxms_add_settings_page' ) );
		// Add link to plugin settings page
		add_filter( "plugin_action_links_$plugin", array( $this, 'sxms_add_settings_link' ), 99, 1 );
	}

	/**
	 * Add "Settings" link to plugin description on plugins page
	 *
	 * @param $links
	 * @return mixed
	 */
	public function sxms_add_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=' . $this->menu_slug . '.php">' . __( 'Settings' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Register settings fields
	 */
	public function sxms_settings_fields(){
		foreach ( $this->settings_fields as $field => $values ) {
			register_setting( $this->menu_slug, $values["key"] );
		}
	}

	/**
	 * Add admin menu item
	 */
	public function sxms_add_settings_page(){
		add_options_page(
			__( "SX AS3 Media settings", $this->textdomain ),
			__( "SX AS3 Media", $this->textdomain ),
			"manage_options",
			$this->menu_slug . ".php",
			array( $this, 'sxms_settings_page_content' )
		);
	}

	/**
	 * Settings page content
	 */
	public function sxms_settings_page_content(){

		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div id="sxms-wrapper">
				<div id="post-body" class="metabox-holder columns-2">
					<div class="postbox">
						<div class="inside">
							<style>
								tr.ct-field th {
									padding-left: 15px;
								}

								tr.ct-field td.label {
                                    width: 170px;
								}

                                .sxms-input {
                                    width: 40%;
								}

								.form-table tr.ct-field:last-child>th,
								.form-table tr.ct-field:last-child>td {
									border-bottom: none
								}

								.ct-metabox tr.ct-field {
									border-top: 1px solid #ececec
								}

								.ct-metabox tr.ct-field:first-child {
									border-top: 0
								}

								@media screen and (max-width: 782px) {
									.ct-metabox tr.ct-field>th {
										padding-top: 15px
									}
									.ct-metabox tr.ct-field>td {
										padding-bottom: 15px
									}
								}
							</style>
							<form action="options.php" method="post">
								<table class="form-table ct-metabox">
									<?php foreach ( $this->settings_fields as $field => $values ) : ?>
									<tr class="ct-field">
										<td class="label">
											<label for="<?php echo $values["key"]; ?>" ><p><?php echo $values["label"]; ?>:</p></label>

											<?php if ( !empty( $values["description"] ) ) : ?>
											<i><p><?php echo __( $values["description"], $this->textdomain ); ?></p></i>
											<?php endif; ?>
                                        </td>
                                        <td class="field">
                                            <input type="<?php echo $values["type"]; ?>" class="<?php echo $values["class"]; ?>" value="<?php echo get_option( $values["key"] ); ?>" name="<?php echo $values["key"]; ?>" id="<?php echo $values["key"]; ?>" />
                                        </td>
									</tr>
									<?php endforeach; ?>
								</table>

								<?php
								settings_fields( 'sxms_settings' );
								// output save settings button
								submit_button( __( 'Save', $this->textdomain ) );
								?>
							</form>
                            <?php
                                if ( !empty( $this->s3 ) ) {
                                    echo "<h3 style='color:green;'>Connection established - credentials are correct.</h3>";
                                }
                            ?>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
		</div>
		<?php

	}

	/**
     * Create connection with s3 and return s3 client instance.
     * If credentials are incorrect or in case of error - return (bool)false;
     *
	 * @return \Aws\S3\S3Client|bool
	 */
	public function get_s3_instance() {
	    $as3_access_key  = get_option( $this->settings_fields["as3_access_key"]["key"] );
	    $as3_secret_key  = get_option( $this->settings_fields["as3_secret_key"]["key"] );
	    $as3_region      = get_option( $this->settings_fields["as3_region"]["key"] );
	    $as3_bucket_name = get_option( $this->settings_fields["as3_bucket_name"]["key"] );

	    if ( empty( $as3_access_key ) || empty( $as3_secret_key ) || empty( $as3_region ) || empty( $as3_bucket_name ) ) {
            return false;
        }

        $this->bucket_name = $as3_bucket_name;

		$credentials = new Aws\Credentials\Credentials( $as3_access_key, $as3_secret_key );

		$s3 = new Aws\S3\S3Client([
			'version'     => 'latest',
			'region'      => $as3_region,
			'credentials' => $credentials
		]);

		try {
			$bucket_exists = $this->bucketExists( $s3 );

			if ( ! $bucket_exists ) {
			    return false;
            }
		} catch (Aws\S3\Exception\S3Exception $e) {
			return false;
		}

		return $s3;
    }

	/**
	 * Check if bucket exists on s3
	 *
	 * @param $s3
	 * @return bool
	 */
	public function bucketExists( $s3 ) {
		// $client->doesBucketExist() returns false on denied access since 3.X, we don't want that
		try {
			$command = $s3->getCommand( 'HeadBucket', ['Bucket' => $this->bucket_name] );
			$s3->execute( $command );
			return true;
		} catch ( Aws\S3\Exception\S3Exception $e ) {
			if ( $e->getAwsErrorCode() == 'AccessDenied' || $e->getStatusCode() == 403 ) {
				return true;
			}
			if ($e->getStatusCode() >= 500 ) {
				throw $e;
			}
			return false;
		}
	}
}

