import {
    BoxGeometry,
    CapsuleGeometry,
    Color,
    CylinderGeometry,
    Euler,
    Float32BufferAttribute,
    Group,
    InstancedMesh,
    Matrix4,
    MeshLambertMaterial,
    Quaternion,
    SphereGeometry,
    Vector3,
} from 'three';
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js';

/**
 * The people of every 3D view (ADR 0064), standing or seated, feet on the ground,
 * facing +z: one batch however many there are. Their clothes take each person's
 * colour, which can say something (a status, a level of power) and be changed with
 * `recolor(people.clothes, colors)`; skin, hair and shoes stay as they are.
 */

/** How tall a standing person is at scale 1. */
export const PERSON_HEIGHT = 1.77;

const DEFAULT_CLOTHES = '#334155';

const LOOK = {
    top: '#ffffff',
    // Darker than the top, whatever the colour of the clothes.
    trousers: '#a3a3a3',
    shoes: '#0f172a',
    skin: '#e8b995',
    hair: '#3b2a20',
    eyes: '#1c1917',
};

const UP = new Vector3(0, 1, 0);

function painted(geometry, color, matrix) {
    geometry.applyMatrix4(matrix);

    const { r, g, b } = new Color(color);
    const colors = [];

    for (let index = 0; index < geometry.attributes.position.count; index++) {
        colors.push(r, g, b);
    }

    geometry.setAttribute('color', new Float32BufferAttribute(colors, 3));

    return geometry;
}

function part(geometry, color, { x = 0, y = 0, z = 0, scale = [1, 1, 1], tilt = [0, 0, 0] } = {}) {
    return painted(geometry, color, new Matrix4().compose(
        new Vector3(x, y, z),
        new Quaternion().setFromEuler(new Euler(...tilt)),
        new Vector3(...scale),
    ));
}

/**
 * A limb from one point to another.
 */
function limb(radius, from, to, color) {
    const [start, end] = [new Vector3(...from), new Vector3(...to)];
    const direction = end.clone().sub(start);

    return painted(new CapsuleGeometry(radius, direction.length(), 2, 6), color, new Matrix4().compose(
        start.clone().add(end).multiplyScalar(0.5),
        new Quaternion().setFromUnitVectors(UP, direction.normalize()),
        new Vector3(1, 1, 1),
    ));
}

/**
 * From the waist up; `lower` moves it down for sitting.
 */
function upperBody(lower) {
    return {
        clothes: [
            part(new CylinderGeometry(0.2, 0.15, 0.6, 10), LOOK.top, { y: 1.04 - lower, scale: [1, 1, 0.68] }),
            part(new CapsuleGeometry(0.08, 0.36, 2, 6), LOOK.top, { y: 1.31 - lower, tilt: [0, 0, Math.PI / 2], scale: [1, 1, 0.8] }),
        ],
        fixed: [
            part(new CylinderGeometry(0.058, 0.066, 0.12, 6), LOOK.skin, { y: 1.41 - lower }),
            part(new SphereGeometry(0.145, 12, 9), LOOK.skin, { y: 1.56 - lower, scale: [0.94, 1.12, 1] }),
            // The hair covers the top and the back of the head.
            part(new SphereGeometry(0.156, 14, 6, 0, Math.PI * 2, 0, Math.PI * 0.5), LOOK.hair, { y: 1.6 - lower, z: -0.022, scale: [0.95, 1.1, 1], tilt: [-0.45, 0, 0] }),
            ...[-1, 1].map((side) => part(new SphereGeometry(0.019, 5, 4), LOOK.eyes, { x: side * 0.052, y: 1.56 - lower, z: 0.133 })),
        ],
    };
}

function standing() {
    const upper = upperBody(0);

    return {
        clothes: [
            ...upper.clothes,
            ...[-1, 1].flatMap((side) => [
                limb(0.085, [side * 0.1, 0.12, 0], [side * 0.1, 0.76, 0], LOOK.trousers),
                limb(0.058, [side * 0.25, 1.28, 0], [side * 0.3, 0.78, 0], LOOK.top),
            ]),
        ],
        fixed: [
            ...upper.fixed,
            ...[-1, 1].flatMap((side) => [
                part(new BoxGeometry(0.15, 0.08, 0.27), LOOK.shoes, { x: side * 0.1, y: 0.04, z: 0.05 }),
                part(new SphereGeometry(0.066, 6, 5), LOOK.skin, { x: side * 0.308, y: 0.7 }),
            ]),
        ],
    };
}

/**
 * Sitting at z = 0 on a seat 0.5 high, the hands forward on a table about 0.8 high.
 */
function seated() {
    const lower = 0.24;
    const upper = upperBody(lower);

    return {
        clothes: [
            ...upper.clothes,
            ...[-1, 1].flatMap((side) => [
                limb(0.085, [side * 0.1, 0.52, 0], [side * 0.1, 0.5, 0.34], LOOK.trousers),
                limb(0.075, [side * 0.1, 0.46, 0.36], [side * 0.1, 0.1, 0.36], LOOK.trousers),
                limb(0.058, [side * 0.25, 1.04, 0], [side * 0.29, 0.8, 0.12], LOOK.top),
                limb(0.052, [side * 0.29, 0.8, 0.12], [side * 0.2, 0.82, 0.38], LOOK.top),
            ]),
        ],
        fixed: [
            ...upper.fixed,
            ...[-1, 1].flatMap((side) => [
                part(new BoxGeometry(0.15, 0.08, 0.27), LOOK.shoes, { x: side * 0.1, y: 0.04, z: 0.41 }),
                part(new SphereGeometry(0.06, 6, 5), LOOK.skin, { x: side * 0.18, y: 0.83, z: 0.44 }),
            ]),
        ],
    };
}

/**
 * @param {{x: number, y?: number, z: number, scale?: number, turn?: number, color?: string}[]} people
 * @param {'standing'|'seated'} pose
 * @returns {Group} with `clothes`, the mesh to recolour
 */
export function makePeople(people, pose = 'standing') {
    const shape = pose === 'seated' ? seated() : standing();
    const clothes = new InstancedMesh(mergeGeometries(shape.clothes), new MeshLambertMaterial({ vertexColors: true }), people.length);
    const fixed = new InstancedMesh(mergeGeometries(shape.fixed), new MeshLambertMaterial({ vertexColors: true }), people.length);
    const matrix = new Matrix4();
    const rotation = new Quaternion();
    const color = new Color();

    people.forEach(({ x, y = 0, z, scale = 1, turn = 0, color: clothesColor = DEFAULT_CLOTHES }, index) => {
        rotation.setFromAxisAngle(UP, turn);
        matrix.compose(new Vector3(x, y, z), rotation, new Vector3(scale, scale, scale));
        clothes.setMatrixAt(index, matrix);
        fixed.setMatrixAt(index, matrix);
        clothes.setColorAt(index, color.set(clothesColor));
    });

    const group = new Group();

    group.add(clothes, fixed);
    group.clothes = clothes;

    return group;
}
