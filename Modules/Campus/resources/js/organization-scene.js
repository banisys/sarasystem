import { Group, Vector3 } from 'three';
import { createStage } from '@/scene/stage.js';
import {
    addBlock,
    addCar,
    addCrosswalk,
    addLamp,
    addPlanter,
    addRoad,
    BLOCK_DEPTH,
    blockSize,
    BoxBatch,
    CITY,
    FACADES,
    plantTrees,
    PRIMARY_ROOF,
    recolor,
    ROAD_WIDTH,
    VIEWS,
} from './blocks.js';

export { keepLive } from './blocks.js';

/**
 * The organization as a town (ADR 0052, ADR 0055): a building per tenant the user
 * may see, on a grid of streets, with a window per room. The layout is only an
 * order, not where the tenants really are.
 */

const SIDE_GAP = 7;
const BACK_GAP = 1.2;
const MIN_FRONT_GAP = 4.5;

export function mountOrganizationScene(element, tooltip, data, onSelect) {
    const world = new Group();
    const boxes = new BoxBatch();
    const glass = new BoxBatch();
    const targets = [];
    const trees = [];
    const sites = data.sites.map((site) => ({ site, ...blockSize(site.rooms.length) }));
    const columns = Math.ceil(Math.sqrt(sites.length));
    const rows = Math.ceil(sites.length / columns);
    const cellWidth = Math.max(...sites.map((site) => site.width)) + SIDE_GAP;
    const height = Math.max(...sites.map((site) => site.height));
    // Rows far enough apart that the front ones don't hide those behind.
    const frontGap = Math.max(MIN_FRONT_GAP, height * 0.55);
    const cellDepth = BACK_GAP + BLOCK_DEPTH + frontGap + ROAD_WIDTH;
    const groundWidth = columns * cellWidth + ROAD_WIDTH + 2;
    const groundDepth = rows * cellDepth + ROAD_WIDTH + 2;
    // A plot and the road in front of it; one more road runs behind the first row.
    const plotZ = (row) => (row - (rows - 1) / 2) * cellDepth + ROAD_WIDTH / 2;
    const plotX = (column) => ((columns - 1) / 2 - column) * cellWidth;
    const roadZs = [plotZ(0) - cellDepth / 2 - ROAD_WIDTH / 2, ...Array.from({ length: rows }, (_, row) => plotZ(row) + cellDepth / 2 - ROAD_WIDTH / 2)];
    const roadXs = Array.from({ length: columns + 1 }, (_, column) => (column - columns / 2) * cellWidth);

    boxes.add(groundWidth, 0.4, groundDepth, CITY.pavement, 0, -0.2, 0);

    roadZs.forEach((z) => addRoad(boxes, 'x', 0, z, groundWidth, roadXs));
    roadXs.forEach((x) => addRoad(boxes, 'z', x, 0, groundDepth, roadZs));

    sites.forEach(({ site, width, height: siteHeight }, index) => {
        const column = index % columns;
        const row = Math.floor(index / columns);
        const x = plotX(column);
        const z = plotZ(row) - cellDepth / 2 + BACK_GAP + BLOCK_DEPTH / 2;
        const facade = FACADES[index % FACADES.length];

        addBlock(boxes, glass, site.rooms, data.states, site.primary ? { ...facade, roof: PRIMARY_ROOF } : facade, x, z);
        targets.push({ position: new Vector3(x, siteHeight + 1.2, z), text: site.name, data: site });

        const curbZ = z + BLOCK_DEPTH / 2 + frontGap - 0.6;

        addPlanter(boxes, width / 2 - 1.6, 0.7, x - width / 4 - 0.8, z + BLOCK_DEPTH / 2 + 1.1);
        addPlanter(boxes, width / 2 - 1.6, 0.7, x + width / 4 + 0.8, z + BLOCK_DEPTH / 2 + 1.1);
        trees.push([x - width / 2, curbZ], [x + width / 2, curbZ]);
        addLamp(boxes, x, curbZ - 0.1);
    });

    // A plot without a building is a car park.
    for (let index = sites.length; index < columns * rows; index++) {
        const x = plotX(index % columns);
        const z = plotZ(rows - 1) - ROAD_WIDTH / 2;

        boxes.add(cellWidth - ROAD_WIDTH - 1, 0.03, cellDepth - ROAD_WIDTH - 1, CITY.parking, x, 0.015, z);

        for (let car = -1; car <= 1; car++) {
            addCar(boxes, 'z', x + car * 1.8, z, index + car + 1);
        }
    }

    // Traffic: every plot's road and side street has a car, always in the same place.
    roadZs.forEach((z, row) => {
        for (let column = 0; column < columns; column++) {
            addCar(boxes, 'x', plotX(column) + cellWidth * 0.22 * (column % 2 ? 1 : -1), z + (row % 2 ? 1 : -1), row + column);
        }
    });
    roadXs.forEach((x, column) => {
        for (let row = 0; row < rows; row++) {
            addCar(boxes, 'z', x + (column % 2 ? 1 : -1), plotZ(row) - cellDepth * 0.2, row + column + 2);
        }
    });

    roadZs.slice(1).forEach((z) => roadXs.slice(0, -1).forEach((x) => addCrosswalk(boxes, 'x', x + ROAD_WIDTH / 2 + 1, z)));

    world.add(boxes.toMesh());
    const windows = glass.boxes.length > 0 ? glass.toMesh(true) : null;
    if (windows) {
        world.add(windows);
    }
    plantTrees(world, trees);

    const stage = createStage(element, tooltip, {
        world,
        targets,
        radius: Math.max(Math.hypot(groundWidth, groundDepth) / 2, height),
        home: new Vector3(0, height * 0.3, 0),
        panLimit: new Vector3(groundWidth / 2, height, groundDepth / 2),
        views: VIEWS,
        onSelect,
    });

    return {
        ...stage,
        /**
         * The same tenants and rooms in a new state, in place. False when they
         * changed: the town has to be built again.
         */
        update(fresh) {
            const isSame = fresh.sites.length === targets.length
                && targets.every(({ data: site }, index) => fresh.sites[index].id === site.id
                    && fresh.sites[index].name === site.name
                    && fresh.sites[index].rooms.length === site.rooms.length);

            if (!isSame) {
                return false;
            }

            targets.forEach((target, index) => {
                target.data = fresh.sites[index];
            });
            if (windows) {
                recolor(windows, fresh.sites.flatMap((site) => site.rooms.map((room) => fresh.states[room.state].color)));
            }
            stage.requestRender();

            return true;
        },
    };
}
