import {
    BoxGeometry,
    Color,
    ConeGeometry,
    CylinderGeometry,
    InstancedMesh,
    Matrix4,
    MeshBasicMaterial,
    MeshLambertMaterial,
    Vector3,
} from 'three';

/**
 * What the building and the organization map are made of (ADR 0051, ADR 0052):
 * blocks with a window per room, all drawn as one batch of boxes however many
 * buildings there are.
 */

export const ROOM_WIDTH = 3;
export const FLOOR_HEIGHT = 2.6;
export const LOBBY_HEIGHT = 2.8;
export const BLOCK_DEPTH = 6;

export const LOOKS = {
    building: { body: '#e7e0d3', slab: '#b8ad9a', roof: '#8d8371' },
    online: { body: '#1e3a5f', slab: '#2f5a8a', roof: '#38bdf8' },
    primary: { body: '#e7e0d3', slab: '#b8ad9a', roof: '#a855f7' },
};

export const VIEWS = {
    corner: new Vector3(0.7, 0.55, 1).normalize(),
    front: new Vector3(0, 0.2, 1).normalize(),
    top: new Vector3(0, 1, 0.5).normalize(),
    side: new Vector3(1, 0.3, 0.15).normalize(),
};

/**
 * Boxes of any size and colour, drawn together.
 */
export class BoxBatch {
    boxes = [];

    add(width, height, depth, color, x, y, z) {
        this.boxes.push({ width, height, depth, color, x, y, z });
    }

    /**
     * @param {boolean} lit lit from inside: not shaded by the sun
     */
    toMesh(lit = false) {
        const material = lit ? new MeshBasicMaterial({ color: '#ffffff' }) : new MeshLambertMaterial({ color: '#ffffff' });
        const mesh = new InstancedMesh(new BoxGeometry(1, 1, 1), material, this.boxes.length);
        const matrix = new Matrix4();

        this.boxes.forEach(({ width, height, depth, color, x, y, z }, index) => {
            matrix.makeScale(width, height, depth).setPosition(x, y, z);
            mesh.setMatrixAt(index, matrix);
            mesh.setColorAt(index, new Color(color));
        });

        return mesh;
    }
}

/**
 * New colours for the windows, in the order they were added.
 */
export function recolor(mesh, colors) {
    const color = new Color();

    colors.forEach((value, index) => mesh.setColorAt(index, color.set(value)));
    mesh.instanceColor.needsUpdate = true;
}

/**
 * Fetches fresh data every so many seconds while the tab is showing, and at once
 * when it shows again (ADR 0053). One request at a time. No seconds, no fetching.
 */
export function keepLive(seconds, load, apply, failed) {
    let busy = false;
    let stopped = false;

    const now = async () => {
        if (busy || stopped || document.hidden) {
            return;
        }

        busy = true;

        try {
            const data = await load();

            if (!stopped) {
                apply(data);
            }
        } catch {
            if (!stopped) {
                failed();
            }
        } finally {
            busy = false;
        }
    };

    if (!seconds) {
        return { now, stop() {} };
    }

    const timer = setInterval(now, seconds * 1000);
    document.addEventListener('visibilitychange', now);

    return {
        now,
        stop() {
            stopped = true;
            clearInterval(timer);
            document.removeEventListener('visibilitychange', now);
        },
    };
}

/**
 * How big a block with so many rooms is.
 */
export function blockSize(roomCount) {
    const columns = Math.max(2, Math.min(12, Math.ceil(Math.sqrt(roomCount * 1.5))));
    const floors = Math.max(1, Math.ceil(roomCount / columns));

    return { columns, floors, width: columns * ROOM_WIDTH + 1, height: LOBBY_HEIGHT + floors * FLOOR_HEIGHT };
}

/**
 * One block at (x, z): body, roof, floors and entrance in `boxes`, a window per
 * room in `glass`, coloured by the room's state.
 *
 * @returns {{room: object, position: Vector3}[]} where each room's name goes
 */
export function addBlock(boxes, glass, rooms, states, look, x, z) {
    const { columns, floors, width, height } = blockSize(rooms.length);
    const front = z + BLOCK_DEPTH / 2;

    boxes.add(width, height, BLOCK_DEPTH, look.body, x, height / 2, z);
    boxes.add(width + 0.5, 0.3, BLOCK_DEPTH + 0.5, look.roof, x, height + 0.15, z);
    boxes.add(2.2, 2.2, 0.1, '#6b4f35', x, 1.1, front + 0.03);

    for (let floor = 0; floor <= floors; floor++) {
        boxes.add(width + 0.2, 0.12, BLOCK_DEPTH + 0.2, look.slab, x, LOBBY_HEIGHT + floor * FLOOR_HEIGHT - 0.06, z);
    }

    return rooms.map((room, index) => {
        const floor = Math.floor(index / columns);
        const inRow = floor === floors - 1 ? rooms.length - floor * columns : columns;
        const roomX = x + ((inRow - 1) / 2 - (index % columns)) * ROOM_WIDTH;
        const roomY = LOBBY_HEIGHT + floor * FLOOR_HEIGHT + FLOOR_HEIGHT * 0.52;

        glass.add(2.2, 1.5, 0.1, states[room.state].color, roomX, roomY, front + 0.03);

        return { room, position: new Vector3(roomX, roomY + 0.8, front) };
    });
}

/**
 * Trees along the front and the sides of the ground; the same two shapes repeated.
 */
export function plantTrees(world, groundWidth, groundDepth) {
    const spots = [];
    for (let x = -groundWidth / 2 + 2; x < groundWidth / 2 - 1; x += 5) {
        spots.push([x, groundDepth / 2 - 2]);
    }
    for (let z = -groundDepth / 2 + 2; z < groundDepth / 2 - 5; z += 5) {
        spots.push([-groundWidth / 2 + 2, z], [groundWidth / 2 - 2, z]);
    }

    // Every other one along the front leaves room for the entrances' paths.
    const planted = spots.filter(([, z], index) => index % 2 === 0 || z < groundDepth / 2 - 2);
    const trunks = new InstancedMesh(new CylinderGeometry(0.15, 0.2, 1, 6), new MeshLambertMaterial({ color: '#7c5a3a' }), planted.length);
    const leaves = new InstancedMesh(new ConeGeometry(1, 2.4, 7), new MeshLambertMaterial({ color: '#4d7c3f' }), planted.length);
    const matrix = new Matrix4();

    planted.forEach(([x, z], index) => {
        matrix.makeTranslation(x, 0.5, z);
        trunks.setMatrixAt(index, matrix);
        matrix.makeTranslation(x, 2.1, z);
        leaves.setMatrixAt(index, matrix);
    });
    world.add(trunks, leaves);
}
