<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeTest extends WebTestCase
{
    public function testApiDocUrlIsSuccessful(): void
    {
        $client = self::createClient();
        $client->followRedirects(false);

        $client->request('GET', '/api/doc');
        self::assertResponseIsSuccessful(); // 2xx
    }

    public function testAccountMeIsSecureWhenNotAuthenticated(): void
    {
        $client = self::createClient();
        $client->followRedirects(false);

        $client->request('GET', '/api/account/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testRestaurantCreateIsSecureWhenNotAuthenticated(): void
    {
        $client = self::createClient();
        $client->followRedirects(false);

        $client->request(
            'POST',
            '/api/restaurant',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'X', 'description' => 'Y', 'maxGuest' => 10], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testRestaurantShowRequiresAuth(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/restaurant/999999');
        self::assertResponseStatusCodeSame(401);
    }

    public function testRegistrationIsPublic(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/registration');
        // La route est POST-only, donc GET devrait renvoyer 405 (ou 404 selon ta config)
        self::assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    public function testLoginIsPublic(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/login');
        self::assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    public function testUnknownApiRouteIs404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/does-not-exist');
        self::assertResponseStatusCodeSame(404);
    }
    
}
