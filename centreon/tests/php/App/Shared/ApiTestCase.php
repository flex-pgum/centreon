<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\App\Shared;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as SymfonyApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class ApiTestCase extends SymfonyApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private Client $client;

    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        foreach (static::apiUsers() as $apiUser) {
            if (is_string($apiUser)) {
                $apiUser = ['identifier' => $apiUser, 'admin' => false];
            }

            self::createApiUser($connection, $apiUser['identifier'], $apiUser['admin'] ?? false);
        }

        StaticDriver::commit();
    }

    public static function tearDownAfterClass(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');

        foreach (static::apiUsers() as $apiUser) {
            if (is_string($apiUser)) {
                $apiUser = ['identifier' => $apiUser];
            }

            self::deleteApiUser($connection, $apiUser['identifier']);
        }

        StaticDriver::commit();

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->token = null;
    }

    /**
     * @param array{headers?: array<string, mixed>, ...<string, mixed>} $options
     */
    final public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->token) {
            $options['headers']['X-AUTH-TOKEN'] = $this->token;
        }

        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        return $this->client->request($method, $url, $options);
    }

    /**
     * Define API users to create for the current test class.
     *
     * @return list<array{identifier: string, admin?: bool}|string>
     */
    protected static function apiUsers(): array
    {
        return [];
    }

    final protected function login(string $login = 'admin'): void
    {
        $this->request('POST', '/api/latest/login', [
            'json' => [
                'security' => [
                    'credentials' => [
                        'login' => $login,
                        'password' => 'Centreon!2021',
                    ],
                ],
            ],
        ]);

        $response = $this->client->getResponse();

        /** @var array{security: array{token: string}}|null $content */
        $content = $response?->toArray();

        $this->token = $content['security']['token'] ?? null;
        if (! $this->token) {
            throw new \RuntimeException('Cannot find authentication token');
        }
    }

    final protected function logout(): void
    {
        $this->token = null;
    }

    private static function createApiUser(Connection $connection, string $identifier, bool $admin = false): void
    {
        $connection->insert('contact', [
            'contact_name' => $identifier,
            'contact_alias' => $identifier,
            'contact_admin' => $admin ? '1' : '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $identifier . '@email.com',
        ]);

        $connection->insert('contact_password', [
            'contact_id' => (int) $connection->lastInsertId(),
            'password' => password_hash('Centreon!2021', PASSWORD_BCRYPT),
            'creation_date' => (new \DateTimeImmutable())->getTimestamp(),
        ]);
    }

    private static function deleteApiUser(Connection $connection, string $identifier): void
    {
        $connection->executeStatement('DELETE p FROM contact_password AS p INNER JOIN contact c ON p.contact_id = c.contact_id WHERE c.contact_alias = :identifier', [
            'identifier' => $identifier,
        ]);
        $connection->executeStatement('DELETE FROM contact c WHERE contact_alias = :identifier', [
            'identifier' => $identifier,
        ]);
    }
}
