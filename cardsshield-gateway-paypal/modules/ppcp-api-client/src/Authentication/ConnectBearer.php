<?php
/**
 * The connect dummy bearer.
 *
 * @package WooCommerce\LazyPaypal\ApiClient\Authentication
 */

declare(strict_types=1);

namespace WooCommerce\LazyPaypal\ApiClient\Authentication;

use WooCommerce\LazyPaypal\ApiClient\Entity\Token;

/**
 * Class ConnectBearer
 */
class ConnectBearer implements Bearer {

	/**
	 * Returns the bearer.
	 *
	 * @return Token
	 */
	public function bearer(): Token {
		$data = (object) array(
			'created'    => time(),
			'expires_in' => 3600,
			'token'      => 'token',
		);
		return new Token( $data );
	}
}
