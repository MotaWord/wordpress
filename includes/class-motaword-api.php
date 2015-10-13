<?php

/**
 * Class MotaWord_API
 *
 * Simple SDK to communicate with MotaWord API. Supports sandboxing.
 */
class MotaWord_API {
	/**
	 * Base API URL
	 */
	protected static $PRODUCTION_URL = 'https://api.motaword.com';

	/**
	 * Base API URL for sandbox mode
	 */
	protected static $SANDBOX_URL = 'https://sandbox.motaword.com';

	/**
	 * @var bool
	 */

	private $useSandbox = false;
	/**
	 * @var string
	 */
	private $clientId;

	/**
	 * @var string
	 */
	private $clientSecret;

	/**
	 * Should we cache some of API transactions using Transient API of WP?
	 *
	 * @var bool
	 */
	public static $isCached = true;
	/**
	 * Cache key prefix
	 *
	 * @var string
	 */
	public static $cachePrefix = 'motaword_';
	/**
	 * Transient expiration in seconds.
	 * Static information such as language list is cached infinitely.
	 *
	 * @var int
	 */
	public static $cacheExpiration = 300; //seconds

	/**
	 * Construct the API
	 *
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param bool $useSandbox
	 * @param bool $isCached
	 * @param string $cachePrefix
	 * @param int $cacheExpiration
	 */
	function __construct( $clientId, $clientSecret, $useSandbox = false, $isCached = true, $cachePrefix = 'motaword_', $cacheExpiration = 300 ) {
		$this->useSandbox        = (bool) $useSandbox;
		$this->clientId          = $clientId;
		$this->clientSecret      = $clientSecret;
		static::$isCached        = $isCached;
		static::$cachePrefix     = $cachePrefix;
		static::$cacheExpiration = $cacheExpiration;

		if((bool)$useSandbox) {
			static::$cachePrefix = static::$cachePrefix.'sandbox_';
		}
	}

	public static function setEndpoint($production, $sandbox) {
		self::$PRODUCTION_URL = $production;
		self::$SANDBOX_URL = $sandbox;
	}

	/**
	 * Gets a list of supported languages from the API
	 *
	 * @return object
	 */
	public function getLanguages() {
		$result = null;

		if ( static::$isCached ) {
			if ( ( $result = get_site_transient( static::$cachePrefix . 'languages' ) ) === false ) {
				$result = $this->get( 'languages' );
				set_site_transient( static::$cachePrefix . 'languages', $result );
			}
		} else {
			$result = $this->get( 'languages' );
		}

		if ( isset( $result->error ) ) {
			return null;
		}

		return $result;
	}

	/**
	 * Gets project details.
	 *
	 * @param int $projectId MotaWord project ID
	 *
	 * @return object
	 */
	public function getProject( $projectId ) {
		$result = null;

		if ( static::$isCached ) {
			if ( ( $result = get_site_transient( static::$cachePrefix . 'project_' . $projectId ) ) === false ) {
				$result = $this->get( 'projects/' . $projectId );

				if ( ! ! $result && ! isset( $result->error ) ) {
					set_site_transient( static::$cachePrefix . 'project_' . $projectId, $result, static::$cacheExpiration );
				}
			}
		} else {
			$result = $this->get( 'projects/' . $projectId );
		}

		if ( isset( $result->error ) ) {
			return null;
		}

		return $result;
	}

	/**
	 * Gets project progress.
	 *
	 * @param int $projectId MotaWord project ID
	 *
	 * @return object
	 */
	public function getProgress( $projectId ) {
		$result = null;

		if ( static::$isCached ) {
			if ( ( $result = get_site_transient( static::$cachePrefix . 'progress_' . $projectId ) ) === false ) {
				$result = $this->get( 'projects/' . $projectId . '/progress' );

				if ( ! ! $result && ! isset( $result->error ) ) {
					set_site_transient( static::$cachePrefix . 'progress_' . $projectId, $result, static::$cacheExpiration );
				}
			}
		} else {
			$result = $this->get( 'projects/' . $projectId . '/progress' );
		}

		if ( isset( $result->error ) ) {
			return null;
		}

		return $result;
	}

	/**
	 * Submits project (does not launch it yet)
	 *
	 * @param array $data
	 *
	 * @return object
	 */
	public function submitProject( array $data ) {
		return $this->post( 'projects', $data, true );
	}

