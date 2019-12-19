<?php
/**
 * @vendor Skynix LLC
 * @see https://skynix.co/
 */

require_once("../../../wp-load.php");
define('WP_USE_THEMES', false);
global $sxms_aws;

if ( empty( $sxms_aws->s3 ) ) {
    throw new \Exception(404);
}

$file_path = ABSPATH . ltrim($_SERVER["REQUEST_URI"], "/");

$file_key  = $sxms_aws->generate_media_file_key_for_s3( $file_path );

if ( !empty( $file_key ) && $sxms_aws->file_exists_on_s3_bucket( $file_key ) ) {
    $result = $sxms_aws->get_file_from_s3_bucket( $file_key );

    if ( !empty( $result['Body'] ) ) {
        $file_name = array_pop( explode( "/", $file_key ) );

        if ( !empty( $file_name ) ) {
            $bits = wp_upload_bits( $file_name, null, $result['Body'] );

            if ( empty( $bits["error"] ) && !empty( $bits["file"] ) ) {
                $attachment = array(
                    'guid'           => trailingslashit( WP_CONTENT_DIR ) . $file_key,
                    'post_mime_type' => $result['ContentType'],
                    'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_name ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );

                $attach_id = wp_insert_attachment( $attachment, $file_name );

                if ( !empty( $attach_id ) ) {
                    require_once( ABSPATH . 'wp-admin/includes/image.php' );

                    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_name );
                    wp_update_attachment_metadata( $attach_id, $attach_data );

                    header("Content-Type: {$result['ContentType']}");
                    echo file_get_contents( $bits["file"] );
                    die();
                }
            }
        }
    }
}

throw new \Exception(404);
