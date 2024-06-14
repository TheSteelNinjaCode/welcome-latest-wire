<?php

namespace Lib\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DateInterval;
use DateTime;
use Lib\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Auth
{
    public const PAYLOAD_NAME = 'role';
    public const ROLE_NAME = '';
    public const PAYLOAD = 'payload_2183A';
    public const COOKIE_NAME = 'pphp_aut_token_D36E5';
    private const PPHPAUTH = 'pphpauth';

    private $secretKey;
    private $defaultTokenValidity = '1h'; // Default to 1 hour

    public function __construct()
    {
        $this->secretKey = $_ENV['AUTH_SECRET'];
    }

    /**
     * Authenticates a user and generates a JWT (JSON Web Token) based on the specified user role
     * and token validity duration. The method first checks if the secret key is set, calculates
     * the token's expiration time, sets the necessary payload, and encodes it into a JWT.
     * If possible (HTTP headers not yet sent), it also sets cookies with the JWT for client-side storage.
     *
     * @param mixed $role A role identifier which can be a simple string or an instance of AuthRole.
     *                    If an instance of AuthRole is provided, its `value` property will be used as the role in the token.
     * @param string|null $tokenValidity Optional parameter specifying the duration the token is valid for (e.g., '10m', '1h').
     *                                   If null, the default validity period set in the class property is used.
     *                                   The format should be a number followed by a time unit ('s' for seconds, 'm' for minutes,
     *                                   'h' for hours, 'd' for days), and this is parsed to calculate the exact expiration time.
     *
     * @return string Returns the encoded JWT as a string.
     *
     * @throws InvalidArgumentException Thrown if the secret key is not set or if the duration format is invalid.
     *
     * Example:
     *   $auth = new Authentication();
     *   $auth->setSecretKey('your_secret_key');
     *   try {
     *       $jwt = $auth->authenticate('Admin', '1h');
     *       echo "JWT: " . $jwt;
     *   } catch (\InvalidArgumentException $e) {
     *       echo "Error: " . $e->getMessage();
     *   }
     */
    public function authenticate($role, string $tokenValidity = null): string
    {
        if (!$this->secretKey) {
            throw new \InvalidArgumentException("Secret key is required for authentication.");
        }

        $expirationTime = $this->calculateExpirationTime($tokenValidity ?? $this->defaultTokenValidity);

        if ($role instanceof AuthRole) {
            $role = $role->value;
        }

        $payload = [
            self::PAYLOAD_NAME => $role,
            'exp' => $expirationTime,
        ];

        // Set the payload in the session
        $_SESSION[self::PAYLOAD] = $payload;

        // Encode the JWT
        $jwt = JWT::encode($payload, $this->secretKey, 'HS256');

        if (!headers_sent()) {
            $this->setCookies($jwt, $expirationTime);
        }

        return $jwt;
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION[self::PAYLOAD]);
    }

    private function calculateExpirationTime(string $duration): int
    {
        $now = new DateTime();
        $interval = $this->convertDurationToInterval($duration);
        $futureDate = $now->add($interval);
        return $futureDate->getTimestamp();
    }

    private function convertDurationToInterval(string $duration): DateInterval
    {
        if (preg_match('/^(\d+)(s|m|h|d)$/', $duration, $matches)) {
            $value = (int)$matches[1];
            $unit = $matches[2];

            switch ($unit) {
                case 's':
                    return new DateInterval("PT{$value}S");
                case 'm':
                    return new DateInterval("PT{$value}M");
                case 'h':
                    return new DateInterval("PT{$value}H");
                case 'd':
                    return new DateInterval("P{$value}D");
                default:
                    throw new \InvalidArgumentException("Invalid duration format: {$duration}");
            }
        }

        throw new \InvalidArgumentException("Invalid duration format: {$duration}");
    }

    public function verifyToken(string $jwt)
    {
        try {
            return JWT::decode($jwt, new Key($this->secretKey, 'HS256'));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid token.");
        }
    }

    public function refreshToken(string $jwt, string $tokenValidity = null): string
    {
        $decodedToken = $this->verifyToken($jwt);

        if (!$decodedToken) {
            throw new \InvalidArgumentException("Invalid token.");
        }

        $expirationTime = $this->calculateExpirationTime($tokenValidity ?? $this->defaultTokenValidity);

        $decodedToken->exp = $expirationTime;
        $newJwt = JWT::encode((array)$decodedToken, $this->secretKey, 'HS256');

        if (!headers_sent()) {
            $this->setCookies($newJwt, $expirationTime);
        }

        return $newJwt;
    }

    protected function setCookies(string $jwt, int $expirationTime)
    {
        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, $jwt, [
                'expires' => $expirationTime,
                'path' => '/',
                'domain' => '', // Specify your domain
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax', // or 'Strict' depending on your requirements
            ]);
        }
    }

    public function logout(string $redirect = null)
    {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            unset($_COOKIE[self::COOKIE_NAME]);
            setcookie(self::COOKIE_NAME, '', time() - 3600, '/');
        }

        if (isset($_SESSION[self::PAYLOAD])) {
            unset($_SESSION[self::PAYLOAD]);
        }

        if ($redirect) {
            redirect($redirect);
        }
    }

    public function getPayload()
    {
        if (isset($_SESSION[self::PAYLOAD])) {
            return $_SESSION[self::PAYLOAD][self::PAYLOAD_NAME];
        }

        return null;
    }

    private function exchangeCode($data, $apiUrl)
    {
        try {
            $client = new Client();
            $response = $client->post($apiUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'form_params' => $data,
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents());
            }

            return false;
        } catch (RequestException) {
            return false;
        }
    }

    private function saveAuthInfo($responseInfo, $accountData)
    {
        // Save user data to the database
    }

    private function findProvider(array $providers, string $type): ?object
    {
        foreach ($providers as $provider) {
            if ($provider instanceof $type) {
                return $provider;
            }
        }
        return null;
    }

    public function authProviders(...$providers)
    {
        global $isGet, $dynamicRouteParams;

        if ($isGet && in_array('signin', $dynamicRouteParams[self::PPHPAUTH])) {
            foreach ($providers as $provider) {
                if ($provider instanceof GithubProvider && in_array('github', $dynamicRouteParams[self::PPHPAUTH])) {
                    $githubAuthUrl = "https://github.com/login/oauth/authorize?scope=user:email%20read:user&client_id={$provider->clientId}";
                    redirect($githubAuthUrl);
                } elseif ($provider instanceof GoogleProvider && in_array('google', $dynamicRouteParams[self::PPHPAUTH])) {
                    $googleAuthUrl = "https://accounts.google.com/o/oauth2/v2/auth?"
                        . "scope=" . urlencode('email profile') . "&"
                        . "response_type=code&"
                        . "client_id=" . urlencode($provider->clientId) . "&"
                        . "redirect_uri=" . urlencode($provider->redirectUri);
                    redirect($googleAuthUrl);
                }
            }
        }

        $authCode = Validator::validateString($_GET['code'] ?? '');

        if ($isGet && in_array('callback', $dynamicRouteParams[self::PPHPAUTH]) && isset($authCode)) {
            if (in_array('github', $dynamicRouteParams[self::PPHPAUTH])) {
                $provider = $this->findProvider($providers, GithubProvider::class);

                if (!$provider) {
                    exit("Error occurred. Please try again.");
                }

                return $this->githubProvider($provider, $authCode);
            } elseif (in_array('google', $dynamicRouteParams[self::PPHPAUTH])) {
                $provider = $this->findProvider($providers, GoogleProvider::class);

                if (!$provider) {
                    exit("Error occurred. Please try again.");
                }

                return $this->googleProvider($provider, $authCode);
            }
        }

        exit("Error occurred. Please try again.");
    }

    private function githubProvider(GithubProvider $githubProvider, string $authCode)
    {
        $gitToken = [
            'client_id' => $githubProvider->clientId,
            'client_secret' => $githubProvider->clientSecret,
            'code' => $authCode,
        ];

        $apiUrl = 'https://github.com/login/oauth/access_token';
        $tokenData = (object)$this->exchangeCode($gitToken, $apiUrl);

        if (!$tokenData) {
            exit("Error occurred. Please try again.");
        }

        if (isset($tokenData->error)) {
            exit("Error occurred. Please try again.");
        }

        if (isset($tokenData->access_token)) {
            $client = new Client();
            $emailResponse = $client->get('https://api.github.com/user/emails', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenData->access_token,
                    'Accept' => 'application/json',
                ],
            ]);

            $emails = json_decode($emailResponse->getBody()->getContents(), true);

            $primaryEmail = array_reduce($emails, function ($carry, $item) {
                return ($item['primary'] && $item['verified']) ? $item['email'] : $carry;
            }, null);

            $response = $client->get('https://api.github.com/user', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $tokenData->access_token,
                ],
            ]);

            if ($response->getStatusCode() == 200) {
                $responseInfo = json_decode($response->getBody()->getContents());

                $accountData = [
                    'provider' => 'github',
                    'type' => 'oauth',
                    'providerAccountId' => "$responseInfo->id",
                    'access_token' => $tokenData->access_token,
                    'expires_at' => $tokenData->expires_at ?? null,
                    'token_type' => $tokenData->token_type,
                    'scope' => $tokenData->scope,
                ];

                $this->saveAuthInfo($responseInfo, $accountData);

                $userToAuthenticate = [
                    'name' => $responseInfo->login,
                    'email' => $primaryEmail,
                    'image' => $responseInfo->avatar_url,
                    'Account' => (object)$accountData
                ];
                $userToAuthenticate = (object)$userToAuthenticate;

                $this->authenticate($userToAuthenticate, $githubProvider->maxAge);
            }
        }
    }

    private function googleProvider(GoogleProvider $googleProvider, string $authCode)
    {
        $googleToken = [
            'client_id' => $googleProvider->clientId,
            'client_secret' => $googleProvider->clientSecret,
            'code' => $authCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $googleProvider->redirectUri
        ];

        $apiUrl = 'https://oauth2.googleapis.com/token';
        $tokenData = (object)$this->exchangeCode($googleToken, $apiUrl);

        if (!$tokenData) {
            exit("Error occurred. Please try again.");
        }

        if (isset($tokenData->error)) {
            exit("Error occurred. Please try again.");
        }

        if (isset($tokenData->access_token)) {
            $client = new Client();
            $response = $client->get('https://www.googleapis.com/oauth2/v1/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenData->access_token,
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() == 200) {
                $responseInfo = json_decode($response->getBody()->getContents());

                $accountData = [
                    'provider' => 'google',
                    'type' => 'oauth',
                    'providerAccountId' => "$responseInfo->id",
                    'access_token' => $tokenData->access_token,
                    'expires_at' => $tokenData->expires_at ?? null,
                    'token_type' => $tokenData->token_type,
                    'scope' => $tokenData->scope,
                ];

                $this->saveAuthInfo($responseInfo, $accountData);

                $userToAuthenticate = [
                    'name' => $responseInfo->name,
                    'email' => $responseInfo->email,
                    'image' => $responseInfo->picture,
                    'Account' => (object)$accountData
                ];
                $userToAuthenticate = (object)$userToAuthenticate;

                $this->authenticate($userToAuthenticate, $googleProvider->maxAge);
            }
        }
    }
}

class GoogleProvider
{
    public function __construct(
        public string $clientId,
        public string $clientSecret,
        public string $redirectUri,
        public string $maxAge = '30d'
    ) {
    }
}

class GithubProvider
{
    public function __construct(
        public string $clientId,
        public string $clientSecret,
        public string $maxAge = '30d'
    ) {
    }
}
