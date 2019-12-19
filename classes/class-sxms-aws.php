<?php
/**
 * @vendor Skynix LLC
 * @see https://skynix.co/
 * SX Media Storage AWS Class
 */

/**
 * Class SXMS_AWS
 */
class SXMS_AWS {

    /**
     * @var mixed|string
     */
	public $textdomain = '';

    /**
     * @var SXMS_Settings
     */
	public $settings;

    /**
     * @var \Aws\S3\S3Client|bool
     */
	public $s3;

    /**
     * SXMS_AWS constructor.
     * @param array $args
     */
	public function __construct( $args = [] ) {
		// Set up plugins textdomain
		if ( !empty( $args["textdomain"] ) ) $this->textdomain = $args["textdomain"];

		$this->settings = new SXMS_Settings(["textdomain" => $this->textdomain]);
		$this->s3 = $this->settings->s3;
	}

	/**
	 * Activate filters and action hooks
	 */
	public function init(){
		// Upload file to s3 when it is uploaded to media
		add_filter( 'wp_handle_upload', array( $this, "upload_media_hook" ), 1, 2 );
		// Delete file from s3 when it is deleted from media
		add_action( 'delete_attachment', array( $this, "delete_media_hook" ) );
		// Rewrite htaccess rules for sx s3 storage media
		add_action( 'init', array( $this, "rules_core_add" ) );
		// Activate filters and action hooks for plugin settings
		$this->settings->init();
	}

	/**
	 * Hook into "wp_handle_upload" filter
	 * When file uploaded to media - upload this file to s3 bucket
	 *
	 * @param $upload
	 * @param $context
	 *
	 * @return mixed
	 */
	public function upload_media_hook( $upload, $context ){
		if ( empty( $this->s3 ) ) {
			return $upload;
		}

		if ( is_array( $upload ) && !empty( $upload["file"] ) ) {
			$key = $this->generate_media_file_key_for_s3( $upload["file"] );

			if ( $key ) {

				if ( $this->file_exists_on_s3_bucket( $key ) ) {
					$this->remove_file_from_s3_bucket( $key );
				}

				$this->upload_file_to_s3_bucket( $key, $upload["file"] );
			}
		}

		return $upload;
	}

	/**
	 * Hook into "delete_attachment" action
	 * When media file is deleted - also delete the file on s3 bucket
	 *
	 * @param $attachment_id
	 */
	public function delete_media_hook( $attachment_id ) {
		if ( empty( $this->s3 ) ) {
			return;
		}

		$path = get_attached_file( $attachment_id, true );

		if ( $path ) {
			$key = $this->generate_media_file_key_for_s3( $path );

			if ( $key && $this->file_exists_on_s3_bucket( $key ) ) {
				$this->remove_file_from_s3_bucket( $key );
			}
		}
	}

	/**
	 * Generate s3 file key ( path to file and file name on s3 bucket )
	 *
	 * @param $path
	 *
	 * @return bool|string
	 */
	public function generate_media_file_key_for_s3( $path ) {
		if ( !empty( $path ) ) {
			$prefix = trailingslashit( WP_CONTENT_DIR );

			if ( substr( $path, 0, strlen( $prefix ) ) == $prefix ) {
				return substr( $path, strlen( $prefix ) );
			}
		}

		return "";
	}

