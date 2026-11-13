<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Exception;

/**
 * Thrown by the service layer when user-supplied contact data fails validation.
 * The controller maps this to HTTP 422 with a JSON body containing `field` and
 * `message` so the frontend can show inline error messages on the correct field.
 */
class ValidationException extends \RuntimeException {

	public function __construct(
		private readonly string $field,
		string $message,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}

	/** The form field name that failed validation (e.g. "email", "name"). */
	public function getField(): string {
		return $this->field;
	}
}
