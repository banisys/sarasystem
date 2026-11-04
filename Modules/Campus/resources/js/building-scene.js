import {
    BoxGeometry,
    Color,
    ConeGeometry,
    CylinderGeometry,
    Group,
    InstancedMesh,
    Matrix4,
    Mesh,
    MeshBasicMaterial,
    MeshLambertMaterial,
    Vector3,
} from 'three';
import { createStage } from '@/scene/stage.js';

/**
 * A tenant's building (ADR 0051): a block with a window per room, generated
 * from the rooms, and a second, glassy block for those that meet only online.
 * It is a picture of the rooms, not the real building's plan.
 */

const ROOM_WIDTH = 3;
const FLOOR_HEIGHT = 2.6;
const LOBBY_HEIGHT = 2.8;
const BLOCK_DEPTH = 6;
const BLOCK_GAP = 5;

const VIEWS = {
    corner: new Vector3(0.7, 0.55, 1).normalize(),
    front: new Vector3(0, 0.2, 1).normalize(),
    top: new Vector3(0, 1, 0.5).normalize(),
    side: new Vector3(1, 0.3, 0.15).normalize(),
};

const LOOKS = {
    building: { body: '#e7e0d3', slab: '#b8ad9a', roof: '#8d8371' },
    online: { body: '#1e3a5f', slab: '#2f5a8a', roof: '#38bdf8' },
};

export function mountBuildingScene(element, tooltip, data, onSelect) {
    const world = new Group();
    const targets = [];
    const matrix = new Matrix4();
    const inPerson = data.rooms.filter((room) => !room.online);
    const online = data.rooms.filter((room) => room.online);
    const blocks = [inPerson, online].filter((rooms) => rooms.length > 0);
    const shapes = blocks.map((rooms) => {
        const columns = Math.max(2, Math.min(12, Math.ceil(Math.sqrt(rooms.length * 1.5))));

        return { rooms, columns, floors: Math.ceil(rooms.length / columns), width: columns * ROOM_WIDTH + 1 };
    });
    const totalWidth = shapes.reduce((sum, shape) => sum + shape.width, 0) + BLOCK_GAP * (shapes.length - 1);
    const groundWidth = totalWidth + 10;
    const groundDepth = BLOCK_DEPTH + 12;
    const height = Math.max(...shapes.map((shape) => LOBBY_HEIGHT + shape.floors * FLOOR_HEIGHT));

    const box = (w, h, d, color, x, y, z) => {
        const mesh = new Mesh(new BoxGeometry(w, h, d), new MeshLambertMaterial({ color }));
        mesh.position.set(x, y, z);
        world.add(mesh);
    };

    box(groundWidth, 0.4, groundDepth, '#a7c58b', 0, -0.2, 0);

    let cursor = -totalWidth / 2;
    const windows = [];
    for (const shape of shapes) {
        const look = shape.rooms === online ? LOOKS.online : LOOKS.building;
        const x = cursor + shape.width / 2;
        const bodyHeight = LOBBY_HEIGHT + shape.floors * FLOOR_HEIGHT;

        box(shape.width, bodyHeight, BLOCK_DEPTH, look.body, x, bodyHeight / 2, 0);
        box(shape.width + 0.5, 0.3, BLOCK_DEPTH + 0.5, look.roof, x, bodyHeight + 0.15, 0);
        // The entrance, with a path to the edge of the ground.
        box(2.2, 2.2, 0.1, '#6b4f35', x, 1.1, BLOCK_DEPTH / 2 + 0.03);
        box(2.2, 0.02, groundDepth / 2 - BLOCK_DEPTH / 2, '#d6d3d1', x, 0.01, (groundDepth / 2 + BLOCK_DEPTH / 2) / 2);

        for (let floor = 0; floor <= shape.floors; floor++) {
            box(shape.width + 0.2, 0.12, BLOCK_DEPTH + 0.2, look.slab, x, LOBBY_HEIGHT + floor * FLOOR_HEIGHT - 0.06, 0);
        }

        shape.rooms.forEach((room, index) => {
            const column = index % shape.columns;
            const floor = Math.floor(index / shape.columns);
            const inRow = floor === shape.floors - 1 ? shape.rooms.length - floor * shape.columns : shape.columns;
            const roomX = x + ((inRow - 1) / 2 - column) * ROOM_WIDTH;
            const roomY = LOBBY_HEIGHT + floor * FLOOR_HEIGHT + FLOOR_HEIGHT * 0.52;

            windows.push({ x: roomX, y: roomY, room });
            targets.push({ position: new Vector3(roomX, roomY + 0.8, BLOCK_DEPTH / 2), text: room.title, data: room });
        });

        cursor += shape.width + BLOCK_GAP;
    }

    // Lit from inside, so not shaded by the sun.
    const glass = new InstancedMesh(new BoxGeometry(2.2, 1.5, 0.1), new MeshBasicMaterial({ color: '#ffffff' }), windows.length);
    windows.forEach(({ x, y, room }, index) => {
        matrix.makeTranslation(x, y, BLOCK_DEPTH / 2 + 0.03);
        glass.setMatrixAt(index, matrix);
        glass.setColorAt(index, new Color(data.states[room.state].color));
    });
    world.add(glass);

    plantTrees(world, matrix, groundWidth, groundDepth);

    return createStage(element, tooltip, {
        world,
        targets,
        radius: Math.max(Math.hypot(groundWidth, groundDepth) / 2, height),
        home: new Vector3(0, height * 0.4, 0),
        panLimit: new Vector3(groundWidth / 2, height, groundDepth / 2),
        views: VIEWS,
        onSelect,
    });
}

/**
 * A row of trees along the front and the sides; the same two shapes repeated.
 */
function plantTrees(world, matrix, groundWidth, groundDepth) {
    const spots = [];
    for (let x = -groundWidth / 2 + 2; x < groundWidth / 2 - 1; x += 5) {
        spots.push([x, groundDepth / 2 - 2]);
    }
    for (let z = -groundDepth / 2 + 2; z < groundDepth / 2 - 5; z += 5) {
        spots.push([-groundWidth / 2 + 2, z], [groundWidth / 2 - 2, z]);
    }

    // Not in front of the entrances' paths.
    const planted = spots.filter(([x, z], index) => index % 2 === 0 || z < groundDepth / 2 - 2);
    const trunks = new InstancedMesh(new CylinderGeometry(0.15, 0.2, 1, 6), new MeshLambertMaterial({ color: '#7c5a3a' }), planted.length);
    const leaves = new InstancedMesh(new ConeGeometry(1, 2.4, 7), new MeshLambertMaterial({ color: '#4d7c3f' }), planted.length);

    planted.forEach(([x, z], index) => {
        matrix.makeTranslation(x, 0.5, z);
        trunks.setMatrixAt(index, matrix);
        matrix.makeTranslation(x, 2.1, z);
        leaves.setMatrixAt(index, matrix);
    });
    world.add(trunks, leaves);
}