	/**
	 * Check if file exists on s3 bucket
	 *
	 * @param $key - path to file on s3 bucket and file name
	 *
	 * @return bool
	 */
	public function file_exists_on_s3_bucket( $key ){
		// $client->doesObjectExist() returns false on denied access since 3.X, we don't want that
		try {
			$command = $this->s3->getCommand( 'HeadObject', [
					'Bucket' => $this->settings->bucket_name,
					'Key'    => $key
				] );
			$this->s3->execute( $command );
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

	/**
	 * Upload file to s3 bucket
	 *
	 * @param $key
	 * @param $file_path
	 *
	 * @return bool
	 */
	public function upload_file_to_s3_bucket( $key, $file_path ){
		try {
			$result = $this->s3->putObject([
				'Bucket'     => $this->settings->bucket_name,
				'Key'        => $key,
				'SourceFile' => $file_path,
			]);

			if ( !empty( $result['ObjectURL'] ) ) {
				return true;
			} else {
				return false;
			}
		} catch (Aws\S3\Exception\S3Exception $e) {
			return false;
		}
	}

	/**
	 * Remove file from the s3 bucket
	 *
	 * @param $key
	 *
	 * @return bool
	 */
	public function remove_file_from_s3_bucket( $key ){
		try {
			$this->s3->deleteObject([
				'Bucket' => $this->settings->bucket_name,
				'Key'    => $key
			]);

			return true;
		} catch (Aws\S3\Exception\S3Exception $e) {
			return false;
		}
	}

	/**
	 * Get file from s3 bucket
	 *
	 * @param $key
	 *
	 * @return Aws\Result|Guzzle\Service\Resource\Model|bool
	 */
	public function get_file_from_s3_bucket( $key ){
		try {
			$result = $this->s3->getObject([
				'Bucket' => $this->settings->bucket_name,
				'Key'    => $key
			]);

			if ( !empty( $result['Body'] ) ) {
				return $result;
			}

			return false;
		} catch (Aws\S3\Exception\S3Exception $e) {
			return false;
		}
	}

	/**
	 * Quotes regular expression string
	 *
	 * @param string  $string
	 * @param string  $delimiter
	 * @return string
	 */
	public function preg_quote( $string, $delimiter = '~' ) {
		$string = preg_quote( $string, $delimiter );
		$string = strtr( $string, array(
			' ' => '\ '
		) );

		return $string;
	}

	/**
	 * Rewrite htaccess rules for sx s3 storage media
	 *
	 * @return void|bool
	 */
	public function rules_core_add() {
		$path = trailingslashit( WP_CONTENT_DIR ) . "uploads/.htaccess";
		$sxms_rules_start = "# Start SX MEDIA STORAGE";
		$sxms_rules_end   = "# End SX MEDIA STORAGE";
		$sxms_rules       = "<IfModule mod_rewrite.c>
############################################
## enable rewrites

 Options +FollowSymLinks
 RewriteEngine on

############################################
## never rewrite for existing files
 RewriteCond %{REQUEST_FILENAME} !-f

############################################
## rewrite everything else to index.php

 RewriteRule .* ../plugins/sx-amazon-s3-media-storage/get.php [L]
</IfModule>";

		$input = $sxms_rules_start . PHP_EOL . $sxms_rules . PHP_EOL . $sxms_rules_end;
		$flags = 0;

		/*
			Check if file wp-content/uploads/.htaccess exists
			If not - it will be created with contents of $input variable
			If exists - check if file already contains rules. Append rules at the end if it does not
			Do nothing if rules already exist in .htaccess
		*/
		if ( file_exists( $path ) ) {
			$data = @file_get_contents( $path );

			if ( !empty( $data ) ) {
				$reg_exp = '~' . $this->preg_quote( $sxms_rules_start ) . "\n.*?" . $this->preg_quote( $sxms_rules_end ) . "\n*~s";
				$contain_rule = preg_match( $reg_exp, $data );

				if ( $contain_rule ) {
					$input = "";
				}
			}
		}

		if ( !empty( $input ) ) {
			$this->file_force_contents( $path, $input, $flags );
		}
	}

	/**
	 * When putting content into a file and some folder in the path does not exist - recursively create those folders
	 *
	 * @param $dir
	 * @param $contents
	 * @param $flags
	 */
	public function file_force_contents($dir, $contents, $flags = ""){
		$parts = explode('/', $dir);
		$file = array_pop($parts);
		$dir = '';
		foreach($parts as $part) {
			if (!is_dir($dir .= "/$part")) mkdir($dir);
		}
		file_put_contents("$dir/$file", $contents, $flags);
	}
}
