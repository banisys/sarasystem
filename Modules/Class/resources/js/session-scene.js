import {
    BoxGeometry,
    CapsuleGeometry,
    Color,
    CylinderGeometry,
    DirectionalLight,
    Group,
    HemisphereLight,
    InstancedMesh,
    Matrix4,
    Mesh,
    MeshLambertMaterial,
    PerspectiveCamera,
    Scene,
    SphereGeometry,
    Vector3,
    WebGLRenderer,
} from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

/**
 * The 3D view of a session (ADR 0049), loaded only when someone asks for it.
 * Everything is built from simple shapes; a frame is drawn only when the camera
 * or the size changes, so an open view uses no GPU while nothing moves.
 */

const SEAT_WIDTH = 1.7;
const SEAT_DEPTH = 1.8;
const MAX_PIXEL_RATIO = 1.5;
const PICK_RADIUS = 40;
const INTRO_MS = 700;

const COLORS = {
    room: { floor: '#e7dcc8', wall: '#f4f1ea', board: '#14532d', trim: '#a8a29e', window: '#bae6fd' },
    virtual: { floor: '#1e293b', wall: '#0f172a', board: '#38bdf8', trim: '#334155', window: '#38bdf8' },
    desk: '#c9a26d',
    chair: '#78716c',
    head: '#f3d6b6',
    teacher: '#6366f1',
};

export function mountSessionScene(stage, tooltip, data) {
    const renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, MAX_PIXEL_RATIO));
    renderer.domElement.style.display = 'block';
    stage.prepend(renderer.domElement);

    const scene = new Scene();
    scene.add(new HemisphereLight('#ffffff', '#a8a29e', 2.2));
    const sun = new DirectionalLight('#ffffff', 1.6);
    sun.position.set(6, 12, 8);
    scene.add(sun);

    const room = buildRoom(data);
    scene.add(room.group);

    const camera = new PerspectiveCamera(32, 1, 0.1, 500);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enablePan = false;
    // The wheel keeps scrolling the modal, and the fitted camera shows every seat.
    controls.enableZoom = false;
    // Stay on the side the room has no walls.
    controls.minAzimuthAngle = Math.PI * 0.05;
    controls.maxAzimuthAngle = Math.PI * 0.45;
    controls.minPolarAngle = Math.PI * 0.25;
    controls.maxPolarAngle = Math.PI * 0.42;
    controls.target.set(0, 0.6, 0);

    const direction = new Vector3(0.65, 0.95, 1).normalize();
    let frame = null;
    let pinned = null;
    let introFrame = null;

    const render = () => {
        frame = null;
        renderer.render(scene, camera);
        placeTooltip();
    };
    const requestRender = () => {
        frame ??= requestAnimationFrame(render);
    };

    const screenPosition = (target) => {
        const projected = target.position.clone().project(camera);

        return {
            x: ((projected.x + 1) / 2) * stage.clientWidth,
            y: ((1 - projected.y) / 2) * stage.clientHeight,
        };
    };

    let shown = null;
    function placeTooltip() {
        shown = pinned ?? shown;
        tooltip.hidden = shown === null;

        if (shown !== null) {
            const { x, y } = screenPosition(shown);
            tooltip.textContent = shown.text;
            tooltip.style.left = `${x}px`;
            tooltip.style.top = `${y}px`;
        }
    }

    const nearest = (event) => {
        const bounds = stage.getBoundingClientRect();
        const pointer = { x: event.clientX - bounds.left, y: event.clientY - bounds.top };
        let closest = null;
        let closestDistance = PICK_RADIUS;

        for (const target of room.targets) {
            const { x, y } = screenPosition(target);
            const distance = Math.hypot(x - pointer.x, y - pointer.y);

            if (distance < closestDistance) {
                closest = target;
                closestDistance = distance;
            }
        }

        return closest;
    };

    let pressedAt = null;
    const onPointerDown = (event) => {
        pressedAt = { x: event.clientX, y: event.clientY };
    };
    const onPointerUp = (event) => {
        // A press that didn't drag the camera (the way to read a seat on a touch screen).
        if (pressedAt && Math.hypot(event.clientX - pressedAt.x, event.clientY - pressedAt.y) < 6) {
            pinned = nearest(event);
            shown = pinned;
            placeTooltip();
        }

        pressedAt = null;
    };
    const onPointerMove = (event) => {
        if (event.pointerType === 'mouse' && event.buttons === 0 && pinned === null) {
            shown = nearest(event);
            placeTooltip();
        }
    };
    const onPointerLeave = () => {
        if (pinned === null) {
            shown = null;
            placeTooltip();
        }
    };

    const canvas = renderer.domElement;
    canvas.addEventListener('pointerdown', onPointerDown);
    canvas.addEventListener('pointerup', onPointerUp);
    canvas.addEventListener('pointermove', onPointerMove);
    canvas.addEventListener('pointerleave', onPointerLeave);
    controls.addEventListener('change', requestRender);

    const fit = () => {
        const width = stage.clientWidth;
        const height = stage.clientHeight;

        if (width === 0 || height === 0) {
            return;
        }

        renderer.setSize(width, height);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();

        const verticalFov = (camera.fov * Math.PI) / 180;
        const horizontalFov = 2 * Math.atan(Math.tan(verticalFov / 2) * camera.aspect);
        // The floor is seen at an angle, so it needs less height than width.
        const distance = Math.max((room.radius * 0.95) / Math.sin(verticalFov / 2), (room.radius * 1.05) / Math.sin(horizontalFov / 2));
        const offset = camera.position.clone().sub(controls.target);

        camera.position.copy(controls.target).add((offset.lengthSq() > 0 ? offset.normalize() : direction).multiplyScalar(distance));
        controls.update();
        requestRender();
    };

    const resizes = new ResizeObserver(fit);
    resizes.observe(stage);
    fit();
    playIntro();

    /**
     * A short swing into place; none for people who asked for less motion.
     */
    function playIntro() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const distance = camera.position.distanceTo(controls.target);
        const from = new Vector3(0.95, 0.6, 0.35).normalize();
        const started = performance.now();

        const step = (now) => {
            const progress = Math.min((now - started) / INTRO_MS, 1);
            const eased = 1 - (1 - progress) ** 3;

            camera.position.copy(controls.target).add(from.clone().lerp(direction, eased).normalize().multiplyScalar(distance));
            controls.update();
            introFrame = progress < 1 ? requestAnimationFrame(step) : null;
        };

        introFrame = requestAnimationFrame(step);
    }

    return {
        /** Measured by the browser check; see ADR 0049. */
        stats: () => ({ calls: renderer.info.render.calls, triangles: renderer.info.render.triangles }),
        dispose() {
            cancelAnimationFrame(frame);
            cancelAnimationFrame(introFrame);
            resizes.disconnect();
            controls.dispose();
            canvas.removeEventListener('pointerdown', onPointerDown);
            canvas.removeEventListener('pointerup', onPointerUp);
            canvas.removeEventListener('pointermove', onPointerMove);
            canvas.removeEventListener('pointerleave', onPointerLeave);
            scene.traverse((object) => {
                object.geometry?.dispose();
                object.material?.dispose();
                object.dispose?.();
            });
            renderer.dispose();
            // Browsers allow few WebGL contexts at a time; give this one back now.
            renderer.forceContextLoss();
            canvas.remove();
            tooltip.hidden = true;
        },
    };
}

