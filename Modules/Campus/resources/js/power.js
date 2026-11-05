import {
    Color,
    CylinderGeometry,
    InstancedMesh,
    Matrix4,
    MeshLambertMaterial,
    Quaternion,
    Vector3,
} from 'three';

/**
 * What the pyramids of roles and of people share (ADR 0057, ADR 0058).
 */

export const POWER_COLORS = {
    none: '#cbd5e1',
    weak: '#bfdbfe',
    strong: '#6d28d9',
    super: '#f59e0b',
    faded: '#e5e7eb',
    plate: '#94a3b8',
    beam: '#94a3b8',
    up: '#f59e0b',
    down: '#2563eb',
};

/**
 * 0 to 1, by the square root of the permissions: most have a small share of all
 * permissions, and would otherwise look alike beside the super admin.
 */
export function strength(node) {
    return Math.sqrt(node.share / 100);
}

export function powerColor(node) {
    if (node.super) {
        return POWER_COLORS.super;
    }

    if (node.permissions === 0) {
        return POWER_COLORS.none;
    }

    return `#${new Color(POWER_COLORS.weak).lerp(new Color(POWER_COLORS.strong), strength(node)).getHexString()}`;
}

/**
 * Beams between two points, recoloured when something is picked.
 */
export function makeBeams(beams, radius = 0.07) {
    const mesh = new InstancedMesh(new CylinderGeometry(radius, radius, 1, 6), new MeshLambertMaterial({ color: '#ffffff' }), Math.max(1, beams.length));
    const up = new Vector3(0, 1, 0);
    const matrix = new Matrix4();
    const direction = new Vector3();
    const middle = new Vector3();
    const rotation = new Quaternion();
    const color = new Color();

    mesh.count = beams.length;
    beams.forEach((beam, index) => {
        direction.subVectors(beam.to, beam.from);
        middle.addVectors(beam.from, beam.to).multiplyScalar(0.5);
        rotation.setFromUnitVectors(up, direction.clone().normalize());
        matrix.compose(middle, rotation, new Vector3(1, direction.length(), 1));
        mesh.setMatrixAt(index, matrix);
        mesh.setColorAt(index, color.set(beam.color));
    });

    return mesh;
}
