<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 *
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */


/**
* System Event classes
*
*/
class SystemEvent_SYSTEM_CHECK extends SystemEvent {
    
    /** 
     * Process stored event
     */
    function process() {
        
        $backendSystem      = BackendSystem::instance();
        $backendAliases     = BackendAliases::instance();
        $backendSVN         = BackendSVN::instance();
        $backendCVS         = BackendCVS::instance();
        $backendMailingList = BackendMailingList::instance();
        
        //TODO: 
        // User: unix_status vs status??
        // Private project: if codeaxadm is not member of the project: check access to SVN (incl. ViewVC), CVS, Web...
        // CVS Watch?
        // TODO: log event in syslog?
        // TODO: check that there is no pending event??? What about lower priority events??
        
        // remove deleted releases and released files
        $backendSystem->cleanupFRS();
        
        // dump SSH authorized_keys into all users homedirs
        $backendSystem->dumpSSHKeys();
        
        // Force global updates: aliases, CVS roots, SVN roots
        $backendCVS->setCVSRootListNeedUpdate();
        $backendSVN->setSVNApacheConfNeedUpdate();
        $backendAliases->setNeedUpdateMailAliases();
        $backendSystem->setNeedRefreshUserCache();
        $backendSystem->setNeedRefreshGroupCache();

        // Verivy setuid bit on some critical files
        $backendSystem->checkSetUIDbit();

        // Remove temporary files generated by aborted CVS commits
        $backendCVS->cleanup();

        // Check mailing lists
        // (re-)create missing ML
        $mailinglistdao = new MailingListDao(CodendiDataAccess::instance());
        $dar = $mailinglistdao->searchAllActiveML();
        foreach($dar as $row) {
            $list = new MailingList($row);
            if (!$backendMailingList->listExists($list)) {
                $backendMailingList->createList($list->getId());
            }
            // TODO what about lists that changed their setting (description, public/private) ?
        }
        
        // Check users
        // (re-)create missing home directories
        $userdao = new UserDao(CodendiDataAccess::instance());
        $allowed_statuses=array(User::STATUS_ACTIVE, User::STATUS_RESTRICTED);
        $dar = $userdao->searchByStatus($allowed_statuses);
        foreach($dar as $row) {
            if (! $backendSystem->userHomeExists($row['user_name'])) {
                $backendSystem->createUserHome($row['user_id']);
            }
        }
        
        $project_manager = ProjectManager::instance();
        foreach($project_manager->getProjectsByStatus(Project::STATUS_ACTIVE) as $project) {
            
            // Recreate project directories if they were deleted
            if (!$backendSystem->createProjectHome($project->getId())) {
                $this->error("Could not create project home");
                return false;
            }
            
            if ($project->usesCVS()) {
                if (!$backendCVS->repositoryExists($project)) {
                    if (!$backendCVS->createProjectCVS($project->getId())) {
                        $this->error("Could not create/initialize project CVS repository");
                        return false;
                    }
                    $backendCVS->setCVSPrivacy($project, !$project->isPublic() || $project->isCVSPrivate());
                }
                $backendCVS->createLockDirIfMissing($project);
                // check post-commit hooks
                if (!$backendCVS->updatePostCommit($project)) {
                    return false;
                }
                $backendCVS->updateCVSwriters($project->getID());
                
                // Check access rights
                if (!$backendCVS->isCVSPrivacyOK($project)) {
                    $backendCVS->setCVSPrivacy($project, !$project->isPublic() || $project->isCVSPrivate());
                }
            }
            
            if ($project->usesSVN()) {
                if (!$backendSVN->repositoryExists($project)) {
                    if (!$backendSVN->createProjectSVN($project->getId())) {
                        $this->error("Could not create/initialize project SVN repository");
                        return false;
                    }
                    $backendSVN->updateSVNAccess($project->getId());
                    $backendSVN->setSVNPrivacy($project, !$project->isPublic() || $project->isSVNPrivate());
                }
                $backendSVN->updateHooks($project);

                // Check ownership/mode/access rights
                $backendSVN->checkSVNMode($project);
            }
        }
        $this->done();
        return true;
    }

}

?>
