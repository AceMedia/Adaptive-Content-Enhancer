<?php
/** Content screen for enquiries: flags sales pitches, SEO offers and bulk RFQ scams without ever dropping them. */
namespace ACE\AdaptiveContentEnhancer\Forms;

defined( 'ABSPATH' ) || exit;

final class Screen {
	/** Score at or above which an enquiry is tagged as possible spam. */
	public const THRESHOLD = 4;

	/**
	 * Score a submission. Nothing is blocked here: the notification still goes to the form's recipients,
	 * tagged in the subject and with the reasons at the top of the body.
	 *
	 * @return array{verdict: string, score: int, reasons: string[]}
	 */
	public static function assess( array $form, array $values, string $contact_email, string $contact_name ): array {
		$text = array();
		foreach ( $values as $value ) {
			$text[] = is_array( $value ) ? implode( ' ', array_map( 'strval', $value ) ) : (string) $value;
		}
		$text = wp_specialchars_decode( implode( "\n", $text ), ENT_QUOTES );
		$lower = strtolower( $text );
		$score = 0;
		$reasons = array();

		foreach ( self::patterns() as $group => $rule ) {
			$hits = array();
			foreach ( $rule['terms'] as $pattern ) {
				if ( preg_match( $pattern, $lower, $match ) ) {
					$hits[] = trim( $match[0] );
				}
			}
			if ( $hits ) {
				$score += min( $rule['cap'], count( $hits ) * $rule['weight'] );
				$reasons[] = $rule['label'] . ': "' . implode( '", "', array_slice( array_unique( $hits ), 0, 3 ) ) . '"';
			}
		}

		$links = preg_match_all( '~(?:https?://|www\.)\S+~i', $text );
		if ( $links >= 3 ) {
			$score += 3;
			$reasons[] = sprintf( '%d links in the message', $links );
		} elseif ( $links ) {
			$score += 1;
			$reasons[] = 'link in the message';
		}

		$letters = preg_match_all( '/\p{L}/u', $text );
		$foreign = preg_match_all( '/[\p{Cyrillic}\p{Han}\p{Hangul}\p{Thai}\p{Arabic}]/u', $text );
		if ( $letters > 20 && $foreign / $letters > 0.3 ) {
			$score += 3;
			$reasons[] = 'mostly non-Latin script';
		}

		foreach ( $form['fields'] ?? array( 'message' => array( 'type' => 'textarea' ) ) as $key => $field ) {
			$message = trim( (string) ( is_scalar( $values[ $key ] ?? null ) ? $values[ $key ] : '' ) );
			// A real word ("Quote", "Hello") is not penalised; a bot token such as "dtacd6" mixes letters and digits.
			if ( 'textarea' === ( $field['type'] ?? '' ) && preg_match( '/^(?=.*\d)(?=.*[a-z])[a-z0-9]{4,12}$/i', $message ) ) {
				$score += 4;
				$reasons[] = 'message is a random token';
				break;
			}
		}

		$sender_domain = strtolower( (string) substr( strrchr( $contact_email, '@' ) ?: '', 1 ) );
		$own_domains = array_filter( array( wp_parse_url( home_url(), PHP_URL_HOST ), is_multisite() ? get_network()->domain : '' ) );
		foreach ( $own_domains as $own ) {
			$own = preg_replace( '/^www\./', '', strtolower( (string) $own ) );
			if ( $sender_domain && ( $sender_domain === $own || str_ends_with( $sender_domain, '.' . $own ) ) ) {
				$score += 2;
				$reasons[] = "sender claims this site's own domain";
				break;
			}
		}

		if ( $contact_name && false !== strpos( $contact_name, '@' ) ) {
			$score += 2;
			$reasons[] = 'email address typed into the name field';
		}

		$result = array(
			'verdict' => $score >= self::THRESHOLD ? 'suspect' : 'clean',
			'score' => $score,
			'reasons' => $reasons,
		);
		/** Lets a site adjust the verdict, e.g. allow-list a known customer domain. */
		return (array) apply_filters( 'ace_forms_screen_result', $result, $form, $values, $contact_email );
	}

