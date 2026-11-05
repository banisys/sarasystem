import {
    BoxGeometry,
    Color,
    ConeGeometry,
    Group,
    InstancedMesh,
    Matrix4,
    Mesh,
    MeshBasicMaterial,
    MeshLambertMaterial,
    QuadraticBezierCurve3,
    Quaternion,
    Vector3,
} from 'three';
import { makePeople } from '@/scene/person-figure.js';
import { createStage } from '@/scene/stage.js';
import { BoxBatch, recolor, VIEWS } from './blocks.js';
import { makeBeams } from './power.js';

/**
 * A workflow as a staircase (ADR 0064): each step a platform one stair higher than
 * the one before, from the start on the right to the end on the left, in the lane
 * of its tenant. Green arrows are approvals, red chutes rejections, amber arcs
 * returns. A case can be played along the path, stopping at each step.
 */

const SPACING = 8;
const LANE_GAP = 8;
const PLATFORM = 4.2;
const MAX_RISE = 1.4;
const MAX_TOTAL_RISE = 14;
const MAX_FOLDERS = 12;
const ARC_SEGMENTS = 14;
const FLOW_RADIUS = 0.07;
const ARC_RADIUS = 0.045;
const MOVE_MS = 900;
const STOP_MS = 2600;

const COLORS = {
    ground: '#cbd5e1',
    start: '#22c55e',
    finish: '#f59e0b',
    approve: '#16a34a',
    reject: '#dc2626',
    return: '#f59e0b',
    edit: '#2563eb',
    note: '#7c3aed',
    folder: '#fcd34d',
    token: '#f97316',
    faded: '#e5e7eb',
};

const WORKFLOW_VIEWS = { ...VIEWS, corner: new Vector3(0.28, 0.5, 1).normalize() };

/**
 * Arrow heads, recoloured with the beams they end.
 */
function makeHeads(heads) {
    const mesh = new InstancedMesh(new ConeGeometry(0.26, 0.7, 10), new MeshLambertMaterial({ color: '#ffffff' }), Math.max(1, heads.length));
    const up = new Vector3(0, 1, 0);
    const matrix = new Matrix4();
    const rotation = new Quaternion();
    const scale = new Vector3(1, 1, 1);
    const color = new Color();

    mesh.count = heads.length;
    heads.forEach((head, index) => {
        rotation.setFromUnitVectors(up, head.direction);
        matrix.compose(head.position, rotation, scale);
        mesh.setMatrixAt(index, matrix);
        mesh.setColorAt(index, color.set(head.color));
    });

    return mesh;
}

/**
 * An arc going back from one platform to an earlier one, cut into short beams.
 */
function arcBetween(from, to, color) {
    const span = from.distanceTo(to);
    const middle = from.clone().add(to).multiplyScalar(0.5);
    const control = middle.clone().add(new Vector3(0, 3.5 + span * 0.3, -1));
    const points = new QuadraticBezierCurve3(from, control, to).getPoints(ARC_SEGMENTS);

    return {
        beams: points.slice(1).map((point, index) => ({ from: points[index], to: point, color })),
        head: {
            position: points[ARC_SEGMENTS],
            direction: points[ARC_SEGMENTS].clone().sub(points[ARC_SEGMENTS - 1]).normalize(),
            color,
        },
    };
}

/**
 * @param {HTMLElement} element
 * @param {HTMLElement} tooltip
 * @param {object} data WorkflowScene::scene()
 * @param {object} callbacks
 * @param {(picked: ?object) => void} callbacks.onSelect a step, a lane, the start or the end was clicked
 * @param {(stop: ?object) => void} callbacks.onPlay the played case reached a stop; null when it's over
 */
