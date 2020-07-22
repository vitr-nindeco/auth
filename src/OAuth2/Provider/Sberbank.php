<?php

declare(strict_types=1);

namespace SocialConnect\OAuth2\Provider;

use SocialConnect\Common\ArrayHydrator;
use SocialConnect\Provider\AccessTokenInterface;
use SocialConnect\Common\Entity\User;

class Sberbank extends \SocialConnect\OAuth2\AbstractProvider {
    const NAME = 'sber';

    /**
     * {@inheritdoc}
     */
    protected $requestHttpMethod = 'GET';

    /**
     * Vk returns email inside AccessToken
     *
     * @var string|null
     */
    protected $email;

    public function getBaseUri()
    {
        return '';
    }

    public function getAuthorizeUri()
    {
        return '';
    }

    public function getRequestTokenUri()
    {
        return '';
    }

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
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentity(AccessTokenInterface $accessToken)
    {
        $query = [
            'v' => '5.100'
        ];

        $fields = $this->getArrayOption('identity.fields', []);
        if ($fields) {
            $query['fields'] = implode(',', $fields);
        }

        $response = $this->request('GET', 'method/users.get', $query, $accessToken);

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
}
