<?php

namespace Storl\WpApiAuth;

use Storl\WpApiAuth\Repository\UserMappingRepository;

class Plugin
{
	private static $instance;

	private UserMappingRepository $userMappingRepository;

	public static function instance(): self
	{
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{

		register_activation_hook(STORL_API_AUTH_PLUGIN_FILE, [$this, 'on_plugin_activation']);
		register_deactivation_hook(STORL_API_AUTH_PLUGIN_FILE, [$this, 'on_plugin_deactivation']);

		$this->userMappingRepository = new UserMappingRepository();
		new Auth($this->userMappingRepository, [
			Auth::OPTION_JWKS_URL => 'https://konto.storl.de/realms/storl/protocol/openid-connect/certs',
			Auth::OPTION_APP_ID => 'packwolf',
		]);

		add_action('init', [$this, 'on_init']);

		add_action('openid-connect-generic-user-update', [$this, 'save_subject_identity_on_update'], 10, 1);
		add_action('openid-connect-generic-user-create', [$this, 'save_subject_identity_on_create'], 10, 2);
	}

	public function on_init()
	{
	}

	public function on_plugin_activation()
	{
		require_once \STORL_API_AUTH_PLUGIN_ABSPATH . '/create_db_schema.php';
	}

	public function on_plugin_deactivation()
	{
	}

	public function save_subject_identity_on_update($user_id) {
		$subject_identity = get_user_meta($user_id, 'openid-connect-generic-subject-identity', true);
		if ($subject_identity) {
			$this->userMappingRepository->upsert([
				'user_id' => $user_id,
				'external_user_id' => $subject_identity,
			]);
		}

	}

	public function save_subject_identity_on_create(\WP_User $user, array $user_claim) {
		$subject_identity = $user_claim['sub'];
		if ($subject_identity) {
			$this->userMappingRepository->upsert([
				'user_id' => $user->ID,
				'external_user_id' => $subject_identity,
			]);
		}
	}
}
