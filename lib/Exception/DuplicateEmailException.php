<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Exception;

/**
 * Thrown when a contact create or update would produce a duplicate email address.
 * Extends ValidationException so the controller can handle both in one catch block.
 */
class DuplicateEmailException extends ValidationException {

	public function __construct(string $email) {
		parent::__construct(
			'email',
			sprintf('A contact with email "%s" already exists. Each email must be unique.', $email),
		);
	}
}
