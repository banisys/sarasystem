import {
    BoxGeometry,
    Color,
    CylinderGeometry,
    Group,
    InstancedMesh,
    Matrix4,
    Mesh,
    MeshLambertMaterial,
    Vector3,
} from 'three';
import { makePeople } from '@/scene/person-figure.js';
import { createStage } from '@/scene/stage.js';

/**
 * The 3D view of a session (ADR 0049), loaded only when someone asks for it:
 * the room, built from simple shapes; the shared stage does the rest.
 */

const SEAT_WIDTH = 1.7;
const SEAT_DEPTH = 1.8;

const COLORS = {
    room: { floor: '#e7dcc8', wall: '#f4f1ea', board: '#14532d', trim: '#a8a29e', window: '#bae6fd' },
    virtual: { floor: '#1e293b', wall: '#0f172a', board: '#38bdf8', trim: '#334155', window: '#38bdf8' },
    desk: '#c9a26d',
    chair: '#78716c',
};

/**
 * Where the camera looks from in the ready-made views.
 */
const VIEWS = {
    corner: new Vector3(0.65, 0.95, 1).normalize(),
    top: new Vector3(0, 1, 0.001).normalize(),
    front: new Vector3(0, 0.35, -1).normalize(),
    back: new Vector3(0, 0.55, 1).normalize(),
};

export function mountSessionScene(element, tooltip, data) {
    const room = buildRoom(data);
    let wallsOn = true;
    const toWall = new Vector3();

    const stage = createStage(element, tooltip, {
        world: room.group,
        targets: room.targets,
        radius: room.radius,
        home: new Vector3(0, 0.6, 0),
        panLimit: new Vector3(room.width / 2, 2.5, room.depth / 2),
        views: VIEWS,
        beforeRender(camera) {
            // A wall between the camera and the room is out of the way.
            for (const wall of room.walls) {
                wall.group.visible = wallsOn && toWall.subVectors(camera.position, wall.point).dot(wall.normal) <= 0;
            }
        },
    });

    return {
        ...stage,
        setWalls(isOn) {
            wallsOn = isOn;
            stage.requestRender();
        },
        stats: () => ({ ...stage.stats(), walls: room.walls.filter((wall) => wall.group.visible).length }),
    };
}

