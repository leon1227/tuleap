<?php
/**
 * Copyright (c) Enalean, 2012 - 2016. All Rights Reserved.
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

namespace Tuleap\Git\GitViews\RepoManagement\Pane;

use Codendi_Request;
use GitRepository;
use TemplateRendererFactory;
use Tuleap\Git\GitPresenters\RepositoryPaneNotificationPresenter;
use EventManager;
use Tuleap\Git\Notifications\CollectionOfUserToBeNotifiedPresenterBuilder;
use Tuleap\Git\Notifications\CollectionOfUgroupToBeNotifiedPresenterBuilder;

class Notification extends Pane
{

    const ID = 'mail';

    /**
     * @var CollectionOfUserToBeNotifiedPresenterBuilder
     */
    private $user_to_be_notified_builder;

    /**
     * @var CollectionOfUgroupToBeNotifiedPresenterBuilder
     */
    private $group_to_be_notified_builder;

    public function __construct(
        GitRepository $repository,
        Codendi_Request $request,
        CollectionOfUserToBeNotifiedPresenterBuilder $user_to_be_notified_builder,
        CollectionOfUgroupToBeNotifiedPresenterBuilder $group_to_be_notified_builder
    ) {
        parent::__construct($repository, $request);
        $this->user_to_be_notified_builder = $user_to_be_notified_builder;
        $this->group_to_be_notified_builder = $group_to_be_notified_builder;
    }

    /**
     * @see GitViews_RepoManagement_Pane::getIdentifier()
     */
    public function getIdentifier()
    {
        return self::ID;
    }

    /**
     * @see GitViews_RepoManagement_Pane::getTitle()
     */
    public function getTitle()
    {
        return $GLOBALS['Language']->getText('plugin_git', 'admin_mail');
    }

    /**
     * @see GitViews_RepoManagement_Pane::getContent()
     */
    public function getContent()
    {
        $users  = $this->user_to_be_notified_builder->getCollectionOfUserToBeNotifiedPresenter($this->repository);
        $groups = $this->group_to_be_notified_builder->getCollectionOfUgroupToBeNotifiedPresenter($this->repository);

        $renderer = TemplateRendererFactory::build()->getRenderer(dirname(GIT_BASE_DIR).'/templates/settings');
        $html     = $renderer->renderToString(
            'notifications',
            new RepositoryPaneNotificationPresenter(
                $this->repository,
                $this->getIdentifier(),
                $users,
                $groups
            )
        );
        $html    .= $this->getPluginNotifications();
        $GLOBALS['Response']->includeFooterJavascriptFile(GIT_BASE_URL .'/scripts/admin-notifications.js');

        return $html;
    }

    private function getPluginNotifications()
    {
        $output = '';
        EventManager::instance()->processEvent(GIT_ADDITIONAL_NOTIFICATIONS, array(
            'request'    => $this->request,
            'repository' => $this->repository,
            'output'     => &$output,
        ));

        return $output;
    }
}