function buildRoom(data) {
    const group = new Group();
    const palette = data.virtual ? COLORS.virtual : COLORS.room;
    const seats = data.seats;
    const columns = Math.max(2, Math.min(14, Math.ceil(Math.sqrt(seats.length * 1.4))));
    const rows = Math.ceil(seats.length / columns);
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

    box(width, 0.2, depth, palette.floor, 0, -0.1, 0);
    box(width, wallHeight, 0.2, palette.wall, 0, wallHeight / 2, -depth / 2 - 0.1);
    box(0.2, wallHeight, depth, palette.wall, -width / 2 - 0.1, wallHeight / 2, 0);

    const boardWidth = Math.min(width * 0.6, 7);
    box(boardWidth + 0.15, 1.45, 0.05, palette.trim, 0, 1.85, -depth / 2 + 0.02);
    box(boardWidth, 1.3, 0.08, palette.board, 0, 1.85, -depth / 2 + 0.04);

    if (!data.virtual) {
        const windows = Math.max(1, Math.floor(depth / 3.5));
        for (let index = 0; index < windows; index++) {
            box(0.05, 1.3, 1.6, palette.window, -width / 2 + 0.02, 1.9, -depth / 2 + (depth * (index + 0.5)) / windows);
        }
    }

    const targets = [];
    const frontZ = -depth / 2 + 3.4;

    // The teacher's table and, when the session has one, the teacher.
    box(2, 0.75, 0.8, COLORS.desk, 0, 0.375, -depth / 2 + 1.7);
    if (data.teacher) {
        const teacherX = 0;
        const teacherZ = -depth / 2 + 1;
        const body = add(new Mesh(new CapsuleGeometry(0.24, 0.75, 3, 8), new MeshLambertMaterial({ color: COLORS.teacher })));
        body.position.set(teacherX, 0.62, teacherZ);
        const head = add(new Mesh(new SphereGeometry(0.2, 10, 8), new MeshLambertMaterial({ color: COLORS.head })));
        head.position.set(teacherX, 1.32, teacherZ);
        targets.push({ position: new Vector3(teacherX, 1.6, teacherZ), text: `${data.teacher} · مدرس` });
    }

    const positions = seats.map((seat, index) => {
        const column = index % columns;
        const row = Math.floor(index / columns);
        const inRow = row === rows - 1 ? seats.length - row * columns : columns;

        return {
            // The first seat is at the front on the right, as the class faces the board.
            x: ((inRow - 1) / 2 - column) * SEAT_WIDTH,
            z: frontZ + row * SEAT_DEPTH,
            look: data.statuses[seat.status],
            seat,
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
    instanced(new CapsuleGeometry(0.22, 0.45, 3, 8), seated, ({ x, z }) => [x, 0.8, z + 0.13], ({ look }) => look.color);
    instanced(new SphereGeometry(0.18, 10, 8), seated, ({ x, z }) => [x, 1.3, z + 0.13], () => COLORS.head);
    instanced(new CylinderGeometry(0.22, 0.22, 0.06, 12), positions.filter(({ look }) => look.marker), ({ x, z }) => [x, 0.79, z - 0.4], ({ look }) => look.color);

    for (const { x, z, seat, look } of positions) {
        targets.push({
            position: new Vector3(x, look.seated ? 1.55 : 1, z + 0.13),
            text: `${seat.name} · ${seat.label}`,
        });
    }

    return { group, targets, radius: Math.hypot(width, depth) / 2 };
}

/**
 * An empty chair says why by its colour, unless the desk has a marker.
 */
function chairColor({ look }) {
    return look.seated || look.marker ? COLORS.chair : look.color;
}
