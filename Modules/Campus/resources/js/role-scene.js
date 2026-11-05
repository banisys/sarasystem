import { Group, Vector3 } from 'three';
import { createStage } from '@/scene/stage.js';
import { BoxBatch, recolor, VIEWS } from './blocks.js';
import { makeBeams, POWER_COLORS, powerColor, strength } from './power.js';

/**
 * The roles as a pyramid of power (ADR 0057): a floor per level of the hierarchy,
 * the higher the stronger. A role's column grows and darkens with its permissions,
 * little people stand for its holders, and beams join a role to the nearest above.
 */

const SPACING = 5.5;
const MAX_IN_ROW = 6;
const COLUMN_WIDTH = 2.4;
const PLATE_MARGIN = 1.6;
const MIN_COLUMN = 0.35;
const MAX_COLUMN = 5.5;
const FLOOR_GAP = 3.2;
const MAX_PEOPLE = 10;

const COLORS = { ...POWER_COLORS, person: '#334155', peer: '#10b981' };

const ROLE_VIEWS = { ...VIEWS, corner: new Vector3(0.5, 0.3, 1).normalize() };

/**
 * Where everything goes: rows per floor, each floor at least as wide as the one
 * above it plus a margin, so that the whole reads as a pyramid.
 */
function layout(roles) {
    const floorCount = Math.max(...roles.map((role) => role.level)) + 1;
    const floors = Array.from({ length: floorCount }, (_, level) => ({ level, roles: roles.filter((role) => role.level === level) }));
    let width = 0;
    let depth = 0;

    [...floors].reverse().forEach((floor) => {
        floor.columns = Math.min(MAX_IN_ROW, Math.max(1, floor.roles.length));
        floor.rows = Math.max(1, Math.ceil(floor.roles.length / floor.columns));
        width = Math.max(floor.columns * SPACING, width + PLATE_MARGIN * 2);
        depth = Math.max(floor.rows * SPACING, depth + PLATE_MARGIN);
        floor.width = width;
        floor.depth = depth;
    });

    let y = 0;
    floors.forEach((floor) => {
        floor.y = y;
        floor.tallest = Math.max(MIN_COLUMN, ...floor.roles.map(columnHeight));
        y += floor.tallest + FLOOR_GAP;
    });

    return { floors, height: y - FLOOR_GAP + 2, base: floors[0] };
}

function columnHeight(role) {
    return MIN_COLUMN + strength(role) * (MAX_COLUMN - MIN_COLUMN);
}

