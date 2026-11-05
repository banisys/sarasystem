import {
    BoxGeometry,
    CapsuleGeometry,
    Color,
    CylinderGeometry,
    Euler,
    InstancedMesh,
    Matrix4,
    MeshLambertMaterial,
    Quaternion,
    SphereGeometry,
    Vector3,
} from 'three';
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js';

/**
 * A person standing, feet at the origin, facing +z, about 1.7 tall: one shape with
 * the colours in its vertices, so that any number of them is one draw call.
 */

const LOOK = {
    suit: '#334155',
    trousers: '#1e293b',
    shoes: '#0f172a',
    skin: '#e8b995',
    hair: '#3b2a20',
    eyes: '#1c1917',
};

function part(geometry, color, { x = 0, y = 0, z = 0, scale = [1, 1, 1], tilt = [0, 0, 0] } = {}) {
    const placed = geometry.applyMatrix4(
        new Matrix4().compose(new Vector3(x, y, z), new Quaternion().setFromEuler(new Euler(...tilt)), new Vector3(...scale)),
    );
    const { r, g, b } = new Color(color);
    const colors = new Float32Array(placed.attributes.position.count * 3);

    for (let index = 0; index < colors.length; index += 3) {
        colors.set([r, g, b], index);
    }

    placed.setAttribute('color', placed.attributes.position.clone());
    placed.attributes.color.array.set(colors);

    return placed;
}

function personGeometry() {
    const limb = (radius, length) => new CapsuleGeometry(radius, length, 3, 8);
    const sides = [-1, 1].flatMap((side) => [
        part(limb(0.085, 0.62), LOOK.trousers, { x: side * 0.1, y: 0.44 }),
        part(new BoxGeometry(0.15, 0.08, 0.27), LOOK.shoes, { x: side * 0.1, y: 0.04, z: 0.05 }),
        part(limb(0.058, 0.5), LOOK.suit, { x: side * 0.275, y: 1.03, tilt: [0, 0, side * 0.1] }),
        part(new SphereGeometry(0.066, 8, 6), LOOK.skin, { x: side * 0.308, y: 0.7 }),
        part(new SphereGeometry(0.019, 6, 5), LOOK.eyes, { x: side * 0.052, y: 1.56, z: 0.133 }),
    ]);

    return mergeGeometries([
        ...sides,
        part(new CylinderGeometry(0.2, 0.15, 0.6, 12), LOOK.suit, { y: 1.04, scale: [1, 1, 0.68] }),
        part(limb(0.08, 0.36), LOOK.suit, { y: 1.31, tilt: [0, 0, Math.PI / 2], scale: [1, 1, 0.8] }),
        part(new CylinderGeometry(0.058, 0.066, 0.12, 8), LOOK.skin, { y: 1.41 }),
        part(new SphereGeometry(0.145, 20, 14), LOOK.skin, { y: 1.56, scale: [0.94, 1.12, 1] }),
        // The hair covers the top and the back of the head.
        part(new SphereGeometry(0.156, 28, 12, 0, Math.PI * 2, 0, Math.PI * 0.5), LOOK.hair, { y: 1.6, z: -0.022, scale: [0.95, 1.1, 1], tilt: [-0.45, 0, 0] }),
    ]);
}

/**
 * @param {{x: number, y: number, z: number, scale?: number, turn?: number}[]} people where each stands
 */
export function makePeople(people) {
    const mesh = new InstancedMesh(personGeometry(), new MeshLambertMaterial({ vertexColors: true }), people.length);
    const matrix = new Matrix4();
    const rotation = new Quaternion();
    const up = new Vector3(0, 1, 0);

    people.forEach(({ x, y, z, scale = 1, turn = 0 }, index) => {
        rotation.setFromAxisAngle(up, turn);
        matrix.compose(new Vector3(x, y, z), rotation, new Vector3(scale, scale, scale));
        mesh.setMatrixAt(index, matrix);
    });

    return mesh;
}
