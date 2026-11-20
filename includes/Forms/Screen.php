<?php
/** Content screen for enquiries: holds sales pitches, SEO offers and bulk RFQ scams for review. */
namespace ACE\AdaptiveContentEnhancer\Forms;

defined( 'ABSPATH' ) || exit;

final class Screen {
	/** Score at or above which an enquiry is tagged as possible spam. */
	public const THRESHOLD = 4;

	/**
	 * Score a submission before notification delivery. Explicit reviewer decisions teach future screening.
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

		// Script and language are not evidence of spam. Inspect actual enquiry content instead.
		$messages = array();
		foreach ( $form['fields'] ?? array( 'message' => array( 'type' => 'textarea' ) ) as $key => $field ) {
			if ( 'textarea' === ( $field['type'] ?? '' ) && is_scalar( $values[ $key ] ?? null ) ) {
				$messages[] = (string) $values[ $key ];
			}
		}
		$message = wp_specialchars_decode( implode( "\n", $messages ), ENT_QUOTES );
		foreach ( self::content_reasons( $message, $contact_name ) as $reason ) {
			$score += self::THRESHOLD;
			$reasons[] = $reason;
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
		$result = Review::assess( $form, $values, $result );
		/** Lets a site adjust the verdict, e.g. allow-list a known customer domain. */
		return (array) apply_filters( 'ace_forms_screen_result', $result, $form, $values, $contact_email );
	}

	/** Strong combinations, rather than individual words a genuine buyer could use. */
	private static function content_reasons( string $message, string $contact_name ): array {
		$reasons = array();
		$text = strtolower( preg_replace( '/\s+/u', ' ', $message ) ?? $message );
		$generic_supply = preg_match( '/\b(?:long[ -]term (?:contract|supply|business)|(?:contract|supply) (?:for |of |with )?(?:your|the) products|supply of your products)\b/', $text );
		$catalogue = preg_match( '/\b(?:catalog(?:ue)?s?|brochures?)\b/', $text );
		$trade = 0;
		foreach ( array( '/\b(?:fob|exw|cif)\b/', '/\b(?:usd|euro|eur|us dollars?)\b/', '/\b(?:bulk|wholesale) (?:pric(?:e|es|ing)|discounts?)\b/', '/\bpayment (?:terms|methods|conditions)\b/', '/\bshipping (?:terms|options|details|costs?|conditions)\b/' ) as $pattern ) {
			$trade += (int) (bool) preg_match( $pattern, $text );
		}
		// A concrete quoted quantity/specification is buying context, even when polite trade phrases overlap.
		$specific_request = preg_match( '/\b(?:quote|quotation|price|order|need|require|buy|purchase)\b[^.!?]{0,70}\b\d+(?:[.,]\d+)?\s+(?!(?:products?|items?|units?|usd|eur|euro|dollars?|bulk|wholesale)\b)[a-z][a-z-]*/', $text );
		if ( ! $specific_request && $generic_supply && preg_match( '/\b(?:your|the) products\b/', $text ) && $catalogue && $trade >= 2 ) {
			$reasons[] = 'Generic procurement template: generic supply proposal, catalogue and multiple trade terms';
		}

		// Rotating or spaced related hostnames inserted in prose are different from a buyer's reference URLs.
		$prose = preg_replace( '~https?://\S+|www\.\S+~iu', '', $message ) ?? $message;
		preg_match_all( '/\b([a-z][a-z-]{2,25})\s*(\d{1,3})?\s*(\.|\s)\s*(com|net|org|co|at|biz|info|io|eu|uk|de|fr|ru)\b/i', $prose, $hosts, PREG_SET_ORDER );
		$families = array();
		foreach ( $hosts as $host ) {
			$key = substr( strtolower( $host[1] ), 0, 4 );
			$families[ $key ]['hosts'][ strtolower( $host[1] . $host[2] . '.' . $host[4] ) ] = true;
			$families[ $key ]['numbered'] = ! empty( $families[ $key ]['numbered'] ) || '' !== $host[2];
			$families[ $key ]['spaced'] = ( $families[ $key ]['spaced'] ?? 0 ) + (int) ( '.' !== $host[3] );
		}
		foreach ( $families as $family ) {
			$count = count( $family['hosts'] );
			if ( $family['numbered'] && ( $count >= 3 || ( $count >= 2 && $family['spaced'] >= 2 ) ) ) {
				$reasons[] = 'Repeated or obfuscated site promotion: related rotating or spaced hostnames';
				break;
			}
		}
		if ( preg_match_all( '/\[url(?:=|\])/i', $message ) >= 3 ) {
			$reasons[] = 'Repeated or obfuscated site promotion: repeated formatted links';
		}
		if ( self::signature_only( $message, $contact_name ) ) {
			$reasons[] = 'Insufficient enquiry content: contact signature only';
		}
		return $reasons;
	}

	/** Hold incomplete contact signatures for a person to review; never call them confirmed spam. */
	private static function signature_only( string $message, string $contact_name ): bool {
		$lines = preg_split( '/\R/u', trim( $message ), -1, PREG_SPLIT_NO_EMPTY ) ?: array();
		if ( count( $lines ) < 3 ) {
			return false;
		}
		$contact_lines = 0;
		$organisation_or_address = false;
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^(?:tel(?:ephone)?|phone|mobile|fax|e[ -]?mail|website|web)\s*[:：]\s*\S.+$/iu', $line ) ) {
				++$contact_lines;
				continue;
			}
			if ( preg_match( '/^(?:address|company|organisation|organization)\s*[:：]\s*\S.+$/iu', $line ) || preg_match( '/^[\p{L}\p{N}\s&.,()\'-]+\b(?:ltd|limited|llc|gmbh|inc|corporation)\.?$/iu', $line ) ) {
				$organisation_or_address = true;
				continue;
			}
			if ( '' !== trim( $contact_name ) && 0 === strcasecmp( $line, trim( $contact_name ) ) ) {
				continue;
			}
			if ( preg_match( '/^(?:regards|best regards|kind regards|sincerely|agent)[,.!]?$/i', $line ) ) {
				continue;
			}
			// Any unrecognised prose is kept, including brief or multilingual enquiries.
			return false;
		}
		return $contact_lines >= 2 && $organisation_or_address;
	}

	/** Subject prefix and body preamble for a tagged notification. */
	public static function tag( array $screen, string $subject, string $body ): array {
		if ( 'suspect' !== ( $screen['verdict'] ?? '' ) ) {
			return array( $subject, $body );
		}
		$note = __( 'Possible spam or sales email. Held for review before delivery.', 'adaptive-content-enhancer' ) . "\n"
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