export function mountWorkflowScene(element, tooltip, data, { onSelect, onPlay }) {
    const world = new Group();
    const steps = data.steps;
    const count = steps.length;
    const rise = Math.min(MAX_RISE, MAX_TOTAL_RISE / count);
    const laneZ = (lane) => ((data.lanes.length - 1) / 2 - lane) * LANE_GAP;
    // The start is -1 and the end is `count`; Persian reads from the right.
    const pathX = (index) => ((count - 1) / 2 - index) * SPACING;
    const top = (index) => 1 + (index + 1) * rise;
    const stationAt = (step) => new Vector3(pathX(step.index), top(step.index), laneZ(step.lane));

    const length = (count + 1) * SPACING + PLATFORM;
    const depth = data.lanes.length * LANE_GAP;
    const height = top(count) + 4;

    const ground = new BoxBatch();
    const lanes = new BoxBatch();
    const platforms = new BoxBatch();
    const details = new BoxBatch();
    const chutes = new BoxBatch();
    const people = [];
    const targets = [];

    ground.add(length + 2, 0.2, depth + 2, COLORS.ground, 0, -0.1, 0);
    data.lanes.forEach((lane) => {
        lanes.add(length, 0.06, LANE_GAP - 1.4, lane.color, 0, 0.03, laneZ(lane.index));
        targets.push({
            // On the lane's front edge, between the start and the first step, where nothing stands.
            position: new Vector3(pathX(-0.5), 0.1, laneZ(lane.index) + (LANE_GAP - 1.4) / 2 - 0.2),
            text: lane.name,
            data: { kind: 'lane', ...lane },
        });
    });

    // The two ends.
    const startAt = new Vector3(pathX(-1), top(-1), laneZ(steps[0].lane));
    const finishAt = new Vector3(pathX(count), top(count), laneZ(steps[count - 1].lane));

    details.add(2.4, startAt.y, 2.4, COLORS.start, startAt.x, startAt.y / 2, startAt.z);
    details.add(2.8, finishAt.y, 2.8, COLORS.finish, finishAt.x, finishAt.y / 2, finishAt.z);
    details.add(1.2, 0.8, 1.2, '#fde68a', finishAt.x, finishAt.y + 0.4, finishAt.z);
    targets.push(
        { position: startAt.clone().add(new Vector3(0, 0.6, 0)), text: 'شروع', data: { kind: 'start' } },
        { position: finishAt.clone().add(new Vector3(0, 1.3, 0)), text: 'پایان', data: { kind: 'finish' } },
    );

    steps.forEach((step) => {
        const at = stationAt(step);
        const color = data.lanes[step.lane].color;

        // In the order `highlight` recolours: the column and the platform of each step.
        platforms.add(PLATFORM * 0.6, at.y - 0.3, PLATFORM * 0.6, color, at.x, (at.y - 0.3) / 2, at.z);
        platforms.add(PLATFORM, 0.3, PLATFORM, color, at.x, at.y - 0.15, at.z);

        // Whoever decides: one figure for a person, a group for a role's holders.
        const figures = step.isPerson ? [[0, 1.25, 0]] : [[-1.05, 1.05, 0.3], [0, 1.2, 0], [1.05, 1.05, -0.3]];

        figures.forEach(([dx, scale, turn]) => {
            people.push({ x: at.x + dx, y: at.y, z: at.z - 0.9, scale, turn });
        });

        // A file per open case, beside the people so that a tall pile hides nobody.
        for (let folder = 0; folder < Math.min(MAX_FOLDERS, step.openCases); folder++) {
            details.add(0.85, 0.12, 0.65, COLORS.folder, at.x + PLATFORM / 2 - 0.55, at.y + 0.08 + folder * 0.15, at.z + 0.25);
        }

        if (step.edits) {
            details.add(0.08, 1.4, 0.08, COLORS.edit, at.x - PLATFORM / 2 + 0.35, at.y + 0.7, at.z + PLATFORM / 2 - 0.35);
            details.add(0.6, 0.36, 0.05, COLORS.edit, at.x - PLATFORM / 2 + 0.68, at.y + 1.2, at.z + PLATFORM / 2 - 0.35);
        }

        // Something to do before approving: documents, a contract.
        if (step.notes.length > 0) {
            details.add(0.7, 0.9, 0.08, COLORS.note, at.x + PLATFORM / 2 - 0.55, at.y + 0.45, at.z + PLATFORM / 2 - 0.35);
        }

        if (step.rejects) {
            const mouth = new Vector3(at.x, at.y - 0.2, at.z + PLATFORM / 2);
            const floor = new Vector3(at.x, 0.3, at.z + PLATFORM / 2 + 1.1);

            chutes.add(1.5, 0.3, 1.1, COLORS.reject, floor.x, 0.2, floor.z);
            step.chute = { from: mouth, to: floor };
        }

        targets.push({
            position: at.clone().add(new Vector3(0, 2.35, -0.9)),
            text: `${step.number}. ${step.name}`,
            data: step,
        });
    });

    // Approvals; `edges` keeps which steps each one joins, -1 and `count` being the two ends.
    const placeOf = (index) => {
        if (index === -1) {
            return startAt;
        }

        return index === count ? finishAt : stationAt(steps[index]);
    };
    const edges = [];
    const heads = [];
    const flow = [];

    for (let index = -1; index < count; index++) {
        const direction = placeOf(index + 1).clone().sub(placeOf(index)).normalize();
        const from = placeOf(index).clone().add(new Vector3(0, 0.15, 0)).addScaledVector(direction, index === -1 ? 1.5 : PLATFORM / 2);
        const to = placeOf(index + 1).clone().add(new Vector3(0, 0.15, 0)).addScaledVector(direction, index + 1 === count ? -1.7 : -PLATFORM / 2 - 0.3);

        flow.push({ from, to, color: COLORS.approve, kind: 'approve', index });
        heads.push({ position: to, direction, color: COLORS.approve, kind: 'approve', index });
        edges.push(index);
    }

    steps.filter((step) => step.chute).forEach((step) => {
        const direction = step.chute.to.clone().sub(step.chute.from).normalize();

        flow.push({ ...step.chute, color: COLORS.reject, kind: 'reject', index: step.index });
        heads.push({ position: step.chute.to.clone().addScaledVector(direction, -0.2), direction, color: COLORS.reject, kind: 'reject', index: step.index });
    });

    // At rest, one arc back to the step before; clicking a step shows all of its arcs.
    const arcEnd = (step) => stationAt(step).add(new Vector3(0, 0.15, -PLATFORM / 2 + 0.25));
    const restingArcs = new Group();
    const arcs = steps.filter((step) => step.returns).map((step) => ({
        step,
        ...arcBetween(arcEnd(step), arcEnd(steps[step.index - 1]), COLORS.return),
    }));

    const flowMesh = makeBeams(flow, FLOW_RADIUS);
    const headMesh = makeHeads(heads);

    if (arcs.length > 0) {
        restingArcs.add(makeBeams(arcs.flatMap((arc) => arc.beams), ARC_RADIUS), makeHeads(arcs.map((arc) => arc.head)));
    }

    const lanesMesh = lanes.toMesh();
    lanesMesh.material.transparent = true;
    lanesMesh.material.opacity = 0.32;
    lanesMesh.material.depthWrite = false;

    const platformMesh = platforms.toMesh();

    world.add(ground.toMesh(), lanesMesh, platformMesh, details.toMesh(), makePeople(people), flowMesh, headMesh, restingArcs);
    if (chutes.boxes.length > 0) {
        world.add(chutes.toMesh());
    }

    const token = new Mesh(new BoxGeometry(1.1, 0.34, 0.85), new MeshBasicMaterial({ color: COLORS.token }));
    token.visible = false;
    world.add(token);

    let pickedArcs = null;

    const clearPickedArcs = () => {
        if (pickedArcs) {
            world.remove(pickedArcs);
            pickedArcs.traverse((object) => {
                object.geometry?.dispose();
                object.material?.dispose();
                object.dispose?.();
            });
            pickedArcs = null;
        }
    };

    /**
     * Keeps the colour of what matters to the picked step or lane, and fades the rest.
     */
    const highlight = (picked) => {
        const isStep = picked?.kind === 'step';
        const isLane = picked?.kind === 'lane';
        const stepIsOn = (step) => !picked
            || !(isStep || isLane)
            || (isStep && Math.abs(step.index - picked.index) <= 1)
            || (isLane && step.lane === picked.index);

        recolor(platformMesh, steps.flatMap((step) => {
            const color = stepIsOn(step) ? data.lanes[step.lane].color : COLORS.faded;

            return [color, color];
        }));

        const edgeColor = (edge) => {
            if (!isStep && !isLane) {
                return edge.color;
            }

            if (isLane) {
                return COLORS.faded;
            }

            const touches = edge.kind === 'approve' ? [edge.index, edge.index + 1].includes(picked.index) : edge.index === picked.index;

            return touches ? edge.color : COLORS.faded;
        };

        recolor(flowMesh, flow.map(edgeColor));
        recolor(headMesh, heads.map(edgeColor));

        clearPickedArcs();
        restingArcs.visible = !isStep && !isLane;

        // All the arcs of the picked step: it can return to any step before it.
        if (isStep && picked.returns) {
            const all = steps.slice(0, picked.index).map((earlier) => arcBetween(arcEnd(picked), arcEnd(earlier), COLORS.return));

            pickedArcs = new Group();
            pickedArcs.add(makeBeams(all.flatMap((arc) => arc.beams), ARC_RADIUS), makeHeads(all.map((arc) => arc.head)));
            world.add(pickedArcs);
        }

        stage.requestRender();
    };

    // Playing a case along the path.
    let timer = null;
    let moveFrame = null;

    const stop = () => {
        clearTimeout(timer);
        cancelAnimationFrame(moveFrame);
        timer = null;
        moveFrame = null;
        token.visible = false;
        stage.requestRender();
    };

    const tokenAt = (index) => placeOf(index).clone().add(new Vector3(0, index === count ? 1.1 : 0.32, index === -1 || index === count ? 0 : 0.2));

    const announce = (index) => {
        if (index === -1) {
            onPlay({ kind: 'start' });
        } else if (index === count) {
            onPlay({ kind: 'finish' });
        } else {
            onPlay(steps[index]);
        }
    };

    const arrive = (index) => {
        token.position.copy(tokenAt(index));
        announce(index);

        highlight(index >= 0 && index < count ? steps[index] : null);

        timer = setTimeout(() => {
            if (index === count) {
                stop();
                highlight(null);
                onPlay(null);
            } else {
                travel(index);
            }
        }, STOP_MS);
    };

    const travel = (index) => {
        const from = tokenAt(index);
        const to = tokenAt(index + 1);

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            arrive(index + 1);

            return;
        }

        const started = performance.now();
        const frame = (now) => {
            const progress = Math.min((now - started) / MOVE_MS, 1);

            token.position.lerpVectors(from, to, progress);
            stage.requestRender();

            if (progress < 1) {
                moveFrame = requestAnimationFrame(frame);
            } else {
                arrive(index + 1);
            }
        };

        moveFrame = requestAnimationFrame(frame);
    };

    // The path is long and low: on a wide screen it can fill the width, which a
    // ball around it wouldn't.
    const aspect = element.clientWidth / Math.max(1, element.clientHeight);
    // Many lanes make it deep instead, and the near lane then needs the height.
    const radius = Math.max((Math.hypot(length, depth * 0.6) * 1.1) / Math.max(2, 1.9 * aspect), (depth + height) / 1.8);

    const stage = createStage(element, tooltip, {
        world,
        targets,
        radius,
        home: new Vector3(0, height * 0.35, 0),
        panLimit: new Vector3(length / 2, height, depth / 2 + 4),
        views: WORKFLOW_VIEWS,
        onSelect(picked) {
            if (timer !== null || moveFrame !== null) {
                stop();
                onPlay(null);
            }

            highlight(picked);
            onSelect?.(picked);
        },
    });

    return {
        ...stage,
        play() {
            stop();
            stage.clearSelection();
            onSelect?.(null);
            token.visible = true;
            arrive(-1);
        },
        stopPlaying() {
            stop();
            highlight(null);
            onPlay(null);
        },
        clearSelection() {
            stage.clearSelection();
            highlight(null);
        },
        dispose() {
            clearTimeout(timer);
            cancelAnimationFrame(moveFrame);
            clearPickedArcs();
            stage.dispose();
        },
    };
}
