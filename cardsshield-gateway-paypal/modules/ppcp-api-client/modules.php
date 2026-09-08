<?php
/**
 * The api client module.
 *
 * @package WooCommerce\LazyPaypal\ApiClient
 */

declare(strict_types=1);

namespace WooCommerce\LazyPaypal\ApiClient;

use Dhii\Modular\Module\ModuleInterface;

return function (): ModuleInterface {
	return new ApiModule();
};