export function mountRoleScene(element, tooltip, data, onSelect) {
    const world = new Group();
    const roles = data.roles;
    const { floors, height, base } = layout(roles);
    const plates = new BoxBatch();
    const columns = new BoxBatch();
    const details = new BoxBatch();
    const places = new Map();
    const targets = [];

    floors.forEach((floor) => {
        plates.add(floor.width, 0.2, floor.depth, COLORS.plate, 0, floor.y - 0.1, 0);

        // Peers side by side, so that their beam is short.
        const ordered = [...floor.roles].sort((a, b) => b.permissions - a.permissions || a.peerIds.length - b.peerIds.length);

        ordered.forEach((role, index) => {
            const row = Math.floor(index / floor.columns);
            const inRow = Math.min(floor.columns, ordered.length - row * floor.columns);
            const x = ((inRow - 1) / 2 - (index % floor.columns)) * SPACING;
            const z = ((floor.rows - 1) / 2 - row) * -SPACING;
            const tall = columnHeight(role);

            places.set(role.id, { x, z, y: floor.y, tall });
            targets.push({
                position: new Vector3(x, floor.y + tall + (role.super ? 1.1 : 0.3), z),
                text: role.caption,
                data: role,
            });

            if (role.super) {
                // A crown: nothing can be above this role.
                for (const [dx, dz] of [[-0.8, -0.8], [0.8, -0.8], [-0.8, 0.8], [0.8, 0.8]]) {
                    details.add(0.4, 0.7, 0.4, COLORS.super, x + dx, floor.y + tall + 0.35, z + dz);
                }
            }

            const people = Math.min(MAX_PEOPLE, role.holders);

            for (let person = 0; person < people; person++) {
                const personX = x + (person - (people - 1) / 2) * 0.36;

                details.add(0.2, 0.42, 0.2, COLORS.person, personX, floor.y + 0.21, z + COLUMN_WIDTH / 2 + 0.55);
                details.add(0.2, 0.2, 0.2, COLORS.person, personX, floor.y + 0.54, z + COLUMN_WIDTH / 2 + 0.55);
            }
        });
    });

    // The same order as `roles`, so a role's index is its column's.
    roles.forEach((role) => {
        const { x, z, y, tall } = places.get(role.id);

        columns.add(COLUMN_WIDTH, tall, COLUMN_WIDTH, powerColor(role), x, y + tall / 2, z);
    });

    const beams = [];

    // Everything is above a role with no permissions: beams would say nothing and hide the rest.
    roles.filter((role) => role.permissions > 0).forEach((role) => {
        const lower = places.get(role.id);

        role.parents.forEach((parentId) => {
            const upper = places.get(parentId);

            beams.push({
                lowerId: role.id,
                upperId: parentId,
                color: COLORS.beam,
                from: new Vector3(lower.x, lower.y + lower.tall, lower.z),
                to: new Vector3(upper.x, upper.y, upper.z),
            });
        });

        role.peerIds.filter((peerId) => peerId > role.id).forEach((peerId) => {
            const peer = places.get(peerId);

            beams.push({
                peer: true,
                lowerId: role.id,
                upperId: peerId,
                color: COLORS.peer,
                from: new Vector3(lower.x, lower.y + Math.min(lower.tall, 0.3), lower.z),
                to: new Vector3(peer.x, peer.y + Math.min(peer.tall, 0.3), peer.z),
            });
        });
    });

    const plateMesh = plates.toMesh();
    plateMesh.material.transparent = true;
    plateMesh.material.opacity = 0.35;
    plateMesh.material.depthWrite = false;

    const columnMesh = columns.toMesh();
    const beamMesh = makeBeams(beams);

    world.add(columnMesh, beamMesh, plateMesh);
    if (details.boxes.length > 0) {
        world.add(details.toMesh());
    }

    const highlight = (picked) => {
        recolor(columnMesh, roles.map((role) => {
            const related = !picked || role.id === picked.id || picked.aboveIds.includes(role.id) || picked.belowIds.includes(role.id) || picked.peerIds.includes(role.id);

            return related ? powerColor(role) : COLORS.faded;
        }));

        if (beams.length > 0) {
            const upward = picked ? new Set([picked.id, ...picked.aboveIds]) : new Set();
            const downward = picked ? new Set([picked.id, ...picked.belowIds]) : new Set();

            recolor(beamMesh, beams.map((beam) => {
                if (beam.peer) {
                    return !picked || beam.lowerId === picked.id || beam.upperId === picked.id ? COLORS.peer : COLORS.faded;
                }

                if (!picked) {
                    return COLORS.beam;
                }

                if (upward.has(beam.lowerId) && upward.has(beam.upperId)) {
                    return COLORS.up;
                }

                return downward.has(beam.lowerId) && downward.has(beam.upperId) ? COLORS.down : COLORS.faded;
            }));
        }

        stage.requestRender();
    };

    const stage = createStage(element, tooltip, {
        world,
        targets,
        radius: Math.max(Math.hypot(base.width, base.depth) / 2, height * 0.62),
        home: new Vector3(0, height * 0.46, 0),
        panLimit: new Vector3(base.width / 2, height, base.depth / 2),
        views: ROLE_VIEWS,
        onSelect(role) {
            highlight(role);
            onSelect?.(role);
        },
    });

    return {
        ...stage,
        clearSelection() {
            stage.clearSelection();
            highlight(null);
        },
    };
}
