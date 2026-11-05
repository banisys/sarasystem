import {
    Color,
    CylinderGeometry,
    Group,
    IcosahedronGeometry,
    InstancedMesh,
    Matrix4,
    MeshLambertMaterial,
    Vector3,
} from 'three';
import { createStage } from '@/scene/stage.js';
import { BoxBatch, recolor, VIEWS } from './blocks.js';
import { makeBeams, POWER_COLORS, powerColor, strength } from './power.js';

/**
 * A tenant's staff on a pyramid of power (ADR 0058): each employee is a figure with
 * their name, on the floor of their level. The stronger, the taller and darker.
 * Those with exactly the same permissions stand together, and beams join such a
 * group to the nearest above.
 */

const PERSON_X = 2.4;
const PERSON_Z = 2.2;
const GROUP_MARGIN = 1.4;
const MAX_ROW_WIDTH = 34;
const PLATE_MARGIN = 1.6;
const FLOOR_GAP = 3.4;
const MIN_FIGURE = 1.2;
const MAX_FIGURE = 2.6;
const PICKED = '#f97316';

const STAFF_VIEWS = { ...VIEWS, corner: new Vector3(0.5, 0.3, 1).normalize() };

function figureHeight(group) {
    return MIN_FIGURE + strength(group) * (MAX_FIGURE - MIN_FIGURE);
}

/**
 * Where everything goes: a group's people in a small block, a floor's groups in
 * rows, each floor at least as big as the one above it plus a margin.
 */
function layout(groups) {
    const floorCount = Math.max(...groups.map((group) => group.level)) + 1;
    const floors = Array.from({ length: floorCount }, (_, level) => {
        const clusters = groups.filter((group) => group.level === level).map((group) => {
            const columns = Math.min(6, Math.ceil(Math.sqrt(group.people.length * 1.6)));
            const rows = Math.ceil(group.people.length / columns);

            return { group, columns, rows, width: columns * PERSON_X + GROUP_MARGIN, depth: rows * PERSON_Z + GROUP_MARGIN };
        });

        const lines = [];
        clusters.forEach((cluster) => {
            const line = lines.at(-1);

            if (line && line.width + cluster.width <= MAX_ROW_WIDTH) {
                line.clusters.push(cluster);
                line.width += cluster.width;
                line.depth = Math.max(line.depth, cluster.depth);
            } else {
                lines.push({ clusters: [cluster], width: cluster.width, depth: cluster.depth });
            }
        });

        return { level, lines };
    });

    let width = 0;
    let depth = 0;
    [...floors].reverse().forEach((floor) => {
        width = Math.max(Math.max(0, ...floor.lines.map((line) => line.width)) + PLATE_MARGIN, width + PLATE_MARGIN * 2);
        depth = Math.max(floor.lines.reduce((sum, line) => sum + line.depth, 0) + PLATE_MARGIN, depth + PLATE_MARGIN);
        floor.width = width;
        floor.depth = depth;
    });

    let y = 0;
    floors.forEach((floor) => {
        floor.y = y;
        y += MAX_FIGURE + FLOOR_GAP;
    });

    return { floors, height: y - FLOOR_GAP, base: floors[0] };
}

