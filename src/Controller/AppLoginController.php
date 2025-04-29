<?php

namespace Storl\WpApiAuth\Controller;

use Jose\Component\Checker\InvalidClaimException;
use Storl\WpApiAuth\Auth;
use Storl\WpApiAuth\Repository\UserMappingRepository;

class AppLoginController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly UserMappingRepository $user_mapping_repository,
    ) {
        add_action('rest_api_init', function () {
            register_rest_route(
                'buddyboss-app/auth/v2',
                '/jwt/login/keycloak',
                array(
                    'methods'             => 'POST',
                    'callback'            => [$this, 'login_keycloak'],
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'access_token'     => array(
                            'type'              => 'string',
                            'required'          => true,
                            'description'       => __('Access token obtained from keycloak after successfull login', 'buddyboss-app'),
                        ),
                        'device_token' => array(
                            'type'        => 'string',
                            'required'    => false,
                            'description' => __('Firebase app device token.', 'buddyboss-app'),
                        ),
                    ),
                ),
                true, // override
            );
        });
    }

    public function login_keycloak(\WP_REST_Request $request)
    {
        $access_token = $request->get_param('access_token');
        $device_token = $request->get_param('device_token');

        try {
            $claims = $this->auth->validate_access_token($access_token);
        } catch (InvalidClaimException $e) {
            if ($e->getClaim() === 'exp') {
                return new \WP_Error(
                    'rest_invalid_request',
                    $e->getMessage(),
                    ['status' => 401]
                );
            } else {
                return new \WP_Error(
                    'rest_invalid_request',
                    $e->getMessage(),
                    ['status' => 400]
                );
            }
            return false;
        } catch (\RuntimeException $e) {
            return new \WP_Error(
                'rest_internal_server_error',
                $e->getMessage(),
                ['status' => 500]
            );
            return false;
        } catch (\Throwable $e) {
            return new \WP_Error(
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
            return new \WP_Error(
                'rest_invalid_request',
                'user not registered in wordpress',
                ['status' => 401]
            );
            return false;
        }


        // Get user details based on username.
        $user = get_user($mapping->get_user_id());

        /**
         * User detail check exits or not.
         */
        if (!$user) {
            return new \WP_Error('rest_bbapp_jwt_invalid_username', __('Unknown username. Check again or try your email address.', 'buddyboss-app'), array('status' => 500));
        }

        /**
         * Generate the token for user.
         */
        $jwt = \BuddyBossApp\Auth\Jwt::instance();

        $token_args = array(
            'expire_at_days' => 1, // allow only 1 day expire for access token. we have refresh token on service for renew.
        );
        $generate_token = $jwt->generate_user_token('', '', $token_args, true, $user);

        $restAPIv2 = \BuddyBossApp\Api\Auth\V2\RestAPI::instance();
        return $restAPIv2->generate_user_token_response($generate_token, $device_token);
    }
}
