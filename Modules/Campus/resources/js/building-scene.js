import { Group, Vector3 } from 'three';
import { createStage } from '@/scene/stage.js';
import {
    addCar,
    addCrosswalk,
    addLamp,
    addPlanter,
    addRoad,
    addRoof,
    addShopfront,
    BoxBatch,
    CITY,
    plantTrees,
    recolor,
    ROAD_WIDTH,
    VIEWS,
} from './blocks.js';

export { keepLive } from './blocks.js';

/**
 * A tenant's building (ADR 0054, ADR 0055): one building on a town's street
 * corner, its front cut away, with a room per class on the floors above the
 * shops. Classes that meet only online are rooms too, with a screen instead of
 * desks. It is a picture of the classes, not the real building's plan.
 */

const ROOM_WIDTH = 4.4;
const ROOM_HEIGHT = 3;
const ROOM_DEPTH = 6;
const LOBBY_HEIGHT = 2.8;
const WALL = 0.15;
const MIN_FLOORS = 3;
const FRONT_PAVEMENT = 3.2;
const SIDE_PAVEMENT = 2.2;
const BACK_PAVEMENT = 1.2;

const COLORS = {
    wall: '#e7e0d3',
    slab: '#b8ad9a',
    roof: '#8d8371',
    door: '#6b4f35',
    board: '#f8fafc',
    screen: '#0ea5e9',
    desk: '#8a6a47',
};

/**
 * Seen more from the front than the map, so that the rooms' insides show.
 */
const BUILDING_VIEWS = { ...VIEWS, corner: new Vector3(0.45, 0.32, 1).normalize() };

/**
 * How many rooms in a row and how many floors. Never low: a town's buildings
 * have floors, and places without a class are closed.
 */
function layout(roomCount) {
    const columns = Math.max(2, Math.min(12, Math.ceil(Math.sqrt(roomCount * 1.5))));
    const floors = Math.max(MIN_FLOORS, Math.ceil(roomCount / columns));

    return {
        columns,
        floors,
        width: columns * ROOM_WIDTH + WALL,
        height: LOBBY_HEIGHT + floors * ROOM_HEIGHT,
    };
}

/**
 * What stands in a room.
 */
function furnish(boxes, room, x, floorY) {
    const back = -ROOM_DEPTH / 2 + WALL;

    if (room.online) {
        boxes.add(3, 1.5, 0.08, COLORS.screen, x, floorY + 1.6, back + 0.14);
        boxes.add(1.4, 0.7, 0.6, COLORS.desk, x, floorY + 0.35, back + 1.6);

        return;
    }

    boxes.add(2.2, 1, 0.06, COLORS.board, x, floorY + 1.7, back + 0.14);
    boxes.add(1.2, 0.7, 0.5, COLORS.door, x, floorY + 0.35, back + 1);

    for (let row = 0; row < 2; row++) {
        for (let column = -1; column <= 1; column++) {
            boxes.add(0.75, 0.5, 0.45, COLORS.desk, x + column * 1.2, floorY + 0.25, back + 2.5 + row * 1.3);
        }
    }
}

/**
 * The building at the origin: its shell in `boxes`, each room's back wall and
 * floor in `lit`, coloured by the room's state and added in the order of `rooms`.
 *
 * @returns {{position: Vector3, text: string, data: object}[]} what can be picked
 */
function addBuilding(boxes, lit, rooms, states) {
    const { columns, floors, width, height } = layout(rooms.length);
    const front = ROOM_DEPTH / 2;
    const roomsHeight = floors * ROOM_HEIGHT;

    // The ground floor, the back and the two ends; the rest of the front is open.
    boxes.add(width, LOBBY_HEIGHT, ROOM_DEPTH, COLORS.wall, 0, LOBBY_HEIGHT / 2, 0);
    addShopfront(boxes, width, 0, front);
    boxes.add(width, roomsHeight, WALL, COLORS.wall, 0, LOBBY_HEIGHT + roomsHeight / 2, -front + WALL / 2);
    addRoof(boxes, width, ROOM_DEPTH, COLORS.roof, 0, height, 0);

    for (let floor = 0; floor <= floors; floor++) {
        boxes.add(width + 0.2, 0.12, ROOM_DEPTH + 0.2, COLORS.slab, 0, LOBBY_HEIGHT + floor * ROOM_HEIGHT - 0.06, 0);
    }

    for (let column = 0; column <= columns; column++) {
        boxes.add(WALL, roomsHeight, ROOM_DEPTH, COLORS.wall, (column - columns / 2) * ROOM_WIDTH, LOBBY_HEIGHT + roomsHeight / 2, 0);
    }

    const roomX = (index) => ((columns - 1) / 2 - (index % columns)) * ROOM_WIDTH;
    const floorY = (index) => LOBBY_HEIGHT + Math.floor(index / columns) * ROOM_HEIGHT;

    // Places without a class are walled off, with their shutter down.
    for (let index = rooms.length; index < columns * floors; index++) {
        boxes.add(ROOM_WIDTH, ROOM_HEIGHT, WALL, COLORS.wall, roomX(index), floorY(index) + ROOM_HEIGHT / 2, front - WALL / 2);
        boxes.add(2.6, 1.6, 0.05, CITY.shutter, roomX(index), floorY(index) + ROOM_HEIGHT * 0.52, front + 0.02);
    }

    return rooms.map((room, index) => {
        const x = roomX(index);
        const y = floorY(index);
        const { color } = states[room.state];

        lit.add(ROOM_WIDTH - WALL, ROOM_HEIGHT - 0.12, 0.04, color, x, y + (ROOM_HEIGHT - 0.12) / 2, -front + WALL + 0.02);
        lit.add(ROOM_WIDTH - WALL, 0.03, ROOM_DEPTH - WALL, color, x, y + 0.015, WALL / 2);
        furnish(boxes, room, x, y);

        return { position: new Vector3(x, y + ROOM_HEIGHT / 2, front), text: room.title, data: room };
    });
}

