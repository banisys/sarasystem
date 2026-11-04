import { Group, Vector3 } from 'three';
import { createStage } from '@/scene/stage.js';
import { addBlock, BLOCK_DEPTH, blockSize, BoxBatch, LOOKS, plantTrees, VIEWS } from './blocks.js';

/**
 * A tenant's building (ADR 0051): a block with a window per room, generated
 * from the rooms, and a second, glassy block for those that meet only online.
 * It is a picture of the rooms, not the real building's plan.
 */

const BLOCK_GAP = 5;

export function mountBuildingScene(element, tooltip, data, onSelect) {
    const world = new Group();
    const boxes = new BoxBatch();
    const glass = new BoxBatch();
    const targets = [];
    const blocks = [
        { rooms: data.rooms.filter((room) => !room.online), look: LOOKS.building },
        { rooms: data.rooms.filter((room) => room.online), look: LOOKS.online },
    ].filter(({ rooms }) => rooms.length > 0).map((block) => ({ ...block, ...blockSize(block.rooms.length) }));

    const totalWidth = blocks.reduce((sum, block) => sum + block.width, 0) + BLOCK_GAP * (blocks.length - 1);
    const groundWidth = totalWidth + 10;
    const groundDepth = BLOCK_DEPTH + 12;
    const height = Math.max(...blocks.map((block) => block.height));

    boxes.add(groundWidth, 0.4, groundDepth, '#a7c58b', 0, -0.2, 0);

    let cursor = -totalWidth / 2;
    for (const block of blocks) {
        const x = cursor + block.width / 2;

        // The path from the entrance to the edge of the ground.
        boxes.add(2.2, 0.02, groundDepth / 2 - BLOCK_DEPTH / 2, '#d6d3d1', x, 0.01, (groundDepth / 2 + BLOCK_DEPTH / 2) / 2);

        for (const { room, position } of addBlock(boxes, glass, block.rooms, data.states, block.look, x, 0)) {
            targets.push({ position, text: room.title, data: room });
        }

        cursor += block.width + BLOCK_GAP;
    }

    world.add(boxes.toMesh(), glass.toMesh(true));
    plantTrees(world, groundWidth, groundDepth);

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
