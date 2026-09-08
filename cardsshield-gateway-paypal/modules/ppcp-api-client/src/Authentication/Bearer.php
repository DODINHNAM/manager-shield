<?php
/**
 * The bearer interface.
 *
 * @package WooCommerce\LazyPaypal\ApiClient\Authentication
 */

declare(strict_types=1);

namespace WooCommerce\LazyPaypal\ApiClient\Authentication;

use WooCommerce\LazyPaypal\ApiClient\Entity\Token;

/**
 * Interface Bearer
 */
interface Bearer {

	/**
	 * Returns the bearer.
	 *
	 * @return Token
	 */
	public function bearer(): Token;
}
