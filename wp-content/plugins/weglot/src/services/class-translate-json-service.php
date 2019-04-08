<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_Json_Inline_Weglot;
use WeglotWP\Helpers\Helper_Keys_Json_Weglot;

use Weglot\Client\Api\WordCollection;
use Weglot\Client\Api\TranslateEntry;
use Weglot\Client\Endpoint\Translate;
use Weglot\Client\Api\WordEntry;
use Weglot\Client\Api\Enum\WordType;
use JsonPath\JsonObject;
use Weglot\Parser\ConfigProvider\ServerConfigProvider;

/**
 * @since 2.6.0
 */
class Translate_Json_Service {

	/**
	 * @since 2.6.0
	 * @var integer
	 */
	protected $index       = 0;

	/**
	 * @since 2.6.0
	 * @var integer
	 */
	protected $limit       = 0;

	/**
	 * @since 2.6.0
	 * @var array
	 */
	protected $indexes     = [];

	/**
	 * @since 2.6.0
	 * @var array
	 */
	protected $collections = [];

	/**
	 * @since 2.6.0
	 */
	public function __construct() {
		$this->replace_url_services             = weglot_get_service( 'Replace_Url_Service_Weglot' );
		$this->replace_link_services            = weglot_get_service( 'Replace_Link_Service_Weglot' );
		$this->parser_services                  = weglot_get_service( 'Parser_Service_Weglot' );
	}

	/**
	 * @since 2.6.0
	 *
	 * @param array $json
	 * @param string $path
	 * @return array
	 */
	protected function check_json_to_translate( $json, $path = '$' ) {
		foreach ( $json as $key => $val ) {
			if ( is_array( $val ) ) {
				if ( is_string( $key ) ) {
					if ( false === strpos( $key, '.' ) ) {
						$newpath = "$path.$key";
					} else {
						$newpath = sprintf( '%s["%s"]', $path, $key );
					}
				} else {
					$newpath = sprintf( '%s[%s]', $path, $key );
				}

				$this->check_json_to_translate( $val, $newpath );
			} else {
				if ( false === strpos( $key, '.' ) ) {
					$newpath = "$path.$key";
				} else {
					$newpath = sprintf( '%s["%s"]', $path, $key );
				}

				if ( Helper_Json_Inline_Weglot::is_ajax_html( $val ) ) {
					try {
						$parser                            = $this->parser_services->get_parser();
						$words                             = $parser->parse( $val );
						if ( ! $words instanceof WordCollection ) {
							continue;
						}
						$this->collections                 = array_merge( $this->collections, $words->jsonSerialize() );
						$this->limit                       = $this->index + $words->count();
						$this->indexes[ $newpath ]         = [
							'start' => $this->index,
							'limit' => $this->limit,
						]; //phpcs:ignore
						$this->index += $words->count();
					} catch ( \Exception $e ) {
						continue;
					}
				} else {
					if ( Helper_Keys_Json_Weglot::translate_key_for_path( $key ) ) {
						try {
							$parser                    = $this->parser_services->get_parser();
							$words                     = $parser->parse( $val );
							if ( ! $words instanceof WordCollection ) {
								continue;
							}
							$this->collections         = array_merge( $this->collections, $words->jsonSerialize() );
							$this->limit               = $this->index + $words->count();
							$this->indexes[ $newpath ] = [
								'start' => $this->index,
								'limit' => $this->limit,
							];
							$this->index += $words->count();
						} catch ( \Exception $e ) {
							continue;
						}
					}
				}
			}
		}

		return [
			$this->indexes,
			$this->collections,
		];
	}

	/**
	 * @since 2.6.0
	 * @param array $json
	 * @return JsonObject
	 */
	protected function translate_json_strings( $json ) {
		$parser = $this->parser_services->get_parser();

		// Translate endpoint parameters
		$params = [
			'language_from' => weglot_get_original_language(),
			'language_to'   => weglot_get_current_language(),
		];

		if ( $parser->getConfigProvider() instanceof ServerConfigProvider ) {
			$parser->getConfigProvider()->loadFromServer();
		}

		$params      = array_merge( $params, $parser->getConfigProvider()->asArray() );
		$json_object = new JsonObject( $json );
		try {
			$translate       = new TranslateEntry( $params );
			$word_collection = $translate->getInputWords();
			foreach ( $this->collections as $value ) {
				$word_collection->addOne( new WordEntry( $value['w'], $value['t'] ) );
			}
		} catch ( \Exception $e ) {
			return $json_object;
		}

		$translate   = new Translate( $translate, $parser->getClient() );
		$translated  = $translate->handle();

		$output_words = $translated->getOutputWords();

		if ( $output_words->count() !== count( $this->collections ) || $output_words->count() === 0 ) {
			return $json_object;
		}

		$input_words = $translated->getInputWords();
		$i           = 0;

		foreach ( $this->indexes as $path => $index ) {
			do {
				if ( is_null( $input_words[ $i ] ) || is_null( $output_words[ $i ] ) ) {
					$i++;
					continue;
				}

				$input_word  = $input_words[ $i ]->getWord();
				$output_word = $output_words[ $i ]->getWord();
				$str         = $json_object->get( $path )[0];

				$json_object->set( $path, str_replace( $input_word, $output_word, $str ) );
				$i++;
			} while ( $i < $index['limit'] );
		}

		return $json_object;
	}


	/**
	 * @since 2.6.0
	 * @param array $json
	 * @return array
	 */
	public function replace_json_links( $json ) {
		$replace_urls = apply_filters( 'weglot_ajax_replace_urls', [ 'redirecturl', 'url', 'link' ] );

		foreach ( $json as $key => $val ) {
			if ( is_array( $val ) ) {
				$json[ $key ] = $this->replace_json_links( $val );
			} else {
				if ( Helper_Json_Inline_Weglot::is_ajax_html( $val ) ) {
					$json[ $key ] = $this->replace_url_services->replace_link_in_dom( $val );
				} else {
					if ( in_array( $key,  $replace_urls, true ) ) {
						$json[ $key ] = $this->replace_link_services->replace_url( $val );
					}
				}
			}
		}

		return $json;
	}


	/**
	 * @since 2.6.0
	 * @param array $json
	 * @param mixed $path
	 * @return JsonObject
	 */
	public function translate_json( $json ) {
		$this->check_json_to_translate( $json );
		$json_object = $this->translate_json_strings( $json );
		$json        = json_decode( $json_object->getJson(), true );

		$json = $this->replace_json_links( $json );

		return $json;
	}
}



