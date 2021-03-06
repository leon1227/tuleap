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

import {
    getSortedProjectsIAmMemberOf as getProjects
} from '../rest-querier.js';

export {
    getSortedProjectsIAmMemberOf
};

let cached_projects = [];

async function getSortedProjectsIAmMemberOf() {
    if (cached_projects.length > 0) {
        return cached_projects;
    }

    const sorted_projects = await getProjects();
    cached_projects       = sorted_projects.map(({ id, label }) => {
        return { id, label };
    });

    return cached_projects;
}
