<?php

	$file = file_get_contents( 'http://www.iso.org/iso/home/standards/country_codes/country_names_and_code_elements_xml.htm', true );
	$file = str_replace( 'encoding="ISO-8859-1"', 'encoding="UTF-8"', $file );
	$xml = simplexml_load_string($file);
 
	$array = $code = $countries = array();
 
	foreach( $xml as $key => $value ) {
		$array[] = (array) $value;
		foreach( $array as $k => $v ) {
			$code[$k] = array_values( $v );
			foreach( $code[$k] as $c ) {
				$countries[$code[$k][1]] = ucwords( strtolower( utf8_decode ($code[$k][0] )) );
			}
		}
	}
 
	print_r( $countries );
