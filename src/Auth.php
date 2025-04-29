<?php

namespace Storl\WpApiAuth;

use Jose\Component\Core\JWKSet;
use Jose\Component\Checker\InvalidClaimException;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Algorithm\RS256;

use Jose\Component\Checker;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Storl\WpApiAuth\Repository\UserMappingRepository;

class Auth
{
	const OPTION_JWKS_URL = 'jwks_url';
	const OPTION_APP_ID = 'app_id';
	const TRANSIENT_KEY_JWKS = 'storl_auth_jwks';

	private array $options;

	private $error = null;

	/**
	 * @var UserMappingRepository
	 */
	private $user_mapping_repository;

	public function __construct(UserMappingRepository $user_mapping_repository, array $options)
	{
		$this->options = $options;
		$this->user_mapping_repository = $user_mapping_repository;
		add_filter('determine_current_user', [$this, 'authenticate'], 20, 1);
		add_filter('rest_authentication_errors', [$this, 'check_auth_error'], 10, 1);
	}

	/**
	 * @param int|false $user_id Current user_id from filter
	 *
	 * If invalid credentials got sent, an exception is stored in $this->error
	 * and passed via another filter 'rest_authentication_errors' to wordpress
	 *
	 * @return int|false Id of the authenticated user or false if no authentication was provided
	 */
	public function authenticate($user_id): int
	{
		// don't override other auth mechanisms
		if ($user_id !== false || !$this->is_rest_request()) {
			// fix buddyboss app login bug
			if ($user_id !== null) {
				return $user_id;
			}
		}

		// check if request provides bearer auth
		$token = $this->get_bearer_token();
		if (!$token) {
			return false;
		}

		try {
			$claims = $this->validate_access_token($token);
		} catch (InvalidClaimException $e) {
			if ($e->getClaim() === 'exp') {
				$this->error = new \WP_Error(
					'rest_invalid_request',
					$e->getMessage(),
					['status' => 401]
				);
			} else {
				$this->error = new \WP_Error(
					'rest_invalid_request',
					$e->getMessage(),
					['status' => 400]
				);
			}
			return false;
		} catch (\RuntimeException $e) {
			$this->error = new \WP_Error(
				'rest_internal_server_error',
				$e->getMessage(),
				['status' => 500]
			);
			return false;
		} catch (\Throwable $e) {
			$this->error = new \WP_Error(
				'rest_invalid_request',
				$e->getMessage(),
				['status' => 400]
			);
			return false;
		}

		$mapping = $this->user_mapping_repository->find_one([
			'filter' => [
				'external_user_id' => $claims['sub'],
			],
		]);

		if (!$mapping) {
			$this->error = new \WP_Error(
				'rest_invalid_request',
				'user not registered in wordpress',
				['status' => 401]
			);
			return false;
		}

		return $mapping->get_user_id();
	}

	/**
	 * @param $error \WP_Error|null
	 */
	public function check_auth_error($error)
	{
		// Pass through other errors.
		if (!empty($error)) {
			return $error;
		}

		return $this->error;
	}

	private function get_bearer_token(): string
	{
		$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
		if ($auth_header === null || 0 !== strncasecmp('Bearer ', $auth_header, 7)) {
			return false;
		}

		return substr($auth_header, 7);
	}

	private function is_rest_request()
	{
		return 0 === strncasecmp('/wp-json', $_SERVER['REQUEST_URI'], 8);
	}

	private function load_keyset(): array
	{
		$client = new \WP_Http();
		$res = $client->request($this->options[self::OPTION_JWKS_URL]);
		if ($res instanceof \WP_Error) {
			throw new \RuntimeException('could not get jwks from auth server');
		}
		$data = json_decode($res['body'], true);
		set_transient(self::TRANSIENT_KEY_JWKS, $data, 60 * 60 * 24);
		return $data;
	}

	private function get_keyset(): ?JWKSet
	{
		$jwks = get_transient(self::TRANSIENT_KEY_JWKS);

		if (!$jwks) {
			$jwks = $this->load_keyset();
		}

		return JWKSet::createFromKeyData($jwks);
	}

	public function validate_access_token(string $token)
	{
		$header_checker_manager = new HeaderCheckerManager(
			[
				new AlgorithmChecker(['RS256']), // check the header "alg" (algorithm)
			],
			[
				new JWSTokenSupport(),
			]
		);

		$algo_manager = new AlgorithmManager([
			new RS256(),
		]);

		$jws_verifier = new JWSVerifier(
			$algo_manager
		);

		$serializer_manager = new JWSSerializerManager([
			new CompactSerializer(),
		]);

		$jwsLoader = new JWSLoader(
			$serializer_manager,
			$jws_verifier,
			$header_checker_manager
		);

		$signature = 0;

		$jws = $jwsLoader->loadAndVerifyWithKeySet($token, $this->get_keyset(), $signature);

		$claimCheckerManager = new ClaimCheckerManager(
			[
				new Checker\ExpirationTimeChecker(),
				// new Checker\AudienceChecker($this->options[self::OPTION_APP_ID]),
			]
		);

		$claims = json_decode($jws->getPayload(), true);
		$claimCheckerManager->check($claims, ['sub', 'realm_access']);

		return $claims;
	}
}
