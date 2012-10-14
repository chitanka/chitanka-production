<?php
#  browser utils
#
#  Copyright (C) 2004 Borislav Manolov
#
#  This program is in the public domain.
#
#  Author: Borislav Manolov <b.manolov at gmail dot com>
#          http://purl.org/NET/borislav/
#
#  This program uses portions of
#    Snoopy - the PHP net client
#    Author: Monte Ohrt <monte@ispi.net>
#    Copyright (c): 1999-2000 ispi, all rights reserved
#    Version: 1.01
#    http://snoopy.sourceforge.net/
#
#  Modified by Grigor Gatchev <grigor at gatchev dot info>.
#  Added support for "Content-Transfer: chunked" и "Accept-Encoding: gzip",
#  and the lastreq_time() function.
#
#############################################################################
require_once ( dirname ( __FILE__ ) . '/utils.php' );

class Browser {

	var $scheme  = '';        // connection scheme
	var $host    = '';        // host for connection
	var $port    = '';        // port for connection
	var $agent   = 'Mozilla/5.0 (PHPBrowser)'; // user agent
	var $cookies = array();   // cookies
	var $print_cookies = false; // whether to print cookies
	var $cookies_file = '';

	var $use_compression = true;

	# data for basic HTTP Authentication
	var $user = '';
	var $pass = '';

	var $conn_timeout = 120;  // timeout for socket connection

	var $fetch_method  = 'GET';     // fetch method
	var $submit_method = 'POST';    // submit method
	var $http_version  = 'HTTP/1.1';// http version
	var $content_type  = array(     // content types
		'text' => 'application/x-www-form-urlencoded',
		'binary' => 'multipart/form-data'
	);
	var $mime_boundary = ''; // MIME boundary for binary submission

	var $postdata_size;    // size in bytes of the last postdata sent
	var $lastreq_begtime;  // timestamp of the last request start
	var $lastreq_endtime;  // timestamp of the last request end

	var $content = '';        // content returned from server
	var $headers = array();   // headers returned from server

	var $error        = '';   // error messages
	var $is_redirect = false; // true if the fetched page is a redirect

	var $bytecounters;  // download / upload counters
	var $limits; // speed limits

	# constructor
	# $params - assoc array (name => value)
	# return nothing
	function Browser($params = array()) {
		settype($params, 'array');
		foreach ( $params as $field => $value ) {
			if ( isset($this->$field) ) {
				$this->$field = $value;
			}
		}
		$this->read_cookies();
		$this->mime_boundary = 'PHPBrowser' . md5( uniqid( microtime() ) );
		$this->reset_bytecounters();
	}


	# fetch a page
	# $uri - location of the page
	# $do_auth:boolean - add an authentication header
	# return true by success
	function fetch($uri, $do_auth = false) {
		return $this->make_request($uri, $this->fetch_method, '', '', $do_auth);
	}


	# submit an http form
	# $uri  - the location of the page to submit
	# $vars - assoc array with form fields and their values
	# $file - assoc array (field name => file name)
	#         set only by upload
	# $do_auth:boolean - add an authentication header
	# return true by success
	function submit( $uri, $vars = array(), $file = array(), $do_auth = false ) {
		$postdata = '';
		if ( empty($file) ) {
			if ( ! empty ( $vars ) ) {
				foreach ( $vars as $key => $val ) {
					if ( is_array ( $val ) ) {
						foreach ( $val as $sub ) {
							$postdata .= urlencode($key."[]") .'='. urlencode($sub) . '&';
						}
					} else {
						$postdata .= urlencode($key) .'='. urlencode($val) .'&';
					}
				}
			}
		} else {
			foreach ( $vars as $key => $val ) {
				$postdata .= '--'. $this->mime_boundary ."\r\n";
				$postdata .= 'Content-Disposition: form-data; name="'. $key ."\"\r\n\r\n";
				$postdata .= $val . "\r\n";
			}

			list($field_name, $file_name) = each($file);
			if ( !is_readable($file_name) ) {
				$this->error = 'File "' . $file_name . '" is not readable.';
				return false;
			}

			$fp = fopen($file_name, 'r');
			$file_content = fread( $fp, filesize($file_name) );
			fclose($fp);
			$base_name = basename($file_name);

			$postdata .= '--'. $this->mime_boundary ."\r\n";
			$postdata .= 'Content-Disposition: form-data; name="'. $field_name .
						'"; filename="' . $base_name . "\"\r\n\r\n";
			$postdata .= $file_content . "\r\n";
			$postdata .= '--'. $this->mime_boundary ."--\r\n";
		}

		$content_type = empty($file)
			? $this->content_type['text']
			: $this->content_type['binary'];

		return $this->make_request($uri, $this->submit_method, $content_type,
			$postdata, $do_auth);
	}

