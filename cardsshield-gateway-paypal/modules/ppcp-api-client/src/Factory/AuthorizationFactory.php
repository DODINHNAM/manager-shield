<?php
/**
 * The Authorization factory.
 *
 * @package WooCommerce\LazyPaypal\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\LazyPaypal\ApiClient\Factory;

use WooCommerce\LazyPaypal\ApiClient\Entity\Authorization;
use WooCommerce\LazyPaypal\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\LazyPaypal\ApiClient\Entity\AuthorizationStatusDetails;
use WooCommerce\LazyPaypal\ApiClient\Exception\RuntimeException;

/**
 * Class AuthorizationFactory
 */
class AuthorizationFactory {

	/**
	 * Returns an Authorization based off a PayPal response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return Authorization
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( \stdClass $data ): Authorization {
		if ( ! isset( $data->id ) ) {
			throw new RuntimeException(
				__( 'Does not contain an id.', 'woocommerce-paypal-payments' )
			);
		}

		if ( ! isset( $data->status ) ) {
			throw new RuntimeException(
				__( 'Does not contain status.', 'woocommerce-paypal-payments' )
			);
		}

		$reason = $data->status_details->reason ?? null;

		return new Authorization(
			$data->id,
			new AuthorizationStatus(
				$data->status,
				$reason ? new AuthorizationStatusDetails( $reason ) : null
			)
		);
	}
}