export function mountStaffScene(element, tooltip, data, onSelect) {
    const world = new Group();
    const { floors, height, base } = layout(data.roles);
    const plates = new BoxBatch();
    const crowns = new BoxBatch();
    const figures = [];
    const targets = [];
    const centres = new Map();

    floors.forEach((floor) => {
        plates.add(floor.width, 0.2, floor.depth, POWER_COLORS.plate, 0, floor.y - 0.1, 0);

        let z = floor.lines.reduce((sum, line) => sum + line.depth, 0) / 2;

        floor.lines.forEach((line) => {
            let x = line.width / 2;

            line.clusters.forEach(({ group, columns, rows, width: clusterWidth }) => {
                const centreX = x - clusterWidth / 2;
                const centreZ = z - line.depth / 2;
                const tall = figureHeight(group);

                centres.set(group.id, { x: centreX, z: centreZ, y: floor.y, tall });

                group.people.forEach((person, index) => {
                    const row = Math.floor(index / columns);
                    const inRow = Math.min(columns, group.people.length - row * columns);
                    const personX = centreX + ((inRow - 1) / 2 - (index % columns)) * PERSON_X;
                    const personZ = centreZ + ((rows - 1) / 2 - row) * PERSON_Z;

                    figures.push({ group, person, x: personX, y: floor.y, z: personZ, tall });
                    targets.push({
                        position: new Vector3(personX, floor.y + tall + 0.3, personZ),
                        text: person.name,
                        data: { ...group, person },
                    });

                    if (group.super) {
                        for (const [dx, dz] of [[-0.17, 0], [0.17, 0], [0, -0.17], [0, 0.17]]) {
                            crowns.add(0.1, 0.22, 0.1, POWER_COLORS.super, personX + dx, floor.y + tall + 0.1, personZ + dz);
                        }
                    }
                });

                x -= clusterWidth;
            });

            z -= line.depth;
        });
    });

    const matrix = new Matrix4();
    const color = new Color();
    const bodies = new InstancedMesh(new CylinderGeometry(0.2, 0.32, 1, 12), new MeshLambertMaterial({ color: '#ffffff' }), figures.length);
    const heads = new InstancedMesh(new IcosahedronGeometry(0.25, 1), new MeshLambertMaterial({ color: '#ffffff' }), figures.length);

    figures.forEach(({ group, x, y, z, tall }, index) => {
        const bodyHeight = tall - 0.5;

        matrix.makeScale(1, bodyHeight, 1).setPosition(x, y + bodyHeight / 2, z);
        bodies.setMatrixAt(index, matrix);
        bodies.setColorAt(index, color.set(powerColor(group)));
        matrix.makeTranslation(x, y + tall - 0.25, z);
        heads.setMatrixAt(index, matrix);
        heads.setColorAt(index, color.set(powerColor(group)));
    });

    const beams = [];

    // Everything is above a group with no permissions: beams would say nothing and hide the rest.
    data.roles.filter((group) => group.permissions > 0).forEach((group) => {
        const lower = centres.get(group.id);

        group.parents.forEach((parentId) => {
            const upper = centres.get(parentId);

            beams.push({
                lowerId: group.id,
                upperId: parentId,
                color: POWER_COLORS.beam,
                from: new Vector3(lower.x, lower.y + lower.tall, lower.z),
                to: new Vector3(upper.x, upper.y, upper.z),
            });
        });
    });

    const plateMesh = plates.toMesh();
    plateMesh.material.transparent = true;
    plateMesh.material.opacity = 0.35;
    plateMesh.material.depthWrite = false;

    const beamMesh = makeBeams(beams);

    world.add(bodies, heads, beamMesh, plateMesh);
    if (crowns.boxes.length > 0) {
        world.add(crowns.toMesh());
    }

    const highlight = (picked) => {
        const colors = figures.map(({ group, person }) => {
            if (!picked) {
                return powerColor(group);
            }

            if (person.user_id === picked.person.user_id) {
                return PICKED;
            }

            const isRelated = group.id === picked.id || picked.aboveIds.includes(group.id) || picked.belowIds.includes(group.id);

            return isRelated ? powerColor(group) : POWER_COLORS.faded;
        });

        recolor(bodies, colors);
        recolor(heads, colors);

        if (beams.length > 0) {
            const upward = picked ? new Set([picked.id, ...picked.aboveIds]) : new Set();
            const downward = picked ? new Set([picked.id, ...picked.belowIds]) : new Set();

            recolor(beamMesh, beams.map((beam) => {
                if (!picked) {
                    return POWER_COLORS.beam;
                }

                if (upward.has(beam.lowerId) && upward.has(beam.upperId)) {
                    return POWER_COLORS.up;
                }

                return downward.has(beam.lowerId) && downward.has(beam.upperId) ? POWER_COLORS.down : POWER_COLORS.faded;
            }));
        }

        stage.requestRender();
    };

    const stage = createStage(element, tooltip, {
        world,
        targets,
        radius: Math.max(Math.hypot(base.width, base.depth) / 2, height * 0.62),
        home: new Vector3(0, height * 0.42, 0),
        panLimit: new Vector3(base.width / 2, height, base.depth / 2),
        views: STAFF_VIEWS,
        onSelect(picked) {
            highlight(picked);
            onSelect?.(picked);
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