	# get data from server
	# $uri - the location the page
	# $request_method - GET / POST
	# $content_type - content type (for POST submission)
	# $postdata - data (for POST submission)
	# $do_auth:boolean - add an authentication header based on $this->user and $this->pass
	# return true if the request succeeded, false otherwise
	function make_request( $uri, $request_method, $content_type = '',
		$postdata = '', $do_auth = false ) {

		$this->delay_if_needed();

		$this->postdata_size = strlen ( $postdata );

		$uri_parts = parse_url ( $uri );
		if ( ! in_array ( $uri_parts['scheme'], array ( 'http', 'https' ) ) ) { // not a valid protocol
			$this->error = "Invalid protocol: $uri_parts[scheme]";
			return false;
		}

		$this->lastreq_begtime = time();

		$this->host = $uri_parts['host'];
		switch ( $uri_parts['scheme'] ) {
			case 'http'  : $this->scheme = '';
			               if ( empty ( $uri_parts['port'] ) ) $uri_parts['port'] = 80;
			               break;
			case 'https' : if ( ! in_array ( 'openssl', get_loaded_extensions() ) ) {
			                 $this->error = "No SSL support - cannot make HTTPS requests!";
			                 return false;
			               } else {
			                 $this->scheme = 'ssl://';
			                 if ( empty ( $uri_parts['port'] ) ) $uri_parts['port'] = 443;
			                 break;
			               }
			default : $this->error = "Inappropriate protocol: " . $uri_parts['scheme']; return false;
		}
		if ( empty ( $this->port ) ) $this->port = $uri_parts['port'];
		$fp = @fsockopen ( $this->scheme . $this->host, $this->port, $errno, $errstr, $this->conn_timeout );
		if ( !$fp ) {
			$this->error = $errno .' / Reason: '. $errstr;
			return false;
		}

		$path = $uri_parts['path'] .
			(isset($uri_parts['query']) ? '?'. $uri_parts['query'] : '');

		$cookie_headers = '';
		if ($this->is_redirect) {
			$this->set_cookies();
		}

		if ( empty($path) ) { $path = '/'; }
		$headers = "$request_method $path $this->http_version\r\n" .
			"User-Agent: $this->agent\r\n" .
			"Host: $this->host\r\n" .
			"Accept: */*\r\n";
		if ( $this->use_compression && function_exists ( "gzinflate" ) ) {
			$headers .= "Accept-Encoding: gzip\r\n";
		}

		if ($do_auth) {
			$headers .= 'Authorization: Basic '.
				base64_encode($this->user.':'.$this->pass) . "\r\n";
		}

		if ( isset($this->cookies[$this->host]) ) {
			$cookie_headers .= 'Cookie: ';
			foreach ($this->cookies[$this->host] as $cookie_name => $cookie_data) {
				$cookie_headers .= $cookie_name .'='. $cookie_data[0] .'; ';
			}
			# add $cookie_headers w/o last 2 chars
			$headers .= substr($cookie_headers, 0, -2) . "\r\n";
		}

		if ( !empty($content_type) ) {
			$headers .= "Content-type: $content_type";
			if ($content_type == $this->content_type['binary']) {
				$headers .= '; boundary=' . $this->mime_boundary;
			}
			$headers .= "\r\n";
		}
		if ( !empty($postdata) ) {
			$headers .= "Content-length: ". strlen($postdata) ."\r\n";
		}
		$headers .= "\r\n";

		$data = $headers . $postdata;
		$datalen = strlen ( $data );
		fwrite( $fp, $data, $datalen );
		$this->bytecounters['total']['UL']['compressed'  ] += $datalen;
		$this->bytecounters['total']['UL']['uncompressed'] += $datalen;
		$this->bytecounters['last' ]['UL']['compressed'  ]  = $datalen;
		$this->bytecounters['last' ]['UL']['uncompressed']  = $datalen;

		$this->is_redirect = false;
		$this->headers = array();

		$headers_len = 0;
		while ( $curr_header = fgets($fp, 4096) )  {
			if ($curr_header == "\r\n") break;
			# if a header begins with Location: or URI:, set the redirect
			if ( preg_match('/^(Location:|URI:)[ ]+(.*)/', $curr_header, $matches) ) {
				$this->is_redirect = rtrim($matches[2]);
			}
			$this->headers[] = $curr_header;
			$headers_len += strlen ( $curr_header );
		}

		$content = '';
		if ( in_array ( "Transfer-Encoding: chunked\r\n", $this->headers ) ) {
			while ( true ) {
				$chunk_size = fgets ( $fp );
				$chunk_size_dec = hexdec ( $chunk_size );
				if ( $chunk_size_dec == 0 ) {
					fgets ( $fp );  // remove trailing CRLF
					break;
				} else {
					$chunk = '';
					while ( $chunk_size_dec - strlen ( $chunk ) > 0 ) {
						if ( ! ( $chunk_piece = fread ( $fp, $chunk_size_dec - strlen ( $chunk ) ) ) ) { break; }
						$chunk .= $chunk_piece;
					}
					fgets ( $fp ); // remove trailing CRLF
				}
				$content .= $chunk;
			}
			fgets ( $fp );
		} else {
			while ( $data = fread($fp, 500000) ) {
				$content .= $data;
			}
		}
		$this->content = $content;
		$this->bytecounters['total']['DL']['compressed'] += strlen ( $this->content ) + $headers_len;
		$this->bytecounters['last' ]['DL']['compressed']  = strlen ( $this->content ) + $headers_len;
		fclose($fp);

		$this->lastreq_endtime = time();

		if ( in_array ( "Content-Encoding: gzip\r\n", $this->headers ) ) {
			$this->content = gzinflate ( substr ( $this->content, 10 ) );  // the 10 bytes stripped are the "member header" of the gzip compression;
				// gzdecode() should be a cleaner implementation, parsing the header intelligiently, but is still unavailable in PHP 5.2.6.
		}
		$this->bytecounters['total']['DL']['uncompressed'] += strlen ( $this->content ) + $headers_len;
		$this->bytecounters['last' ]['DL']['uncompressed']  = strlen ( $this->content ) + $headers_len;

		if ( $this->content === false ) {
			$this->error = 'Data decompression failed';
			return false;
		}

		if ($this->is_redirect) {
			$this->make_request($this->is_redirect, $request_method,
				$content_type, $postdata);
		}
		$this->set_cookies();
		return true;
	}

