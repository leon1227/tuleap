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
 *
 */

namespace Tuleap\CreateTestEnv\REST;

use Tuleap\REST\JsonCast;

class TestEnvironmentRepresentation
{
    /**
     * @var int Project id
     */
    public $id;

    /**
     * @var string Project shortname
     */
    public $project_shortname;

    /**
     * @var string Project realname
     */
    public $project_realname;

    /**
     * @var string URL of project
     */
    public $project_url;

    /**
     * @var string Created user login
     */
    public $user_login;

    /**
     * @var string Created user password
     */
    public $user_password;

    public function build(\Project $project, $base_url, \PFUser $user)
    {
        $this->id                = JsonCast::toInt($project->getID());
        $this->project_shortname = $project->getUnixNameMixedCase();
        $this->project_realname  = $project->getUnconvertedPublicName();
        $this->project_url       = $base_url.'/projects/'.$project->getUnixNameLowerCase();
        $this->user_login        = $user->getUserName();
        $this->user_password     = $user->getPassword();
        return $this;
    }
}
