<?php

declare(strict_types=1);

namespace OCA\ReidelCrm\Middleware;

use OCA\ReidelCrm\AppInfo\Application;
use OCA\ReidelCrm\Controller\IntegrationsController;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Middleware;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;

class UnauthorizedException extends \RuntimeException {}

/**
 * Validates a bearer token on /api/integrations/* routes. Token is stored
 * encrypted in app_config and rotated via the admin settings page.
 */
class WindmillAuthMiddleware extends Middleware {

	public function __construct(
		private IRequest $request,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
	) {
	}

	public function beforeController(Controller $controller, string $methodName): void {
		if (!$controller instanceof IntegrationsController) {
			return;
		}

		$header = $this->request->getHeader('Authorization');
		$presented = '';
		if (stripos($header, 'Bearer ') === 0) {
			$presented = trim(substr($header, 7));
		}

		$encrypted = $this->appConfig->getValueString(Application::APP_ID, 'windmill_token', '');
		if ($encrypted === '' || $presented === '') {
			throw new UnauthorizedException();
		}
		try {
			$expected = $this->crypto->decrypt($encrypted);
		} catch (\Throwable) {
			throw new UnauthorizedException();
		}
		if (!hash_equals($expected, $presented)) {
			throw new UnauthorizedException();
		}

		// Expiry check: if an expiry timestamp has been configured and the
		// current time is past it, the token is treated as revoked.
		// expiresAt = 0 means "never expires" (default for existing tokens).
		$expiresAt = (int)$this->appConfig->getValueString(Application::APP_ID, 'windmill_token_expires_at', '0');
		if ($expiresAt > 0 && time() > $expiresAt) {
			throw new UnauthorizedException();
		}
	}

	public function afterException(Controller $controller, string $methodName, \Exception $exception): JSONResponse {
		if ($exception instanceof UnauthorizedException) {
			return new JSONResponse(['error' => 'unauthorized'], 401);
		}
		throw $exception;
	}
}