/**
 * The street corner around the building: pavement, a road in front and one on the
 * side away from the camera, cars, lamps and a few trees.
 *
 * @returns {{centerX: number, centerZ: number, width: number, depth: number, trees: number[][]}}
 */
function addStreets(boxes, buildingWidth) {
    const left = -buildingWidth / 2 - SIDE_PAVEMENT - ROAD_WIDTH - 1;
    const right = buildingWidth / 2 + SIDE_PAVEMENT;
    const back = -ROOM_DEPTH / 2 - BACK_PAVEMENT;
    const roadZ = ROOM_DEPTH / 2 + FRONT_PAVEMENT + ROAD_WIDTH / 2;
    const near = roadZ + ROAD_WIDTH / 2 + 1;
    const width = right - left;
    const depth = near - back;
    const centerX = (left + right) / 2;
    const centerZ = (back + near) / 2;
    const roadX = left + 1 + ROAD_WIDTH / 2;
    const curbZ = ROOM_DEPTH / 2 + FRONT_PAVEMENT - 0.6;
    const trees = [];

    boxes.add(width, 0.4, depth, CITY.pavement, centerX, -0.2, centerZ);
    addRoad(boxes, 'x', centerX, roadZ, width, [roadX]);
    addRoad(boxes, 'z', roadX, centerZ, depth, [roadZ]);
    addCrosswalk(boxes, 'x', roadX + ROAD_WIDTH / 2 + 1, roadZ);
    addCar(boxes, 'x', right - 3, roadZ + 1, 0);
    addCar(boxes, 'x', centerX + 2.5, roadZ - 1, 2);
    addCar(boxes, 'z', roadX - 1, back + 2, 4);

    // Beside the entrance, and low: nothing hides the rooms.
    const planterWidth = buildingWidth / 2 - 2;

    addPlanter(boxes, planterWidth, 0.7, -planterWidth / 2 - 1.6, ROOM_DEPTH / 2 + 1.1);
    addPlanter(boxes, planterWidth, 0.7, planterWidth / 2 + 1.6, ROOM_DEPTH / 2 + 1.1);

    for (let x = -buildingWidth / 2; x <= buildingWidth / 2 + 0.01; x += buildingWidth / Math.max(2, Math.round(buildingWidth / 6))) {
        if (Math.abs(x) < 1.8) {
            addLamp(boxes, x, curbZ - 0.1);
        } else {
            trees.push([x, curbZ]);
        }
    }

    return { centerX, centerZ, width, depth, trees };
}

export function mountBuildingScene(element, tooltip, data, onSelect) {
    const world = new Group();
    const boxes = new BoxBatch();
    const lit = new BoxBatch();
    const { width: buildingWidth, height } = layout(data.rooms.length);
    const { centerX, centerZ, width, depth, trees } = addStreets(boxes, buildingWidth);

    const targets = addBuilding(boxes, lit, data.rooms, data.states);
    const roomColors = lit.toMesh(true);

    world.add(boxes.toMesh(), roomColors);
    plantTrees(world, trees);

    const stage = createStage(element, tooltip, {
        world,
        targets,
        radius: Math.max(Math.hypot(width, depth) / 2, height) * 0.92,
        home: new Vector3(centerX * 0.4, height * 0.42, centerZ * 0.4),
        panLimit: new Vector3(width, height, depth),
        views: BUILDING_VIEWS,
        onSelect,
    });

    return {
        ...stage,
        /**
         * The same rooms in a new state, in place. False when the rooms
         * changed: the building has to be built again.
         */
        update(fresh) {
            const rooms = new Map(fresh.rooms.map((room) => [room.id, room]));
            const isSame = fresh.rooms.length === targets.length
                && targets.every(({ data: room }) => rooms.get(room.id)?.title === room.title && rooms.get(room.id)?.online === room.online);

            if (!isSame) {
                return false;
            }

            targets.forEach((target) => {
                target.data = rooms.get(target.data.id);
            });
            recolor(roomColors, targets.flatMap((target) => {
                const { color } = fresh.states[target.data.state];

                return [color, color];
            }));
            stage.requestRender();

            return true;
        },
    };
}
