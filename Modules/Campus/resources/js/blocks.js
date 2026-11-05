import {
    BoxGeometry,
    Color,
    CylinderGeometry,
    IcosahedronGeometry,
    InstancedMesh,
    Matrix4,
    MeshBasicMaterial,
    MeshLambertMaterial,
    Vector3,
} from 'three';

/**
 * What the building and the organization map share (ADR 0052, ADR 0054, ADR 0055):
 * boxes drawn as one batch, live updates, the town's streets and street furniture,
 * roofs and shops; and the map's blocks with a window per room, however many
 * buildings there are.
 */

export const ROOM_WIDTH = 3;
export const FLOOR_HEIGHT = 2.6;
export const LOBBY_HEIGHT = 2.8;
export const BLOCK_DEPTH = 6;
export const ROAD_WIDTH = 4;
const MIN_FLOORS = 3;

export const CITY = {
    pavement: '#c9c5bd',
    road: '#4b4f55',
    line: '#e7e5e4',
    planter: '#7fa66a',
    parking: '#6b6f75',
    shop: '#7e97a1',
    awning: '#b4533c',
    shutter: '#a8a29a',
    roofUnit: '#9ca3af',
    lamp: '#44403c',
};

/** The façades of the town, so the buildings aren't all alike. */
export const FACADES = [
    { body: '#e7e0d3', slab: '#b8ad9a', roof: '#8d8371' },
    { body: '#c98f70', slab: '#9c6a50', roof: '#6f5a4d' },
    { body: '#bdbdb8', slab: '#8f8f8a', roof: '#6b7280' },
    { body: '#dcc9a3', slab: '#ab9873', roof: '#857659' },
];

export const PRIMARY_ROOF = '#a855f7';

const CAR_COLORS = ['#dc2626', '#f8fafc', '#1d4ed8', '#facc15', '#1f2937', '#94a3b8'];

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
 * A road with a dashed centre line. `crossings` are the places on it where another
 * road crosses, which have no line.
 *
 * @param {'x'|'z'} along the direction it runs in; roads along z lie a little higher
 */
export function addRoad(boxes, along, x, z, length, crossings = []) {
    const isAlongX = along === 'x';
    const top = isAlongX ? 0.04 : 0.05;
    const start = isAlongX ? x : z;

    if (isAlongX) {
        boxes.add(length, top, ROAD_WIDTH, CITY.road, x, top / 2, z);
    } else {
        boxes.add(ROAD_WIDTH, top, length, CITY.road, x, top / 2, z);
    }

    for (let offset = -length / 2 + 0.6; offset < length / 2 - 0.6; offset += 2.4) {
        const at = start + offset;

        if (crossings.every((crossing) => Math.abs(at - crossing) > ROAD_WIDTH / 2 + 0.7)) {
            if (isAlongX) {
                boxes.add(1.1, 0.01, 0.12, CITY.line, at, 0.056, z);
            } else {
                boxes.add(0.12, 0.01, 1.1, CITY.line, x, 0.056, at);
            }
        }
    }
}

/**
 * Stripes across a road running along `along`, centred at (x, z).
 */
export function addCrosswalk(boxes, along, x, z) {
    for (let offset = -ROAD_WIDTH / 2 + 0.3; offset < ROAD_WIDTH / 2; offset += 0.6) {
        if (along === 'x') {
            boxes.add(1.1, 0.01, 0.3, CITY.line, x, 0.058, z + offset);
        } else {
            boxes.add(0.3, 0.01, 1.1, CITY.line, x + offset, 0.058, z);
        }
    }
}

/**
 * A car; `seed` picks its colour, so the same scene always looks the same.
 */
export function addCar(boxes, along, x, z, seed) {
    const color = CAR_COLORS[seed % CAR_COLORS.length];
    const [length, width] = [1.7, 0.8];

    if (along === 'x') {
        boxes.add(length, 0.4, width, color, x, 0.3, z);
        boxes.add(length * 0.5, 0.32, width * 0.86, '#334155', x, 0.64, z);
    } else {
        boxes.add(width, 0.4, length, color, x, 0.3, z);
        boxes.add(width * 0.86, 0.32, length * 0.5, '#334155', x, 0.64, z);
    }
}

/**
 * A street lamp at (x, z), its arm towards the road on the +z side.
 */
export function addLamp(boxes, x, z) {
    boxes.add(0.1, 2.6, 0.1, CITY.lamp, x, 1.3, z);
    boxes.add(0.1, 0.08, 0.7, CITY.lamp, x, 2.6, z + 0.3);
    boxes.add(0.22, 0.1, 0.3, '#fef3c7', x, 2.54, z + 0.6);
}

/**
 * A strip of planting in the pavement; low, so that it hides nothing.
 */
export function addPlanter(boxes, width, depth, x, z) {
    boxes.add(width + 0.12, 0.2, depth + 0.12, CITY.shutter, x, 0.1, z);
    boxes.add(width, 0.24, depth, CITY.planter, x, 0.12, z);
}

