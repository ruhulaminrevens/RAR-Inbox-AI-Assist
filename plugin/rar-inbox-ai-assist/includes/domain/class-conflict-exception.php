<?php
/**
 * Raised inside a transaction when an optimistic-concurrency check fails.
 *
 * @package RarIaa
 */

namespace RarIaa\Domain;

defined( 'ABSPATH' ) || exit;

class ConflictException extends \RuntimeException {
}
