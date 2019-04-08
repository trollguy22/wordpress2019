<?php

namespace WeglotWP\Third\CalderaForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WeglotWP\Helpers\Helper_Json_Inline_Weglot;

use Weglot\Client\Api\WordEntry;
use Weglot\Client\Api\Enum\WordType;
use Weglot\Client\Client;
use Weglot\Client\Endpoint\Translate;
use Weglot\Client\Api\TranslateEntry;
use Weglot\Client\Api\Enum\BotType;

/**
 * Caldera_Translate
 *
 * @since 2.6.0
 */
class Caldera_Translate {

	/**
	 * @since 2.6.0
	 * @param string $dom
	 * @return string
	 */
	protected function translate_entries( $dom ) {
		$parser = weglot_get_service( 'Parser_Service_Weglot' )->get_parser();
		return $parser->translate( $dom, weglot_get_original_language(), weglot_get_current_language() ); // phpcs:ignore
	}

	/**
	 * @since 2.7.0
	 * @param array $all_words
	 * @return array
	 */
	protected function translate_words_inline( $all_words ) {
		// TranslateEntry
		$params = [
			'language_from' => weglot_get_original_language(),
			'language_to'   => weglot_get_current_language(),
			'request_url'   => weglot_get_current_full_url(),
			'bot'           => BotType::HUMAN,
		];

		$translate = new TranslateEntry( $params );

		$word_collection = $translate->getInputWords();
		foreach ( $all_words as $value ) {
			$value = Helper_Json_Inline_Weglot::format_for_api( $value );
			$word_collection->addOne( new WordEntry( $value, WordType::TEXT ) );
		}

		$client    = new Client( weglot_get_option( 'api_key' ) );
		$translate = new Translate( $translate, $client );

		return $translate->handle();
	}

	/**
	 * @since 2.7.0
	 * @param string $content
	 * @return string
	 */
	protected function translate_i18n_inline( $content ) {
		$regex = apply_filters( 'weglot_caldera_forms_match_validator_settings', '#CF_VALIDATOR_STRINGS(.*?);#' );
		preg_match( $regex, $content, $match );
		if ( ! isset( $match[1] ) ) {
			return $content;
		}

		$regex = apply_filters( 'weglot_caldera_forms_translate_validator_settings', '#(defaultMessage|email|url|number|integer|digits|alphanum|required|pattern|min|max|range|minlength|maxlength|length|mincheck|maxcheck|check|equalto|notblank)":"(.*?)"#' );
		preg_match_all( $regex, $match[1], $all );

		if ( empty( $all[2] ) ) {
			return $content;
		}
		$current_language = weglot_get_current_language();

		$object = $this->translate_words_inline( $all[2] );

		foreach ( $object->getInputWords() as $key => $input_word ) {
			$from_input_encoding = apply_filters( 'weglot_caldera_translate_need_json_encode', true );

			$encoded = true;

			if ( in_array( $current_language, apply_filters('weglot_caldera_translate_languages_encoded_output', ['fr'] ), true ) ) {
				$encoded = false;
			}

			$to_output_encoding  = apply_filters( 'weglot_caldera_translate_need_json_encode', $encoded );
			$from_input          = $input_word->getWord();
			$to_output           = $object->getOutputWords()[ $key ]->getWord();
			if ( '' === $from_input ) {
				continue;
			}

			if ( $from_input_encoding ) {
				$from_input          = Helper_Json_Inline_Weglot::need_json_encode_api( $from_input );
			}
			if ( $to_output_encoding ) {
				$to_output          = Helper_Json_Inline_Weglot::need_json_encode_api( $to_output );
			}

			$content    = str_replace( '"' . $from_input . '"', '"' . $to_output . '"', $content );
		}

		return $content;
	}

	/**
	 * @since 2.6.0
	 *
	 * @param string $content
	 * @return array
	 */
	protected function translate_script_html_template( $content ) {
		preg_match_all( '#<script type="text\/html"(.*?)>([\s\S]*)<\/script>#mU', $content, $match );

		if ( ! isset( $match[2] ) || empty( $match[2][0] ) ) {
			return $content;
		}

		foreach ( $match[2] as $key => $template_html ) {
			$dom_translate  = $this->translate_entries( $template_html );
			$content        = str_replace( $match[2][$key], $dom_translate, $content );
		}

		return $content;
	}

	/**
	 * @since 2.6.0
	 *
	 * @param string $content
	 * @return string
	 */
	public function translate_words( $content ) {
		$content = $this->translate_script_html_template( $content );
		$content = $this->translate_i18n_inline( $content );

		return $content;
	}
}