	public function lastreq_time () {
		return round ( ( $this->lastreq_begtime + $this->lastreq_endtime ) / 2 );
	}

	public function reset_bytecounters () {
		$bytecounters = $this->bytecounters;
		$this->bytecounters = array (
			'total' => array (
				'DL' => array (
					'compressed'   => 0,
					'uncompressed' => 0,
				),
				'UL' => array (
					'compressed'   => 0,
					'uncompressed' => 0,
				),
			),
			'last' => array (
				'DL' => array (
					'compressed'   => 0,
					'uncompressed' => 0,
				),
				'UL' => array (
					'compressed'   => 0,
					'uncompressed' => 0,
				),
			),
		);
		return $bytecounters;
	}

	public function has_cookies_for ( $host ) {
		return ( ! empty ( $this->cookies[$host] ) );
	}

	private function delay_if_needed() {
		if ( empty ( $this->limits ) ) return;
		if ( ! is_array ( $this->limits ) ) {
			$this->limits = array ( 'total' => $this->limits, 'DL' => PHP_INT_MAX, 'UL' => PHP_INT_MAX );
		}
		if ( ! empty ( $this->limits['total'] ) ) {
			$secs_total = ( $this->bytecounters['last']['DL']['compressed'] +
			                $this->bytecounters['last']['UL']['compressed'] ) / $this->limits['total'];
		}
		if ( ! empty ( $this->limits['DL'] ) ) {
			$secs_DL = $this->bytecounters['last']['DL']['compressed'] / $this->limits['DL'];
		}
		if ( ! empty ( $this->limits['UL'] ) ) {
			$secs_UL = $this->bytecounters['last']['UL']['compressed'] / $this->limits['UL'];
		}
		$wait_secs = max ( $secs_total, $secs_DL, $secs_UL ) + $this->lastreq_begtime - time();
		@sleep ( $wait_secs );
	}

	# read cookies from file
	private function read_cookies() {
		if (file_exists($this->cookies_file)) {
			$curr_time = time();
			$lines = file($this->cookies_file);
			foreach ($lines as $line) {
				$line = trim($line);
				if ( empty($line) ) { continue; }
				list($host, $cookie_expire, $cookie_name, $cookie_val) = explode("\t", $line);
				# add cookie if not expired
				if ($curr_time < $cookie_expire) {
					$this->cookies[$host][$cookie_name] = array($cookie_val, $cookie_expire);
				}
			}
			# write not expired cookies back to file
			$cookies_str = '';
			foreach ($this->cookies as $host => $cookie_data) {
				foreach ($cookie_data as $cookie_name => $cookie_subdata) {
					$cookies_str .= "$host\t$cookie_subdata[1]\t$cookie_name\t$cookie_subdata[0]\n";
				}
			}
			if ( ! empty ( $this->cookies_file ) ) {
				my_fwrite($this->cookies_file, $cookies_str, 'w');
			}
		}
	}

	# set cookies
	private function set_cookies() {
		$cookies_str = '';
		$len = count($this->headers);
		for ($i = 0; $i < $len; $i++) {
			if (preg_match('/^Set-Cookie:\s+([^=]+)=([^;]+);\s+(expires=([^;]+))?/i',
					$this->headers[$i], $matches)) {
				$exp_time = isset($matches[4]) ? strtotime($matches[4]) : time() + 60*60*24*30;
				$cookies_str .= "$this->host\t$exp_time\t$matches[1]\t$matches[2]\n";
				$this->cookies[$this->host][$matches[1]] = array($matches[2], $exp_time);
				if ( $this->print_cookies ) {
					echo "$matches[1] = $matches[2]; expires at " . @$matches[4] ."\n";
				}
			}
		}
		$cookies_str = '';
		foreach ( $this->cookies as $hostname => $cookies_host ) {
			if ( is_array ( $cookies_host ) ) {
				foreach ( $cookies_host as $name => $valuesarray ) {
					$cookies_str .= "$hostname\t$valuesarray[1]\t$name\t$valuesarray[0]\n";
				}
			}
			if ( ! empty ( $this->cookies_file ) ) {
				my_fwrite($this->cookies_file, $cookies_str, 'w');
			}
		}
	}

} // end of class Browser
