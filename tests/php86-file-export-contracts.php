<?php
/** Private-file and CSV contracts with synthetic WordPress APIs and temporary files. */
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) { exit( 1 ); }
define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1048576 );
define( 'ARRAY_A', 'ARRAY_A' );
$mode = $argv[1] ?? 'parent';
$root = $argv[2] ?? sys_get_temp_dir() . '/ace-form-contract-' . bin2hex( random_bytes( 8 ) );
$meta = null; $can = true; $nonce = true; $rows = array(); $routes = array();
class WP_Error { public function __construct( public string $code, public string $message, public array $data = array() ) {} }
class WP_REST_Request implements ArrayAccess {
    public array $values = array( 'entry_id' => 1, 'file_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' );
    public function offsetExists( mixed $key ): bool { return isset( $this->values[ $key ] ); }
    public function offsetGet( mixed $key ): mixed { return $this->values[ $key ] ?? null; }
    public function offsetSet( mixed $key, mixed $value ): void { $this->values[ $key ] = $value; }
    public function offsetUnset( mixed $key ): void { unset( $this->values[ $key ] ); }
}
class WP_REST_Response {}
function __( $s, $domain = '' ) { return $s; }
function esc_html__( $s, $domain = '' ) { return $s; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) ); }
function sanitize_text_field( $v ) { return strip_tags( $v ); }
function sanitize_email( $v ) { return filter_var( $v, FILTER_SANITIZE_EMAIL ); }
function sanitize_mime_type( $v ) { return preg_replace( '/[^a-z0-9\/\-+.]/i', '', $v ); }
function sanitize_file_name( $v ) { return basename( $v ); }
function wp_check_filetype_and_ext( $path, $name ) { return array( 'ext' => false, 'type' => false ); }
function wp_unslash( $v ) { return stripslashes( $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function trailingslashit( $v ) { return rtrim( $v, '/' ) . '/'; }
function wp_upload_dir() { global $root; return array( 'basedir' => $root ); }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function current_user_can( $cap ) { global $can; return $can; }
function check_admin_referer( $action ) { global $nonce; if ( ! $nonce ) { throw new RuntimeException( 'nonce-refused' ); } }
function wp_die( $message, $title = '', $status = 0 ) { throw new RuntimeException( 'permission-refused:' . $status ); }
function nocache_headers() {}
function register_rest_route( $ns, $route, $args ) { global $routes; $routes[] = $args; }
class FixtureDb {
    public string $prefix = 'synthetic_';
    public function prepare( $sql, ...$args ) { return $sql; }
    public function get_var( $sql ) { global $meta; return $meta; }
    public function get_results( $sql, $format = null ) { global $rows; return $rows; }
}
$wpdb = new FixtureDb();
require dirname( __DIR__ ) . '/includes/Security/Capabilities.php';
require dirname( __DIR__ ) . '/includes/Forms/Files.php';
require dirname( __DIR__ ) . '/includes/Entries/Admin.php';
require dirname( __DIR__ ) . '/includes/Entries/Privacy.php';
use ACE\AdaptiveContentEnhancer\Forms\Files;
use ACE\AdaptiveContentEnhancer\Entries\Admin;
use ACE\AdaptiveContentEnhancer\Entries\Privacy;
set_error_handler( static function ( $level, $message, $file, $line ) { throw new ErrorException( $message, 0, $level, $file, $line ); } );
if ( 'csv' === $mode ) {
    $rows = array( array( 'id' => 7, 'form_id' => 'synthetic', 'created_at' => '2026-11-21', 'status' => 'accepted', 'contact_name' => '=HYPERLINK("synthetic")', 'contact_email' => 'fixture@example.invalid', 'data' => '{"message":"Café, a quote \\" and a slash \\\\"}' ) );
    Admin::export_csv();
}
if ( 'download' === $mode ) {
    $meta = json_encode( array( 'path' => $root . '/ace-forms/private.pdf', 'name' => 'Café.pdf', 'mime' => 'application/pdf' ) );
    Files::download( new WP_REST_Request() );
}
$checks = 0;
function check_contract( $ok, $label ) { global $checks; if ( ! $ok ) { throw new RuntimeException( 'FAIL ' . $label ); } ++$checks; }
function child( $mode, $root ) {
    $process = proc_open( array( PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'sendmail_path=/bin/false', __FILE__, $mode, $root ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
    fclose( $pipes[0] ); $output = stream_get_contents( $pipes[1] ); $errors = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
    check_contract( 0 === proc_close( $process ) && '' === $errors, 'child completed without diagnostics: ' . $mode . ' ' . $errors ); return $output;
}
try {
    mkdir( $root, 0700 ); mkdir( $root . '/ace-forms', 0700 );
    file_put_contents( $root . '/ace-forms/private.pdf', "%PDF-1.4\nSynthetic private document\n%%EOF" );
    file_put_contents( $root . '/outside.pdf', 'synthetic outside sentinel' );
    Files::register_routes();
    $can = false; check_contract( false === ( $routes[0]['permission_callback'] )(), 'anonymous private-file route denied' );
    $can = true; check_contract( true === ( $routes[0]['permission_callback'] )(), 'authorised private-file route allowed' );
    foreach ( array( null, '{broken', 'null', '42', '"Café"', '[]', json_encode( array( 'path' => $root . '/outside.pdf' ) ), json_encode( array( 'path' => $root . '/ace-forms/../outside.pdf' ) ) ) as $meta ) {
        $result = Files::download( new WP_REST_Request() ); check_contract( $result instanceof WP_Error && 404 === $result->data['status'], 'invalid or outside file metadata denied' );
    }
    symlink( $root . '/outside.pdf', $root . '/ace-forms/escape.pdf' );
    $meta = json_encode( array( 'path' => $root . '/ace-forms/escape.pdf' ) );
    check_contract( Files::download( new WP_REST_Request() ) instanceof WP_Error, 'symlink outside private root denied' );
    check_contract( child( 'download', $root ) === file_get_contents( $root . '/ace-forms/private.pdf' ), 'real readfile returns exact private document bytes' );
    foreach ( array( array( 'error' => UPLOAD_ERR_NO_FILE ), array( 'error' => 0, 'size' => 0 ), array( 'error' => 0, 'size' => 11 * MB_IN_BYTES ), array( 'error' => 0, 'size' => 100, 'name' => 'shell.php' ), array( 'error' => 0, 'size' => 100, 'name' => 'fake.pdf', 'tmp_name' => $root . '/outside.pdf' ) ) as $upload ) {
        check_contract( Files::store( $upload, array() ) instanceof WP_Error, 'invalid upload refused without moving files' );
    }
    $can = false;
    try { Admin::export_csv(); throw new RuntimeException( 'unexpected export' ); } catch ( RuntimeException $e ) { check_contract( 'permission-refused:403' === $e->getMessage(), 'CSV export requires manage capability' ); }
    $can = true; $nonce = false;
    try { Admin::export_csv(); throw new RuntimeException( 'unexpected export' ); } catch ( RuntimeException $e ) { check_contract( 'nonce-refused' === $e->getMessage(), 'CSV export requires nonce' ); }
    $nonce = true;
    $csv = child( 'csv', $root ); $stream = fopen( 'php://temp', 'w+' ); fwrite( $stream, $csv ); rewind( $stream );
    $header = fgetcsv( $stream, null, ',', '"', '\\' ); $record = fgetcsv( $stream, null, ',', '"', '\\' ); fclose( $stream );
    check_contract( count( $header ) === 7 && 'ID' === $header[0], 'real CSV header preserved' );
    check_contract( count( $record ) === 7 && '7' === $record[0] && "'=HYPERLINK(\"synthetic\")" === $record[4], 'CSV quoting and formula neutralisation preserved' );
    check_contract( str_contains( $record[6], 'Café' ), 'CSV Unicode preserved' );
    foreach ( array( '{broken', 'null', '[]', '{"message":"Café","nested":{"safe":true}}' ) as $payload ) {
        $rows = array( (object) array( 'id' => 7, 'form_id' => 'synthetic', 'created_at' => '2026-11-21', 'data' => $payload ) );
        $export = Privacy::export( 'fixture@example.invalid' );
        check_contract( true === $export['done'] && 1 === count( $export['data'] ) && 'ace-form-entry-7' === $export['data'][0]['item_id'], 'privacy export retains identity with selected JSON boundary' );
    }
    echo "Completed $checks file/export checks on PHP ", PHP_VERSION, "; no hosted data, network, mail or submission\n";
} finally {
    restore_error_handler();
    foreach ( array( '/ace-forms/escape.pdf', '/ace-forms/private.pdf', '/outside.pdf' ) as $path ) { if ( is_file( $root . $path ) || is_link( $root . $path ) ) { unlink( $root . $path ); } }
    rmdir( $root . '/ace-forms' ); rmdir( $root );
}