	/** Subject prefix and body preamble for a tagged notification. */
	public static function tag( array $screen, string $subject, string $body ): array {
		if ( 'suspect' !== ( $screen['verdict'] ?? '' ) ) {
			return array( $subject, $body );
		}
		$note = __( 'Possible spam or sales email. It was sent to you anyway so nothing genuine is missed.', 'adaptive-content-enhancer' ) . "\n"
			/* translators: %d: screening score. */
			. sprintf( __( 'Why (score %d):', 'adaptive-content-enhancer' ), (int) $screen['score'] ) . "\n- " . implode( "\n- ", $screen['reasons'] ) . "\n\n-----\n\n";
		return array( __( '[Possible spam]', 'adaptive-content-enhancer' ) . ' ' . $subject, $note . $body );
	}

	/** Weighted phrase groups. Each group's contribution is capped so one long pitch cannot dominate. */
	private static function patterns(): array {
		$rules = array(
			'seo' => array(
				'label' => 'SEO or web services pitch',
				'weight' => 2,
				'cap' => 6,
				'terms' => array(
					'/\bseo\b/',
					'/\b(?:first|top|1st) page of google\b/',
					'/\brank(?:ing)? (?:higher )?on google\b/',
					'/\bbacklinks?\b/',
					'/\bguest post(?:ing)?\b/',
					'/\b(?:web ?site|web) (?:design|redesign|development) (?:services?|agency|company)\b/',
					'/\b(?:organic )?traffic to your (?:web ?site|site)\b/',
					'/\byour (?:web ?site|site) (?:is not|isn\'t) (?:ranking|showing)\b/',
					'/\bdomain authority\b/',
					'/\bnoticed (?:a few|some|several) (?:seo |website )?(?:opportunities|issues|errors)\b/',
				),
			),
			'sales' => array(
				'label' => 'Sales or marketing pitch',
				'weight' => 2,
				'cap' => 6,
				'terms' => array(
					'/\blead generation\b/',
					'/\b(?:increase|boost|grow) your (?:sales|revenue|leads|business)\b/',
					'/\b(?:digital )?marketing (?:agency|services|campaign)\b/',
					'/\b(?:app|software) development (?:services?|company)\b/',
					'/\boutsourc(?:e|ing)\b/',
					'/\bvirtual assistants?\b/',
					'/\bbook a (?:quick )?(?:call|demo|meeting)\b/',
					'/\bfree (?:audit|consultation|trial|report)\b/',
					'/\bpartnership opportunit/',
					'/\bunsubscribe\b/',
					'/\bwe are a (?:leading|professional|trusted|reputable)\b/',
					'/\b(?:sponsored|advertis(?:e|ing) on your)\b/',
				),
			),
			'rfq' => array(
				'label' => 'Bulk RFQ or scam pattern',
				'weight' => 2,
				'cap' => 6,
				'terms' => array(
					'/\burgent (?:order|purchase|requirement)\b/',
					'/\b(?:send|share|provide) (?:us )?(?:your |the )?(?:latest )?(?:price ?list|catalog(?:ue)?s?)\b/',
					'/\brfq\b/',
					'/\brequest for (?:quotation|proposal)\b/',
					'/\bbudget (?:is|has been) (?:allocated|approved)\b/',
					'/\b(?:owner|managing director|senior executive|ceo)\b.{0,40}\b(?:responsible|direct this)\b/',
					'/\bstill accepting (?:new )?orders\b/',
					'/\b(?:see|find|open|download) (?:the )?(?:attached|attachment|file) (?:below|here)\b/',
					'/\bmy colleague contacted you\b/',
					'/\bspokesperson\b/',
					'/\b(?:representative|agent) in your (?:country|region|area)\b/',
				),
			),
			'money' => array(
				'label' => 'Finance or crypto offer',
				'weight' => 3,
				'cap' => 6,
				'terms' => array(
					'/\b(?:bitcoin|crypto(?:currency)?|forex|nft)\b/',
					'/\b(?:business )?(?:loan|funding|finance) (?:offer|approval|options)\b/',
					'/\binvestment opportunit/',
					'/\bguaranteed (?:returns?|income|profit)\b/',
				),
			),
			'filler' => array(
				'label' => 'Template phrasing',
				'weight' => 1,
				'cap' => 2,
				'terms' => array(
					'/\bkindly\b/',
					'/\bdear (?:sir|madam|sir\/madam|business owner|website owner)\b/',
					'/\bi came across your (?:web ?site|site|company)\b/',
				),
			),
		);
		/** Lets a site add or tune phrase groups (label, weight, cap, terms as lower-case regexes). */
		return (array) apply_filters( 'ace_forms_screen_patterns', $rules );
	}
}
