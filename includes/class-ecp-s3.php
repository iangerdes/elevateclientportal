<?php
// File: elevate-client-portal/includes/class-ecp-s3.php
/**
 * A helper class for all Amazon S3 functionality.
 *
 * @package Elevate_Client_Portal
 * @version 31.0.0 (Bulk Encrypt/Decrypt Fix)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_S3 {

    private static $instance;
    private $s3_client;
    private $options;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option( 'ecp_s3_options', [] );
        $this->initialize_s3_client();
    }

    private function initialize_s3_client() {
        if ( ! $this->is_s3_enabled() ) {
            $this->s3_client = null;
            return;
        }

        if ( ! class_exists( 'Aws\\S3\\S3Client' ) ) {
            if ( file_exists( ECP_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
                require_once ECP_PLUGIN_PATH . 'vendor/autoload.php';
            } elseif ( file_exists( ECP_PLUGIN_PATH . 'vendor/aws-autoloader.php' ) ) {
                require_once ECP_PLUGIN_PATH . 'vendor/aws-autoloader.php';
            }
        }
        
        if ( ! class_exists( 'Aws\\S3\\S3Client' ) ) {
             $this->s3_client = null;
             return;
        }

        try {
            $this->s3_client = new Aws\S3\S3Client([
                'version'     => 'latest',
                'region'      => $this->options['s3_region'],
                'credentials' => [
                    'key'    => $this->options['s3_access_key'],
                    'secret' => $this->options['s3_secret_key'],
                ],
            ]);
        } catch (\Exception $e) {
            $this->s3_client = null;
            // Optionally log this error
        }
    }

    public function is_s3_enabled() {
        return ! empty( $this->options['s3_bucket'] ) && ! empty( $this->options['s3_region'] ) && ! empty( $this->options['s3_access_key'] ) && ! empty( $this->options['s3_secret_key'] );
    }

    public function test_connection() {
        if ( ! $this->s3_client ) return new WP_Error( 's3_sdk_missing', 'The AWS SDK is not loaded or S3 is not configured correctly.' );
        try {
            $this->s3_client->headBucket([ 'Bucket' => $this->options['s3_bucket'] ]);
            return true;
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_connection_failed', 'Could not connect. Details: ' . $e->getMessage() );
        }
    }

    public function upload_file( $file_path, $file_name ) {
        if ( ! $this->s3_client ) return new WP_Error( 's3_not_configured', 'S3 is not configured.' );
        $s3_key = 'client-files/' . wp_generate_uuid4() . '/' . sanitize_file_name($file_name);
        try {
            $this->s3_client->putObject([
                'Bucket'     => $this->options['s3_bucket'],
                'Key'        => $s3_key,
                'SourceFile' => $file_path,
            ]);
            $meta = $this->get_file_metadata( $s3_key );
            return [ 'key' => $s3_key, 'size' => $meta['size'] ?? 0 ];
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_upload_failed', $e->getMessage() );
        }
    }

    public function delete_file( $s3_key ) {
        if ( ! $this->s3_client ) return new WP_Error( 's3_not_configured', 'S3 is not configured.' );
        try {
            $this->s3_client->deleteObject([
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
            ]);
            return true;
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_delete_failed', $e->getMessage() );
        }
    }
    
    public function update_file_contents( $s3_key, $contents ) {
        if ( ! $this->s3_client ) {
            return new WP_Error( 's3_not_configured', 'S3 is not configured.' );
        }
        try {
            $this->s3_client->putObject([
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
                'Body'   => $contents,
            ]);
            return true;
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_update_failed', $e->getMessage() );
        }
    }

    public function get_file_metadata( $s3_key ) {
        if ( ! $this->s3_client ) return new WP_Error('s3_not_configured', 'S3 not configured.');
        try {
            $result = $this->s3_client->headObject([
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
            ]);
            return [
                'size'      => $result['ContentLength'] ?? 0,
                'timestamp' => isset($result['LastModified']) ? $result['LastModified']->getTimestamp() : time(),
            ];
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_metadata_failed', $e->getMessage() );
        }
    }
    
    public function get_presigned_url( $s3_key, $file_name ) {
        if ( ! $this->s3_client ) {
            return new WP_Error( 's3_not_configured', 'S3 is not configured.' );
        }
        try {
            $cmd = $this->s3_client->getCommand('GetObject', [
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
                'ResponseContentDisposition' => 'attachment; filename="' . $file_name . '"',
            ]);
            $request = $this->s3_client->createPresignedRequest($cmd, '+15 minutes');
            return (string) $request->getUri();
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_presign_failed', $e->getMessage() );
        }
    }

    public function get_file_contents( $s3_key ) {
        if ( ! $this->s3_client ) return new WP_Error('s3_not_configured', 'S3 not configured.');
        try {
            $result = $this->s3_client->getObject([
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
            ]);
            return (string) $result['Body'];
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_get_contents_failed', $e->getMessage() );
        }
    }

    public function get_all_s3_files() {
        if ( ! $this->s3_client ) return new WP_Error('s3_not_configured', 'S3 not configured.');
        try {
            $result = $this->s3_client->listObjectsV2([
                'Bucket' => $this->options['s3_bucket'],
                'Prefix' => 'client-files/',
            ]);
            
            $files = [];
            foreach ($result['Contents'] ?? [] as $object) {
                if (substr($object['Key'], -1) === '/') continue;
                $files[] = [
                    'key'           => $object['Key'],
                    'size'          => $object['Size'],
                    'last_modified' => $object['LastModified']
                ];
            }
            return $files;
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_list_failed', $e->getMessage() );
        }
    }
}