function buildRoom(data) {
    const group = new Group();
    const palette = data.virtual ? COLORS.virtual : COLORS.room;
    const layout = data.layout;
    const seats = layout ? layout.seats.map((index) => index === null ? null : data.seats[index]) : data.seats;
    const columns = layout?.columns ?? Math.max(2, Math.min(14, Math.ceil(Math.sqrt(seats.length * 1.4))));
    const rows = layout?.rows ?? Math.ceil(seats.length / columns);
    const width = columns * SEAT_WIDTH + 2;
    const depth = rows * SEAT_DEPTH + 4.5;
    const wallHeight = 3.2;
    const add = (mesh) => {
        group.add(mesh);

        return mesh;
    };
    const box = (w, h, d, color, x, y, z) => {
        const mesh = add(new Mesh(new BoxGeometry(w, h, d), new MeshLambertMaterial({ color })));
        mesh.position.set(x, y, z);

        return mesh;
    };

    const wall = (normal, point, w, d) => {
        const wallGroup = new Group();
        const mesh = new Mesh(new BoxGeometry(w, wallHeight, d), new MeshLambertMaterial({ color: palette.wall }));
        mesh.position.set(point.x, wallHeight / 2, point.z);
        wallGroup.add(mesh);
        group.add(wallGroup);

        return { group: wallGroup, normal, point };
    };
    const onWall = (wallGroup, w, h, d, color, x, y, z) => {
        const mesh = box(w, h, d, color, x, y, z);
        group.remove(mesh);
        wallGroup.add(mesh);
    };

    box(width, 0.2, depth, palette.floor, 0, -0.1, 0);

    const walls = [
        wall(new Vector3(0, 0, -1), new Vector3(0, 0, -depth / 2 - 0.1), width + 0.4, 0.2),
        wall(new Vector3(-1, 0, 0), new Vector3(-width / 2 - 0.1, 0, 0), 0.2, depth),
        wall(new Vector3(0, 0, 1), new Vector3(0, 0, depth / 2 + 0.1), width + 0.4, 0.2),
        wall(new Vector3(1, 0, 0), new Vector3(width / 2 + 0.1, 0, 0), 0.2, depth),
    ];

    const boardWidth = Math.min(width * 0.6, 7);
    onWall(walls[0].group, boardWidth + 0.15, 1.45, 0.05, palette.trim, 0, 1.85, -depth / 2 + 0.02);
    onWall(walls[0].group, boardWidth, 1.3, 0.08, palette.board, 0, 1.85, -depth / 2 + 0.04);

    if (!data.virtual) {
        const windows = Math.max(1, Math.floor(depth / 3.5));
        for (let index = 0; index < windows; index++) {
            const z = -depth / 2 + (depth * (index + 0.5)) / windows;
            onWall(walls[1].group, 0.05, 1.3, 1.6, palette.window, -width / 2 + 0.02, 1.9, z);
            onWall(walls[3].group, 0.05, 1.3, 1.6, palette.window, width / 2 - 0.02, 1.9, z);
        }
    }

    const targets = [];
    const frontZ = -depth / 2 + 3.4;

    // The teacher's table and who teaches the session side by side behind it, in their class role's colour.
    box(2, 0.75, 0.8, COLORS.desk, 0, 0.375, -depth / 2 + 1.7);
    const teachers = data.teachers ?? [];
    if (teachers.length) {
        const teacherZ = -depth / 2 + 1;
        const spacing = Math.min(1.2, (width - 2) / teachers.length);
        const teacherXs = teachers.map((_, index) => (index - (teachers.length - 1) / 2) * spacing);
        add(makePeople(teachers.map((person, index) => ({ x: teacherXs[index], z: teacherZ, scale: 0.92, color: person.color }))));
        teachers.forEach((person, index) => {
            targets.push({ position: new Vector3(teacherXs[index], 1.75, teacherZ), text: `${person.name} · ${person.role}` });
        });
    }

    const positions = seats.map((seat, index) => {
        const column = index % columns;
        const row = Math.floor(index / columns);
        const inRow = !layout && row === rows - 1 ? seats.length - row * columns : columns;

        return {
            // The first seat is at the front on the right, as the class faces the board.
            x: ((inRow - 1) / 2 - column) * SEAT_WIDTH,
            z: frontZ + row * SEAT_DEPTH,
            look: seat ? data.statuses[seat.status] : { seated: false, marker: false, color: COLORS.chair },
            seat,
            number: layout ? index + 1 : null,
        };
    });

    const matrix = new Matrix4();
    const instanced = (geometry, items, place, colorOf = null) => {
        if (items.length === 0) {
            return;
        }

        const mesh = add(new InstancedMesh(geometry, new MeshLambertMaterial({ color: '#ffffff' }), items.length));
        items.forEach((item, index) => {
            matrix.makeTranslation(...place(item));
            mesh.setMatrixAt(index, matrix);
            if (colorOf) {
                mesh.setColorAt(index, new Color(colorOf(item)));
            }
        });
    };
    const seated = positions.filter(({ look }) => look.seated);

    instanced(new BoxGeometry(1.15, 0.06, 0.55), positions, ({ x, z }) => [x, 0.72, z - 0.4], () => COLORS.desk);
    instanced(new BoxGeometry(1.05, 0.7, 0.45), positions, ({ x, z }) => [x, 0.35, z - 0.4], () => '#d6bb8f');
    instanced(new BoxGeometry(0.5, 0.45, 0.5), positions, ({ x, z }) => [x, 0.225, z + 0.15], chairColor);
    instanced(new BoxGeometry(0.5, 0.5, 0.07), positions, ({ x, z }) => [x, 0.7, z + 0.4], chairColor);
    // Facing the board, on the chair, their clothes in the colour of their status.
    if (seated.length > 0) {
        add(makePeople(seated.map(({ x, z, look }) => ({ x, z: z + 0.15, scale: 0.92, turn: Math.PI, color: look.color })), 'seated'));
    }
    instanced(new CylinderGeometry(0.22, 0.22, 0.06, 12), positions.filter(({ look }) => look.marker), ({ x, z }) => [x, 0.79, z - 0.4], ({ look }) => look.color);

    const seatNumber = new Intl.NumberFormat('fa-IR', { useGrouping: false });
    for (const { x, z, seat, look, number } of positions) {
        if (!seat) continue;

        targets.push({
            position: new Vector3(x, look.seated ? 1.55 : 1, z + 0.13),
            text: `${number === null ? '' : `صندلی ${seatNumber.format(number)} · `}${seat.name} · ${seat.label}`,
        });
    }

    // The class's other staff (supervisors, supporters, …) stand along the back, facing the board.
    const staff = data.staff ?? [];
    if (staff.length) {
        const staffZ = depth / 2 - 0.9;
        const spacing = Math.min(1.2, (width - 1.5) / staff.length);
        const staffXs = staff.map((_, index) => ((staff.length - 1) / 2 - index) * spacing);
        add(makePeople(staff.map((person, index) => ({ x: staffXs[index], z: staffZ, scale: 0.92, turn: Math.PI, color: person.color }))));
        staff.forEach((person, index) => {
            targets.push({ position: new Vector3(staffXs[index], 1.75, staffZ), text: `${person.name} · ${person.role}` });
        });
    }

    return { group, targets, walls, width, depth, radius: Math.hypot(width, depth) / 2 };
}

/**
 * An empty chair says why by its colour, unless the desk has a marker.
 */
function chairColor({ look }) {
    return look.seated || look.marker ? COLORS.chair : look.color;
}
