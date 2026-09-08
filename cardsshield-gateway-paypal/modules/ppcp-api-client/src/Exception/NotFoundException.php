<?php
/**
 * The modules Not Found exception.
 *
 * @package WooCommerce\LazyPaypal\ApiClient\Exception
 */

declare(strict_types=1);

namespace WooCommerce\LazyPaypal\ApiClient\Exception;

use Psr\Container\NotFoundExceptionInterface;
use Exception;

/**
 * Class NotFoundException
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface {


}