	/**
	 * Submits project (does not launch it yet)
	 *
	 * @param integer $projectId
	 *
	 * @return object
	 */
	public function downloadProject( $projectId ) {
		$response = $this->post(
			'projects/' . $projectId . '/package',
			array( 'async' => 0 )
		);

		return $response;
	}

	/**
	 * Launch a project
	 *
	 * @param integer $projectId
	 * @param array $data
	 *
	 * @return object
	 */
	public function launchProject( $projectId, $data = array() ) {
		return $this->post( 'projects/' . $projectId . '/launch', $data, false );
	}

	/**
	 * Get a new access token. Once we retrieve one, we store it in the Transient storage for a while ('expires' result)
	 * to prevent unnecessary calls.
	 *
	 * @param bool $forceNew When true, we'll retrieve a new access token even though the previous one was not
	 *                          expired yet.
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	protected function getAccessToken( $forceNew = false ) {
		$accessToken = null;

		if ( $forceNew === true || ! ( $accessToken = get_site_transient( static::$cachePrefix . 'access_token' ) ) ) {
			$options = array(
				'headers' => array(
					'User-Agent'    => $this->getUserAgent(),
					'Accept'        => 'application/json',
					'Authorization' => 'Basic ' . base64_encode(
							$this->clientId . ':' . $this->clientSecret
						),
					'Content-Type'  => 'application/x-www-form-urlencoded'
				),
				'body'    => http_build_query( array( 'grant_type' => 'client_credentials' ) ),
				'method'  => 'POST'
			);

			if ( $this->useSandbox ) {
				$url = self::$SANDBOX_URL . '/token';
			} else {
				$url = self::$PRODUCTION_URL . '/token';
			}

			$response = wp_remote_post( $url, $options );

			if(!$response || $response instanceof WP_Error) {
				return null;
			}

			$response = $response['body'];
			$response = json_decode( $response, true );

			if ( isset( $response['access_token'] ) ) {
				$accessToken = $response['access_token'];
				set_site_transient( static::$cachePrefix . 'access_token', $accessToken, ( $response['expires_in'] - 300 ) );
			}
		}

		return $accessToken;
	}

	/**
	 * Convert error object returned from MW API response to string.
	 *
	 * @param \stdClass $response
	 *
	 * @return bool|null|string
	 */
	protected function flattenError( stdClass $response ) {
		$result = null;

		$response = json_decode( $response->data );

		if ( ! $response ) {
			if ( is_object( $response ) && property_exists( $response, 'error' ) ) {
				return $response->error;
			} else {
				return false;
			}
		}

		if ( isset( $response->error ) || isset( $response->errors ) ) {
			if ( isset( $response->errors ) ) {
				$error = $response->errors[0];
			} else {
				$error = $response->error;
			}

			$errMsg  = null;
			$errCode = null;

			if ( isset( $error->code ) ) {
				$errCode = $error->code;
			}

			if ( isset( $error->message ) && ( ( isset( $error->code ) && $error->code !== $error->message ) || ! isset( $error->code ) ) ) {
				$errMsg = $error->message;
			}

			$result = $errCode . ': ' . $errMsg;
		}

		return $result;
	}

	/**
	 * Encode post body.
	 *
	 * @param $boundary
	 * @param $params
	 *
	 * @return string
	 */
	function multipart_encode( $boundary, $params ) {
		$output = "";
		foreach ( $params as $key => $value ) {
			$output .= "--$boundary\r\n";

			if ( substr( $value, 0, 1 ) === '@' ) {
				$output .= $this->multipart_enc_file( $key, $value );
			} else {
				$output .= $this->multipart_enc_text( $key, $value );
			}
		}

		$output .= "--$boundary\r\n";

		return $output;
	}

	/**
	 * Regular form body.
	 *
	 * @param string $name Form input name
	 * @param string $value Form input value
	 *
	 * @return string
	 */
	function multipart_enc_text( $name, $value ) {
		return "Content-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
	}

