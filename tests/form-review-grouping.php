<?php
/** Standalone advisory review grouping: php8.4 tests/form-review-grouping.php */
namespace ACE\AdaptiveContentEnhancer\Security;
final class Capabilities { public const EDIT_ENTRIES = 'ace_edit_entries'; }
namespace ACE\AdaptiveContentEnhancer\Forms;
if ( PHP_SAPI !== 'cli' ) { exit; }
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_salt( $scheme ) { return 'grouping-test-salt'; }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function home_url() { return 'https://bins.example'; }
function __( $text, $domain ) { return $text; }
function get_option( $key, $default = false ) { return $GLOBALS['review_lessons'] ?? $default; }
function update_option() { throw new \RuntimeException( 'Grouping must never persist or train.' ); }
function current_user_can( $cap ) { return true; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text, $domain ) { return esc_html( $text ); }
function esc_url( $url ) { return esc_html( $url ); }
function admin_url( $path ) { return 'https://bins.example/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
final class FormRegistry { public static function get( $id ) { return $GLOBALS['review_fixture_form']; } }

require dirname( __DIR__ ) . '/includes/Forms/Review.php';
$form = array( 'fields' => array( 'email' => array( 'type' => 'email' ), 'message' => array( 'type' => 'textarea' ) ) );
$count = 0;
$check = static function ( bool $ok, string $label ) use ( &$count ): void { if ( ! $ok ) { throw new \RuntimeException( $label ); } ++$count; echo "PASS {$label}\n"; };
$entry = static function ( string $text, array $reasons = array(), string $email = '' ) use ( $form ): array { static $id = 0; return array( 'row' => (object) array( 'id' => ++$id ), 'form' => $form, 'values' => array( 'message' => $text, 'email' => $email ), 'screen' => array( 'verdict' => 'suspect', 'reasons' => $reasons ) ); };
$link = array( 'link in the message', 'mostly non-Latin script' );
$groups = Review::group_entries( array( $entry( 'Unexpected travel advert trip79.at', $link ), $entry( 'Different booking story mentioning trip 83 co', $link ), $entry( 'Yet another unrelated advert using trip80.co', $link ) ) );
$check( 1 === count( $groups ) && 3 === count( $groups[0]['rows'] ), 'Numbered and spaced promoted hosts group despite different wording/TLDs' );
$check( isset( $groups[0]['signals']['rotating-host:trip'] ), 'Campaign group explains the shared rotating host family' );
$groups = Review::group_entries( array( $entry( 'An advert at https://www.campaign.example/a?token=private' ), $entry( 'Separate wording https://campaign.example/other' ) ) );
$check( 1 === count( $groups ) && isset( $groups[0]['signals']['host:campaign.example'] ), 'Promoted hosts ignore www/path/query variants' );
$check( ! str_contains( implode( ' ', $groups[0]['signals'] ), 'private' ), 'Campaign labels contain no URL query data' );
$groups = Review::group_entries( array( $entry( 'Different proposal one', array( 'SEO or web services pitch: "backlinks"' ) ), $entry( 'Another unsolicited promotion', array( 'SEO or web services pitch: "backlinks", "ranking"' ) ) ) );
$check( 1 === count( $groups ) && str_contains( implode( ' ', $groups[0]['signals'] ), 'backlinks' ), 'Specific shared screening terms group related pitches' );
$groups = Review::group_entries( array( $entry( 'Запрос о контейнере для отходов', $link ), $entry( 'Отдельный вопрос о доставке заказа', $link ) ) );
$check( 2 === count( $groups ), 'A shared language and broad screening reasons never form a campaign' );
$groups = Review::group_entries( array( $entry( 'Model model1100 co details' ), $entry( 'Different model2000 co enquiry' ) ) );
$check( 2 === count( $groups ), 'Numbered product references without link/promotion evidence stay separate' );
$groups = Review::group_entries( array( $entry( 'Please inspect https://bins.example/catalogue' ), $entry( 'Spare parts from https://www.bins.example/support' ) ) );
$check( 2 === count( $groups ), 'Links to this site do not create campaign groups' );
$groups = Review::group_entries( array( $entry( 'A quotation request', array(), 'sender@campaign.example' ), $entry( 'Delivery question', array(), 'sender@campaign.example' ) ) );
$check( 2 === count( $groups ), 'Sender contact details are excluded from campaign signals' );
$groups = Review::group_entries( array( $entry( 'First https://a.example' ), $entry( 'Bridge https://a.example and https://b.example' ), $entry( 'Separate https://b.example' ) ) );
$check( 2 === count( $groups ), 'A multi-domain bridge cannot chain unrelated campaigns together' );
$spam = 'Dear sir we offer SEO backlinks and guest posting to boost your website rankings today';
$groups = Review::group_entries( array( $entry( $spam . ' https://one.example' ), $entry( $spam . ' https://two.example' ) ) );
$check( 1 === count( $groups ), 'Existing close-text grouping still works across changed URLs' );
$before = array( 'verdict' => 'clean', 'score' => 0, 'reasons' => array() );
$values = array( 'message' => 'New travel sales wording trip83.co that must not train itself from an advisory campaign' );
$initial = Review::assess( $form, $values, $before );
Review::group_entries( array( $entry( 'Prior advert trip79.at', $link ), $entry( $values['message'], $link ) ) );
$check( $initial === Review::assess( $form, $values, $before ) && 'clean' === $initial['verdict'], 'Advisory grouping changes neither screening verdict nor learning' );
$GLOBALS['review_lessons'] = array( array( 'signature' => Review::signature( $form, array( 'message' => $spam ) ), 'verdict' => 'spam', 'time' => time() ) );
$check( 'clean' === Review::assess( $form, $values, $before )['verdict'], 'A promoted-family match never expands an explicitly learned signature' );
$check( 'suspect' === Review::assess( $form, array( 'message' => $spam ), $before )['verdict'], 'Existing explicit conservative signature learning remains effective' );
$check( array() === Review::signature( $form, array( 'message' => 'Please quote' ) ), 'Short enquiries remain excluded from learning' );
if ( ! empty( $argv[1] ) ) {
    $historical = array();
    foreach ( file( $argv[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
        $blog = json_decode( $line, true );
        if ( 2 !== ( $blog['blog'] ?? 0 ) ) { continue; }
        foreach ( $blog['entries'] as $row ) {
            if ( 'suspect' !== $row['status'] ) { continue; }
            $reasons = array();
            foreach ( $row['stored_meta'] as $meta ) { if ( 'screen' === $meta['key'] ) { $reasons = $meta['reasons']; } }
            $historical[] = $entry( $row['message'], $reasons );
        }
    }
    $groups = Review::group_entries( $historical );
    $check( 10 === count( $historical ) && 1 === count( $groups ), 'All ten redacted historical Taylor campaign variants form one advisory group' );
}
$GLOBALS['review_fixture_form'] = $form;
$fixture_rows = array();
$fixture_meta = array();
foreach ( array( 'First advert trip79.at', 'Different advert trip 83 co' ) as $id => $message ) {
    $fixture_rows[] = (object) array( 'id' => $id + 1, 'form_id' => 'review-fixture', 'data' => json_encode( array( 'message' => $message ) ), 'created_at' => '2026-11-20 00:00:00', 'contact_name' => '<script>unsafe</script>', 'status' => 'suspect' );
    $fixture_meta[] = (object) array( 'entry_id' => $id + 1, 'meta_value' => json_encode( array( 'reasons' => $link ) ) );
}
$wpdb = new class( $fixture_rows, $fixture_meta ) {
    public $prefix = 'wp_fixture_';
    public $queries = array();
    private $rows;
    private $meta;
    public function __construct( $rows, $meta ) { $this->rows = $rows; $this->meta = $meta; }
    public function prepare( $sql, $args ) { return $sql; }
    public function get_results( $sql ) { $this->queries[] = $sql; return str_contains( $sql, 'entry_meta' ) ? $this->meta : $this->rows; }
    public function query() { throw new \RuntimeException( 'Review rendering must not write.' ); }
};
ob_start(); Review::render(); $html = ob_get_clean();
$check( str_contains( $html, 'Similar messages: 2' ) && str_contains( $html, 'Rotating promoted host family: trip[number]' ), 'Review queue renders shared campaign evidence and both entry links' );
$check( str_contains( $html, 'Grouping is advisory' ) && str_contains( $html, 'does not confirm spam, train screening or release messages' ), 'Queue explains grouping cannot train, classify or release entries' );
$check( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ), 'Queue escapes untrusted contact text' );
$check( 2 === count( $wpdb->queries ), 'Queue loads screening evidence in one bounded batch without per-entry SQL' );
$check( array( 'suspect', 'suspect' ) === array_column( $fixture_rows, 'status' ), 'Rendering leaves all entry decisions untouched' );
echo "{$count} advisory grouping checks passed.\n";
