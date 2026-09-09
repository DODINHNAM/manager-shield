<?php
/**
 * The RequestTrait wraps the wp_remote_get functionality for the API client.
 *
 * @package WooCommerce\LazyPaypal\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\LazyPaypal\ApiClient\Endpoint;

use WP_Error;

/**
 * Trait RequestTrait
 */
trait RequestTrait {

	/**
	 * Whether to log the detailed request/response info.
	 *
	 * @var bool
	 */
	protected $is_request_logging_enabled = false;

	/**
	 * Performs a request
	 *
	 * @param string $url The URL to request.
	 * @param array  $args The arguments by which to request.
	 *
	 * @return array|WP_Error
	 */
	private function request( string $url, array $args ) {

		$args['timeout'] = 30;

		/**
		 * This filter can be used to alter the request args.
		 * For example, during testing, the PayPal-Mock-Response header could be
		 * added here.
		 */
		$args = apply_filters( 'ppcp_request_args', $args, $url );
		if ( ! isset( $args['headers']['PayPal-Partner-Attribution-Id'] ) ) {
			$args['headers']['PayPal-Partner-Attribution-Id'] = 'Woo_PPCP';
		}

		$response = wp_remote_get( $url, $args );
		if ( $this->is_request_logging_enabled ) {
			$this->logger->debug( $this->request_response_string( $url, $args, $response ) );
		}
		return $response;
	}

	/**
	 * Returns request and response information as string.
	 *
	 * @param string         $url The request URL.
	 * @param array          $args The request arguments.
	 * @param array|WP_Error $response The response.
	 * @return string
	 */
	private function request_response_string( string $url, array $args, $response ): string {
		if ( $response instanceof WP_Error ) {
			return 'PayPal request failed.';
		}

		$status = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		return 'PayPal request completed with HTTP status ' . $status . '.';
	}
}