	/**
	 * File upload form body.
	 *
	 * @param string $key Form input name
	 * @param string $path File path
	 *
	 * @return string
	 */
	function multipart_enc_file( $key, $path ) {
		if ( substr( $path, 0, 1 ) == "@" ) {
			$path = substr( $path, 1 );
		}

		if ( strpos( $path, ';filename=' ) === false ) {
			$fileName = basename( $path );
		} else {
			$path     = explode( ';filename=', $path );
			$fileName = $path[1];
			$path     = $path[0];
		}

		$mimetype = "application/octet-stream";
		$data     = "Content-Disposition: form-data; name=\"" . $key . "\"; filename=\"$fileName\"\r\n";
		$data .= "Content-Transfer-Encoding: binary\r\n";
		$data .= "Content-Type: $mimetype\r\n\r\n";
		$data .= file_get_contents( $path ) . "\r\n";

		return $data;
	}

	/**
	 * Prepare data to be sent via an HTTP request.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	function prepareData( $data ) {
		$result = array();

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $datum ) {
				if ( is_array( $datum ) ) {
					foreach ( $datum as $i => $datuman ) {
						$result[ $key . '[' . '' . ']' ] = $datuman;
					}
				} else {
					$result[ $key ] = $datum;
				}
			}
		} else {
			return $data;
		}

		return $result;
	}

	/**
	 * Builds user agent info.
	 *
	 * @return string
	 */
	protected function getUserAgent() {
		global $wp_version;

		return apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );

	}

	/**
	 * HTTP GET
	 *
	 * @param string $path Relative API resource path
	 * @param array $data Query parameters
	 *
	 * @return object
	 */
	protected function get( $path, $data = array() ) {
		return $this->request( $path, 'GET', $data );
	}

	/**
	 * HTTP POST
	 *
	 * @param string $path Relative API resource path
	 * @param array $data Post form data
	 * @param boolean $upload Is this a file upload?
	 *
	 * @return object
	 */
	protected function post( $path, $data = array(), $upload = false ) {
		return $this->request( $path, 'POST', $data, $upload );
	}

	/**
	 * HTTP PUT
	 *
	 * @param string $path Relative API resource path
	 * @param array $data Put body parameters
	 *
	 * @return object
	 */
	protected function put( $path, $data = array() ) {
		return $this->request( $path, 'PUT', $data );
	}

	/**
	 * HTTP request base
	 *
	 * @param string $path Relative API resource path
	 * @param string $method HTTP method: GET, POST, PUT, DELETE
	 * @param array $data Request parameters
	 * @param boolean $upload Is this a file upload?
	 *
	 * @return object
	 * @throws Exception
	 */
	protected function request( $path, $method, $data = array(), $upload = false ) {
		$data = $this->prepareData( $data );

		if ( ! isset( $data['detailed'] ) ) {
			$data['detailed'] = true;
		}

		$options = array(
			'headers' => array(
				'User-Agent' => $this->getUserAgent(),
				'Accept'     => 'application/json'
			),
			'timeout' => 99999999,
			'method'  => $method,
		);

		if ( $this->useSandbox ) {
			$url = self::$SANDBOX_URL . '/' . $path;
		} else {
			$url = self::$PRODUCTION_URL . '/' . $path;
		}

		if ( $method == 'GET' || $method == 'DELETE' ) {
			$url .= "?access_token=" . $this->getAccessToken();
			$response = wp_remote_post( $url, $options );
		} else {
			$url .= "?access_token=" . $this->getAccessToken();

			if ( $upload === true ) {
				$boundary                           = uniqid();
				$options['headers']['Content-Type'] = "multipart/form-data; boundary=$boundary";
				$options['body']                    = $this->multipart_encode( $boundary, $data );
			} else {
				$options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
				$options['body']                    = http_build_query( $data );
			}

			$response = wp_remote_post( $url, $options );
		}

		if(!$response || $response instanceof WP_Error) {
			return null;
		}

		$response = json_decode( trim( $response['body'] ) );

		return $response;
	}

	public static function clear_cache() {
		global $wpdb;
		$sql = "SELECT `option_name` AS `name`
            FROM  $wpdb->options
            WHERE `option_name` LIKE '%transient_" . static::$cachePrefix . "%'
            ORDER BY `option_name`";

		$results = $wpdb->get_results( $sql );

		foreach ( $results as $result ) {
			delete_site_transient( str_replace( '_site_transient_', '', $result->name ) );
			delete_transient( str_replace( '_transient_', '', $result->name ) );

		}
	}

}
