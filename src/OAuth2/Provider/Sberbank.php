<?php

declare(strict_types=1);

namespace SocialConnect\OAuth2\Provider;

use Psr\Http\Message\RequestInterface;
use SocialConnect\Common\ArrayHydrator;
use SocialConnect\Provider\AccessTokenInterface;
use SocialConnect\Common\Entity\User;

/**
 * Class Sberbank
 * @author vitr tretyak
 * @package SocialConnect\OAuth2\Provider
 */
class Sberbank extends \SocialConnect\OAuth2\AbstractProvider {
    const NAME = 'sber';

    /**
     * {@inheritdoc}
     */
    protected $requestHttpMethod = 'POST';

    /**
     * @var array
     */
    public $additionalHeaders;

    public function getBaseUri()
    {
        return 'https://dev.api.sberbank.ru';
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthorizeUri()
    {
        return 'https://online.sberbank.ru/CSAFront/oidc/sberbank_id/authorize.do';
    }

    /**
     * {@inheritDoc}
     */
    public function getRequestTokenUri()
    {
        return 'https://dev.api.sberbank.ru/ru/prod/tokens/v2/oidc';
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function prepareRequest(string $method, string $uri, array &$headers, array &$query, AccessTokenInterface $accessToken = null): void
    {
        if ($accessToken) {
            $query['access_token'] = $accessToken->getToken();
        }

        if (!empty($this->additionalHeaders)) {
            $headers = array_merge($headers, $this->additionalHeaders);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function makeAccessTokenRequest(string $code): RequestInterface
    {
        $parameters = [
            'client_id' => $this->consumer->getKey(),
            'client_secret' => $this->consumer->getSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getRedirectUrl(),
//            'scope' => 'openid, email, name',
            'scope' => 'name',
        ];

        return $this->httpStack->createRequest($this->requestHttpMethod, $this->getRequestTokenUri())
            ->withHeader('accept', 'application/json')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('x-ibm-client-id', $this->consumer->getKey())
            ->withHeader('rquid', $this->getRquid())
            ->withBody($this->httpStack->createStream(http_build_query($parameters, '', '&')));
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentity(AccessTokenInterface $accessToken)
    {
        $query = [];

        if ($fields = $this->getArrayOption('identity.fields', [])) {
            $query['fields'] = implode(',', $fields);
        }

        $this->additionalHeaders = [
            'accept' => 'application/json',
            'x-ibm-client-id' => $this->consumer->getKey(),
            'x-introspect-rquid' => $this->getRquid(),
//            'authorization' => 'Bearer ' . $accessToken->getToken(),
        ];


//        $curl = curl_init();
//
//        curl_setopt_array($curl, array(
//            CURLOPT_URL => 'https://dev.api.sberbank.ru/ru/prod/sberbankid/v2.1/userinfo',
//            CURLOPT_RETURNTRANSFER => true,
//            CURLOPT_ENCODING => "",
//            CURLOPT_MAXREDIRS => 10,
//            CURLOPT_TIMEOUT => 30,
//            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//            CURLOPT_CUSTOMREQUEST => "GET",
//            CURLOPT_HTTPHEADER => array(
//                "accept: application/json",
//                "authorization: Bearer ". $accessToken->getToken(),
//                "x-ibm-client-id: ".$this->consumer->getKey(),
//                "x-introspect-rquid: ". $this->getRquid()
//            ),
//        ));
//
//        $response = curl_exec($curl);
//        $err = curl_error($curl);
//
//        curl_close($curl);

        $response = $this->request('GET', '/ru/prod/sberbankid/v2.1/userinfo', [], $accessToken);

        $hydrator = new ArrayHydrator([
            'id' => 'id',
            'first_name' => 'firstname',
            'last_name' => 'lastname',
            'email' => 'email',
            'bdate' => static function ($value, User $user) {
                $user->setBirthday(
                    new \DateTime($value)
                );
            },
            'sex' => static function ($value, User $user) {
                $user->setSex($value === 1 ? User::SEX_FEMALE : User::SEX_MALE);
            },
            'screen_name' => 'username',
            'photo_max_orig' => 'pictureURL',
        ]);

        /** @var User $user */
        $user = $hydrator->hydrate(new User(), $response['response'][0]);

        $user->email = $this->email;
        $user->emailVerified = true;

        return $user;
    }

    /**
     * @param string|null $str
     *
     * @return string
     * @throws \Exception
     */
    public function getRquid(string $str = null): string
    {
        return md5($str ? $str : ($this->consumer->getKey() . microtime() . random_bytes(10)));
    }
}
