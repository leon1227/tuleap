<?php
/**
 * Copyright (c) Enalean, 2013 – 2015. All Rights Reserved.
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

class AgileDashboard_Milestone_MilestoneDao extends DataAccessObject {

    public function searchPaginatedSubMilestones(
        $milestone_artifact_id,
        Tuleap\AgileDashboard\Milestone\Criterion\ISearchOnStatus $criterion,
        $limit,
        $offset,
        $order
    ) {
        $milestone_artifact_id = $this->da->escapeInt($milestone_artifact_id);

        $limit_statement = '';
        if ($limit > 0) {
            $limit  = $this->da->escapeInt($limit);
            $offset = $this->da->escapeInt($offset);

            $limit_statement = "LIMIT $offset, $limit";
        }

        if (strtolower($order) !== 'asc') {
            $order = 'desc';
        }

        list($from_status_statement, $where_status_statement) = $this->getStatusStatements($criterion);

        $nature = $this->da->quoteSmart(Tracker_FormElement_Field_ArtifactLink::NATURE_IS_CHILD);

        $sql = "SELECT SQL_CALC_FOUND_ROWS submilestones.*
                FROM tracker_artifact AS parent
                    INNER JOIN tracker_field AS f ON (
                        parent.tracker_id = f.tracker_id
                        AND parent.id = $milestone_artifact_id
                        AND f.formElement_type = 'art_link'
                    )
                    INNER JOIN tracker_changeset_value AS cv ON (
                        cv.field_id = f.id
                        AND cv.changeset_id = parent.last_changeset_id
                    )
                    INNER JOIN tracker_changeset_value_artifactlink AS cva ON (
                        cva.changeset_value_id = cv.id
                        AND cva.nature = $nature
                    )
                    INNER JOIN tracker_artifact AS submilestones ON (
                        submilestones.id = cva.artifact_id
                    )
                    INNER JOIN plugin_agiledashboard_planning AS planning ON (
                        planning.planning_tracker_id = submilestones.tracker_id
                    )
                    $from_status_statement

                WHERE 1
                  AND $where_status_statement

                ORDER BY submilestones.id $order
                $limit_statement";

        return $this->retrieve($sql);
    }

    public function searchPaginatedTopMilestones(
        $milestone_tracker_id,
        Tuleap\AgileDashboard\Milestone\Criterion\ISearchOnStatus $criterion,
        $limit,
        $offset,
        $order
    ) {
        $milestone_tracker_id = $this->da->escapeInt($milestone_tracker_id);

        $limit_statement = '';
        if ($limit > 0) {
            $limit  = $this->da->escapeInt($limit);
            $offset = $this->da->escapeInt($offset);

            $limit_statement = "LIMIT $offset, $limit";
        }

        if (strtolower($order) !== 'asc') {
            $order = 'desc';
        }

        list($from_status_statement, $where_status_statement) = $this->getStatusStatements($criterion);

        $sql = "SELECT SQL_CALC_FOUND_ROWS submilestones.*
                FROM tracker_artifact AS submilestones
                    $from_status_statement

                WHERE submilestones.tracker_id = $milestone_tracker_id
                  AND $where_status_statement

                ORDER BY submilestones.id $order
                $limit_statement";

        return $this->retrieve($sql);
    }

    private function getStatusStatements(Tuleap\AgileDashboard\Milestone\Criterion\ISearchOnStatus $criterion) {
        $from_status_statement  = "";
        $where_status_statement = "1";
        if ($criterion->shouldRetrieveOpenMilestones() && $criterion->shouldRetrieveClosedMilestones()) {
            // search all milestones.
            // no need to filter
        } else {
            if ($criterion->shouldRetrieveOpenMilestones()) {
                $from_status_statement = "
                    INNER JOIN tracker_changeset AS C ON (submilestones.last_changeset_id = C.id)
                    LEFT JOIN (
                        tracker_semantic_status as SS
                        INNER JOIN tracker_changeset_value AS CV3 ON (SS.field_id = CV3.field_id)
                        INNER JOIN tracker_changeset_value_list AS CVL2 ON (CV3.id = CVL2.changeset_value_id)
                    ) ON (submilestones.tracker_id = SS.tracker_id AND C.id = CV3.changeset_id)";
                $where_status_statement = "(
                        SS.field_id IS NULL -- Use the status semantic only if it is defined
                        OR
                        CVL2.bindvalue_id = SS.open_value_id
                     )";
            }
            if ($criterion->shouldRetrieveClosedMilestones()) {
                $from_status_statement = "
                    INNER JOIN tracker_changeset_value AS cvs ON(
                        submilestones.last_changeset_id = cvs.changeset_id
                    )
                    INNER JOIN (
                        SELECT DISTINCT tracker_id, field_id
                        FROM tracker_semantic_status
                    ) AS R ON (
                        R.tracker_id   = submilestones.tracker_id
                        AND R.field_id = cvs.field_id
                    )
                    INNER JOIN tracker_changeset_value_list AS cvl ON(cvl.changeset_value_id = cvs.id)
                    LEFT JOIN tracker_semantic_status AS open_values ON (
                        cvl.bindvalue_id = open_values.open_value_id
                        AND open_values.tracker_id = submilestones.tracker_id
                        AND cvs.field_id = open_values.field_id
                    )";
                $where_status_statement = "open_values.open_value_id IS NULL";
            }
        }

        return array($from_status_statement, $where_status_statement);
    }

    public function searchSubMilestones($milestone_artifact_id) {
        $limit     = null;
        $offset    = null;
        $order     = 'asc';
        $criterion = new Tuleap\AgileDashboard\Milestone\Criterion\StatusAll();

        return $this->searchPaginatedSubMilestones($milestone_artifact_id, $criterion, $limit, $offset, $order);
    }

    public function getAllMilestoneByTrackers(array $list_of_trackers_ids) {
        $select_fragments = $this->getSelectFragments($list_of_trackers_ids);
        $from_fragments   = $this->getFromFragments($list_of_trackers_ids);
        $order_fragments  = $this->getOrderFragments($list_of_trackers_ids);

        $sql = "SELECT $select_fragments
                FROM $from_fragments
                ORDER BY $order_fragments";

        return $this->retrieve($sql);
    }

    private function getOrderFragments(array $list_of_trackers_ids) {
        return 'm'. implode('.id, m', $list_of_trackers_ids) .'.id';
    }

    private function getSelectFragments(array $list_of_trackers_ids) {
        return implode(', ', array_map(array($this, 'extractSelectFragments'), $list_of_trackers_ids));
    }

    private function extractSelectFragments($tracker_id) {
        return "m{$tracker_id}.id as m{$tracker_id}_id, m{$tracker_id}_CVT.value AS m{$tracker_id}_title";
    }

    private function getFromFragments(array $list_of_trackers_ids) {
        $trackers_ids = $list_of_trackers_ids;
        $first_tracker_id = array_shift($trackers_ids);
        return "tracker_artifact AS m{$first_tracker_id}
                {$this->getTrackerFromFragment($first_tracker_id)}
                {$this->getTitleFromFragment($first_tracker_id)}
                {$this->joinRecursively($first_tracker_id, $trackers_ids)}";
    }

    private function joinRecursively($parent_tracker_id, array $trackers_ids) {
        $child_tracker_id = array_shift($trackers_ids);
        if (! $child_tracker_id) {
            return '';
        }

        return "LEFT JOIN (
            tracker_changeset_value AS m{$parent_tracker_id}_CV2
            INNER JOIN tracker_changeset_value_artifactlink AS m{$parent_tracker_id}_AL ON ( m{$parent_tracker_id}_CV2.id = m{$parent_tracker_id}_AL.changeset_value_id )
            INNER JOIN tracker_artifact AS m{$child_tracker_id} ON (m{$parent_tracker_id}_AL.artifact_id = m{$child_tracker_id}.id)
            {$this->getTrackerFromFragment($child_tracker_id)}
            {$this->joinRecursively($child_tracker_id, $trackers_ids)}
            {$this->getTitleFromFragment($child_tracker_id)}
        ) ON (m{$parent_tracker_id}.last_changeset_id = m{$parent_tracker_id}_CV2.changeset_id)";
    }

    private function getTitleFromFragment($tracker_id) {
        return "LEFT JOIN (
            tracker_changeset_value AS m{$tracker_id}_CV
            INNER JOIN tracker_semantic_title AS m{$tracker_id}_ST ON ( m{$tracker_id}_CV.field_id = m{$tracker_id}_ST.field_id )
            INNER JOIN tracker_changeset_value_text AS m{$tracker_id}_CVT ON ( m{$tracker_id}_CV.id = m{$tracker_id}_CVT.changeset_value_id )
        ) ON (m{$tracker_id}.last_changeset_id = m{$tracker_id}_CV.changeset_id)";
    }

    private function getTrackerFromFragment($tracker_id) {
        return "INNER JOIN tracker AS mt{$tracker_id} ON (mt{$tracker_id}.id = m{$tracker_id}.tracker_id AND m{$tracker_id}.tracker_id = {$tracker_id})";
    }
}
