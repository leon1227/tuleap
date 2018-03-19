<?php
/**
 * Copyright (c) Enalean, 2018. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tuleap\DynamicCredentials\User;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Tuleap\DynamicCredentials\Credential\Credential;
use Tuleap\DynamicCredentials\Credential\CredentialNotFoundException;
use Tuleap\DynamicCredentials\Session\DynamicCredentialSession;
use Tuleap\DynamicCredentials\Session\DynamicCredentialSessionNotInitializedException;

require_once __DIR__ . '/../bootstrap.php';

class DynamicUserCreatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp()
    {
        parent::setUp();
        $language = \Mockery::mock(\BaseLanguage::class);
        $GLOBALS['Language'] = $language;
    }

    protected function tearDown()
    {
        unset($GLOBALS['Language']);
        parent::tearDown();
    }

    public function testDynamicUserIsRetrievedLoggedInWhenSessionIsInitialized()
    {
        $dynamic_credential_session = \Mockery::mock(DynamicCredentialSession::class);
        $credential                 = \Mockery::mock(Credential::class);
        $credential->shouldReceive('hasExpired')->andReturn(false);
        $dynamic_credential_session->shouldReceive('getAssociatedCredential')->andReturn($credential);
        $user_manager = \Mockery::mock(\UserManager::class);
        $GLOBALS['Language']->shouldReceive('getLanguageFromAcceptLanguage');

        $dynamic_user_creator = new DynamicUserCreator($dynamic_credential_session, $user_manager);

        $user = $dynamic_user_creator->getDynamicUser([]);
        $this->assertTrue($user->isLoggedIn());
    }

    public function testDynamicUserIsRetrievedLoggedOutWhenSessionIsNotInitialized()
    {
        $dynamic_credential_session = \Mockery::mock(DynamicCredentialSession::class);
        $dynamic_credential_session->shouldReceive('getAssociatedCredential')->andThrow(DynamicCredentialSessionNotInitializedException::class);
        $user_manager = \Mockery::mock(\UserManager::class);
        $GLOBALS['Language']->shouldReceive('getLanguageFromAcceptLanguage');

        $dynamic_user_creator = new DynamicUserCreator($dynamic_credential_session, $user_manager);

        $user = $dynamic_user_creator->getDynamicUser([]);
        $this->assertFalse($user->isLoggedIn());
    }

    /**
     * @runInSeparateProcess
     */
    public function testCurrentUserIsLogoutWhenCredentialIsExpired()
    {
        $dynamic_credential_session = \Mockery::mock(DynamicCredentialSession::class);
        $credential                 = \Mockery::mock(Credential::class);
        $credential->shouldReceive('hasExpired')->andReturn(true);
        $dynamic_credential_session->shouldReceive('getAssociatedCredential')->andReturn($credential);
        $user_manager = \Mockery::mock(\UserManager::class);
        $user_manager->shouldReceive('logout');

        $dynamic_user_creator = new DynamicUserCreator($dynamic_credential_session, $user_manager);

        $dynamic_user_creator->getDynamicUser([]);
    }

    /**
     * @runInSeparateProcess
     */
    public function testCurrentUserIsLogoutWhenCredentialIsNotFound()
    {
        $dynamic_credential_session = \Mockery::mock(DynamicCredentialSession::class);
        $dynamic_credential_session->shouldReceive('getAssociatedCredential')->andThrow(CredentialNotFoundException::class);
        $user_manager = \Mockery::mock(\UserManager::class);
        $user_manager->shouldReceive('logout');

        $dynamic_user_creator = new DynamicUserCreator($dynamic_credential_session, $user_manager);

        $dynamic_user_creator->getDynamicUser([]);
    }
}