/**
 * A flat roof with a low wall round it and machines on top.
 */
export function addRoof(boxes, width, depth, roofColor, x, top, z) {
    const wall = 0.18;
    const wallHeight = 0.5;

    boxes.add(width + 0.2, 0.2, depth + 0.2, roofColor, x, top + 0.1, z);

    for (const side of [-1, 1]) {
        boxes.add(width + 0.2, wallHeight, wall, roofColor, x, top + 0.2 + wallHeight / 2, z + (side * (depth + 0.2 - wall)) / 2);
        boxes.add(wall, wallHeight, depth + 0.2, roofColor, x + (side * (width + 0.2 - wall)) / 2, top + 0.2 + wallHeight / 2, z);
    }

    boxes.add(1.1, 0.7, 0.9, CITY.roofUnit, x - width * 0.22, top + 0.55, z - depth * 0.15);
    boxes.add(0.8, 0.5, 0.8, CITY.roofUnit, x + width * 0.25, top + 0.45, z + depth * 0.1);
}

/**
 * The entrance in the middle of the ground floor with shops on both sides.
 */
export function addShopfront(boxes, width, x, front) {
    const doorHalf = 1.3;
    const shopWidth = width / 2 - doorHalf - 0.3;

    boxes.add(2.2, 2.2, 0.1, '#6b4f35', x, 1.1, front + 0.03);

    if (shopWidth > 0.5) {
        for (const side of [-1, 1]) {
            boxes.add(shopWidth, 1.5, 0.08, CITY.shop, x + side * (doorHalf + shopWidth / 2), 1.05, front + 0.03);
            boxes.add(shopWidth + 0.1, 0.12, 0.7, CITY.awning, x + side * (doorHalf + shopWidth / 2), 2.05, front + 0.35);
        }
    }
}

/**
 * How big a block with so many rooms is. Never low: a town's buildings have floors.
 */
export function blockSize(roomCount) {
    const columns = Math.max(2, Math.min(12, Math.ceil(Math.sqrt(roomCount * 1.5))));
    const floors = Math.max(MIN_FLOORS, Math.ceil(roomCount / columns));

    return { columns, floors, width: columns * ROOM_WIDTH + 1, height: LOBBY_HEIGHT + floors * FLOOR_HEIGHT };
}

/**
 * One block at (x, z): body, roof, floors and shops in `boxes`, a window per
 * room in `glass`, coloured by the room's state. Places without a room have
 * their shutter down.
 *
 * @returns {{room: object, position: Vector3}[]} where each room's name goes
 */
export function addBlock(boxes, glass, rooms, states, look, x, z) {
    const { columns, floors, width, height } = blockSize(rooms.length);
    const front = z + BLOCK_DEPTH / 2;
    const place = (index) => ({
        x: x + ((columns - 1) / 2 - (index % columns)) * ROOM_WIDTH,
        y: LOBBY_HEIGHT + Math.floor(index / columns) * FLOOR_HEIGHT + FLOOR_HEIGHT * 0.52,
    });

    boxes.add(width, height, BLOCK_DEPTH, look.body, x, height / 2, z);
    addRoof(boxes, width, BLOCK_DEPTH, look.roof, x, height, z);
    addShopfront(boxes, width, x, front);

    for (let floor = 0; floor <= floors; floor++) {
        boxes.add(width + 0.2, 0.12, BLOCK_DEPTH + 0.2, look.slab, x, LOBBY_HEIGHT + floor * FLOOR_HEIGHT - 0.06, z);
    }

    for (let index = rooms.length; index < columns * floors; index++) {
        boxes.add(2.2, 1.5, 0.06, CITY.shutter, place(index).x, place(index).y, front + 0.03);
    }

    return rooms.map((room, index) => {
        const { x: roomX, y: roomY } = place(index);

        glass.add(2.2, 1.5, 0.1, states[room.state].color, roomX, roomY, front + 0.03);

        return { room, position: new Vector3(roomX, roomY + 0.8, front) };
    });
}

/**
 * Small round street trees at the given [x, z] spots; the same shape repeated.
 */
export function plantTrees(world, spots) {
    const trunks = new InstancedMesh(new CylinderGeometry(0.07, 0.1, 1.2, 6), new MeshLambertMaterial({ color: '#7c5a3a' }), spots.length);
    const crowns = new InstancedMesh(new IcosahedronGeometry(0.75, 1), new MeshLambertMaterial({ color: '#5b8a47' }), spots.length);
    const matrix = new Matrix4();

    spots.forEach(([x, z], index) => {
        matrix.makeTranslation(x, 0.6, z);
        trunks.setMatrixAt(index, matrix);
        matrix.makeTranslation(x, 1.7, z);
        crowns.setMatrixAt(index, matrix);
    });
    world.add(trunks, crowns);
}
