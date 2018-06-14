<?php

namespace Wikisource\WsContest;

class Str extends \Illuminate\Support\Str {

	/**
	 * Explode a newline-delimited string and remove duplicates and whitespace.
	 * @param string $str
	 * @param string $delimiter
	 * @return array
	 */
	public static function explode( $str, $delimiter = "\n" ) {
		return array_filter( array_map( 'trim', explode( $delimiter, $str ) ) );
	}
}
