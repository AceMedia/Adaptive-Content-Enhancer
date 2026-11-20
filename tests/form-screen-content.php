<?php
/** Standalone screening regressions. Optional argument: a redacted historical JSONL sample file. */
namespace ACE\AdaptiveContentEnhancer\Forms;

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['screen_lessons'] = array();
$GLOBALS['screen_override'] = null;
function wp_specialchars_decode( $text, $flags ) { return htmlspecialchars_decode( $text, $flags ); }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function home_url() { return 'https://supplier.example'; }
function is_multisite() { return false; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_salt( $scheme ) { return 'standalone-screening-fixture'; }
function get_option( $key, $default = false ) { return 'ace_forms_review_learning' === $key ? $GLOBALS['screen_lessons'] : $default; }
function apply_filters( $hook, $value, ...$args ) { return 'ace_forms_screen_result' === $hook && is_callable( $GLOBALS['screen_override'] ) ? ( $GLOBALS['screen_override'] )( $value ) : $value; }
require dirname( __DIR__ ) . '/includes/Forms/Review.php';
require dirname( __DIR__ ) . '/includes/Forms/Screen.php';
$form = array( 'fields' => array( 'email' => array( 'type' => 'email' ), 'message' => array( 'type' => 'textarea' ) ) );
$count = 0;
$check = static function ( bool $ok, string $label ) use ( &$count ): void { if ( ! $ok ) { throw new \RuntimeException( $label ); } ++$count; echo "PASS $label\n"; };
$assess = static fn( string $message, string $email = 'buyer@example.test', string $name = 'Alex Buyer' ) => Screen::assess( $form, array( 'email' => $email, 'message' => $message ), $email, $name );
$template = 'Dear Sales Team, We are interested in a long-term contract supply of your products. Please send your brochure and offer FOB prices in USD or EURO. Include bulk pricing, payment terms and shipping options. Kindly send your current product catalog. Agent';
$result = $assess( $template );
$check( 'suspect' === $result['verdict'] && str_contains( implode( ' ', $result['reasons'] ), 'Generic procurement template' ), 'Observed generic contract/catalogue/FOB template is held for review.' );
$check( 'suspect' === $assess( 'We want a long-term contract for your products. Please send a brochure with FOB prices in USD and payment terms.' )['verdict'], 'Generic contract template without a concrete buying request remains held.' );
$signature = "Example Technology Mould Limited\n\nAddress: 109, Building A, Example South Road, Example Town\n\nTel: +00 1234 567890\n\nEmail: agent@example.test";
$result = $assess( $signature );
$check( 'suspect' === $result['verdict'] && str_contains( implode( ' ', $result['reasons'] ), 'Insufficient enquiry content' ), 'Company/address/telephone/email without an enquiry is held as insufficient content.' );
$clean = array(
    'Quote',
    'Request for quotation: please send your catalogue of 1100 litre bins.',
    'Dear Sir, kindly send your catalogue for our RFQ. Our specification is https://buyer.example/brief',
    'We want a long-term contract for your products. Please quote for 20 galvanised bins and send brochure with FOB prices in USD and payment terms.',
    'Hello',
    'Please send me your catalogue.',
    'Kindly send your current product catalog.',
    'Dear Sales Team, can you quote for 20 bins including bulk pricing, payment terms and shipping costs?',
    'Dear Sales Team, we need 60 of your 1100 litre containers for a long-term contract. Please send a catalogue with FOB prices in USD and payment terms.',
    'We are a buyer in Singapore. Please send a quotation and catalogue for 120 galvanised 1100 litre bins, including FOB prices in USD and shipping costs. We need stock before March.',
    'Could you send a brochure? We plan a long-term supply agreement for 40 replacement lids and need the specification and price before raising our purchase order.',
    'We manage recycling at four distribution centres and need advice on replacing our damaged containers. Our existing 1100 litre galvanised bins have towing brackets and two brakes. Could you confirm lead times, available colours, spare wheels and whether you can deliver to all four sites? Our schedule and photos are at https://buyer.example/specification.',
    'Добрый день! Нужна цена на двадцать контейнеров объёмом 1100 литров. Возможна ли доставка в наш город? План площадки: https://buyer.example/site',
    '您好，我们需要二十个垃圾收集容器。请问有哪些尺寸，是否可以国际运输？我们的场地是 https://buyer.example/site',
    'مرحباً، نحتاج إلى حاويات للنفايات لمبنى المكاتب. هل يمكنكم إرسال الأسعار والمقاسات؟ https://buyer.example/site',
    'Please compare these supplier specifications: https://supplier01.co/bins https://supplier02.co/bins https://supplier03.co/bins. We need matching wheels.',
    "Could you quote for replacement lids?\n$signature",
    "Нужны контейнеры.\n$signature",
    "Please call me\n$signature",
    'Tel: +00 1234 567890',
);
foreach ( $clean as $index => $message ) { $check( 'clean' === $assess( $message )['verdict'], 'Genuine brief, detailed, international or catalogue enquiry stays clean: ' . ( $index + 1 ) ); }
$promotions = array(
    'Compare cheap travel deals with deals71.co then deals72.at and deals73.co for your next booking.',
    'Find holidays with travelbrand co and travel83 co, your source for deals: https://advert.example',
    'Our offers [url=https://advert.example/one]click[/url] [url=https://advert.example/two]offers[/url] [url=https://advert.example/three]book[/url]',
);
foreach ( $promotions as $index => $message ) { $check( 'suspect' === $assess( $message )['verdict'], 'Related rotating domains or repeated formatted links are held: ' . ( $index + 1 ) ); }
$check( 'suspect' === $assess( 'We provide SEO backlinks and guest posting. Kindly book a quick call.' )['verdict'], 'Existing sales-pitch screening remains effective.' );
$check( 'suspect' === $assess( 'dtacd6' )['verdict'], 'Existing random-token screening remains effective.' );
// Structured fields do not turn a genuine message into a generic procurement template.
$structured_form = array( 'fields' => array( 'company' => array( 'type' => 'text' ), 'message' => array( 'type' => 'textarea' ) ) );
$result = Screen::assess( $structured_form, array( 'company' => $template, 'message' => 'Please quote for ten bins.' ), 'buyer@example.test', 'Alex Buyer' );
$check( ! str_contains( implode( ' ', $result['reasons'] ), 'Generic procurement template' ), 'New procurement rules inspect enquiry fields, not company/contact fields.' );
$GLOBALS['screen_lessons'] = array( array( 'signature' => Review::signature( $form, array( 'message' => $template ) ), 'verdict' => 'clean', 'time' => time() ) );
$check( 'clean' === $assess( $template )['verdict'], 'Explicitly reviewed genuine messages still override new heuristics.' );
$GLOBALS['screen_lessons'] = array();
$GLOBALS['screen_override'] = static function ( array $result ): array { $result['verdict'] = 'clean'; return $result; };
$check( 'clean' === $assess( $template )['verdict'], 'Site screening overrides remain available.' );
$GLOBALS['screen_override'] = null;
if ( ! empty( $argv[1] ) ) {
    $historical = 0;
    foreach ( file( $argv[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
        foreach ( json_decode( $line, true, 512, JSON_THROW_ON_ERROR )['entries'] ?? array() as $entry ) {
            $message = (string) ( $entry['message'] ?? '' );
            if ( false === stripos( $message, 'tripscan' ) ) { continue; }
            $result = $assess( str_replace( '[link]', 'https://promoted.example', $message ) );
            $check( 'suspect' === $result['verdict'], 'Historical redacted campaign message remains held: ' . (int) $entry['id'] );
            ++$historical;
        }
    }
    $check( 10 === $historical, 'All ten observed campaign messages were tested.' );
}
echo "$count screening checks passed.\n";
