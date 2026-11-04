import { Group, Vector3 } from 'three';
import { createStage } from '@/scene/stage.js';
import { addBlock, BLOCK_DEPTH, blockSize, BoxBatch, LOOKS, plantTrees, VIEWS } from './blocks.js';

/**
 * The organization as a town (ADR 0052): a building per tenant the user may see,
 * in rows along roads, with a window per room. The layout is only an order, not
 * where the tenants really are.
 */

const SIDE_GAP = 8;
const ROAD_WIDTH = 3;
const MIN_FRONT_GAP = 5;

export function mountOrganizationScene(element, tooltip, data, onSelect) {
    const world = new Group();
    const boxes = new BoxBatch();
    const glass = new BoxBatch();
    const targets = [];
    const sites = data.sites.map((site) => ({ site, ...blockSize(site.rooms.length) }));
    const columns = Math.ceil(Math.sqrt(sites.length));
    const rows = Math.ceil(sites.length / columns);
    const cellWidth = Math.max(...sites.map((site) => site.width)) + SIDE_GAP;
    const height = Math.max(...sites.map((site) => site.height));
    // Rows far enough apart that the front ones don't hide those behind.
    const frontGap = Math.max(MIN_FRONT_GAP, height * 0.8);
    const cellDepth = BLOCK_DEPTH + frontGap + ROAD_WIDTH;
    const rowZ = (row) => (row - (rows - 1) / 2) * cellDepth - (frontGap + ROAD_WIDTH) / 2;
    const groundWidth = columns * cellWidth + 8;
    const groundDepth = rows * cellDepth + 8;

    boxes.add(groundWidth, 0.4, groundDepth, '#a7c58b', 0, -0.2, 0);

    sites.forEach(({ site, height: siteHeight }, index) => {
        const row = Math.floor(index / columns);
        const inRow = row === rows - 1 ? sites.length - row * columns : columns;
        const x = ((inRow - 1) / 2 - (index % columns)) * cellWidth;
        const z = rowZ(row);

        addBlock(boxes, glass, site.rooms, data.states, site.primary ? LOOKS.primary : LOOKS.building, x, z);
        // From the entrance to the road in front.
        boxes.add(2.2, 0.02, frontGap, '#d6d3d1', x, 0.01, z + BLOCK_DEPTH / 2 + frontGap / 2);
        targets.push({ position: new Vector3(x, siteHeight + 1, z), text: site.name, data: site });
    });

    for (let row = 0; row < rows; row++) {
        boxes.add(groundWidth, 0.03, ROAD_WIDTH, '#78716c', 0, 0.015, rowZ(row) + BLOCK_DEPTH / 2 + frontGap + ROAD_WIDTH / 2);
    }

    world.add(boxes.toMesh());
    if (glass.boxes.length > 0) {
        world.add(glass.toMesh(true));
    }
    plantTrees(world, groundWidth, groundDepth);

    return createStage(element, tooltip, {
        world,
        targets,
        radius: Math.max(Math.hypot(groundWidth, groundDepth) / 2, height),
        home: new Vector3(0, height * 0.3, 0),
        panLimit: new Vector3(groundWidth / 2, height, groundDepth / 2),
        views: VIEWS,
        onSelect,
    });
}
