import {
    DirectionalLight,
    HemisphereLight,
    PerspectiveCamera,
    Scene,
    Vector3,
    WebGLRenderer,
} from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const MAX_PIXEL_RATIO = 1.5;
const PICK_RADIUS = 40;
const MOVE_MS = 700;
const ZOOM_MS = 200;

/**
 * What every 3D view shares (ADR 0049, ADR 0051): camera and full control,
 * names over the things in the scene and picking them, clean-up. Frames are
 * drawn only while something moves, so an open view uses no GPU when still.
 *
 * @param {HTMLElement} element where the canvas goes; holds the tooltip
 * @param {HTMLElement} tooltip the box for a name, also copied for «نام همه»
 * @param {object} options
 * @param {import('three').Object3D} options.world what to show
 * @param {{position: Vector3, text: string, data?: any}[]} options.targets what has a name and can be picked
 * @param {number} options.radius how far the camera must see around `home`
 * @param {Vector3} options.home what the camera looks at
 * @param {Vector3} options.panLimit how far panning may go from the origin
 * @param {Record<string, Vector3>} options.views ready-made views: directions to look from; needs `corner`
 * @param {(camera: PerspectiveCamera) => void} [options.beforeRender]
 * @param {(data: any) => void} [options.onSelect] a target was picked, or null
 */
export function createStage(element, tooltip, { world, targets, radius, home, panLimit, views, beforeRender, onSelect }) {
    const renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, MAX_PIXEL_RATIO));
    renderer.domElement.style.display = 'block';
    element.prepend(renderer.domElement);

    const scene = new Scene();
    scene.add(new HemisphereLight('#ffffff', '#a8a29e', 2.2));
    const sun = new DirectionalLight('#ffffff', 1.6);
    sun.position.set(6, 12, 8);
    scene.add(sun);
    scene.add(world);

    const camera = new PerspectiveCamera(32, 1, 0.1, 1000);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.12;
    controls.zoomToCursor = true;
    controls.autoRotateSpeed = 1.2;
    controls.minDistance = radius * 0.25;
    controls.maxDistance = radius * 6;
    // Above the floor, all the way round.
    controls.maxPolarAngle = Math.PI * 0.49;
    controls.target.copy(home);
    controls.listenToKeyEvents(window);

    let frame = null;
    let controlsFrame = null;
    let moveFrame = null;
    let pinned = null;
    let shown = null;
    let dragging = false;
    let labels = [];

    const render = () => {
        frame = null;
        beforeRender?.(camera);
        renderer.render(scene, camera);
        placeTooltip();
        labels.forEach((label) => place(label.element, label.target));
    };
    const requestRender = () => {
        frame ??= requestAnimationFrame(render);
    };

    const screenPosition = (target) => {
        const projected = target.position.clone().project(camera);

        return {
            x: ((projected.x + 1) / 2) * element.clientWidth,
            y: ((1 - projected.y) / 2) * element.clientHeight,
        };
    };

    function place(box, target) {
        const { x, y } = screenPosition(target);
        box.style.left = `${x}px`;
        box.style.top = `${y}px`;
    }

    function placeTooltip() {
        shown = pinned ?? shown;
        tooltip.hidden = shown === null;

        if (shown !== null) {
            tooltip.textContent = shown.text;
            place(tooltip, shown);
        }
    }

    const nearest = (event) => {
        const bounds = element.getBoundingClientRect();
        const pointer = { x: event.clientX - bounds.left, y: event.clientY - bounds.top };
        let closest = null;
        let closestDistance = PICK_RADIUS;

        for (const target of targets) {
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
        pressedAt = event.button === 0 ? { x: event.clientX, y: event.clientY } : null;
    };
    const onPointerUp = (event) => {
        // A press that didn't drag the camera (the way to read a name on a touch screen).
        if (pressedAt && Math.hypot(event.clientX - pressedAt.x, event.clientY - pressedAt.y) < 6) {
            pinned = nearest(event);
            shown = pinned;
            placeTooltip();
            onSelect?.(pinned?.data ?? null);
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

    /**
     * Runs the controls only while they have something to do: a drag, the
     * glide after it, or turning by itself.
     */
    const runControls = () => {
        controlsFrame = null;

        if (controls.update() || dragging || controls.autoRotate) {
            wakeControls();
        }
    };
    const wakeControls = () => {
        controlsFrame ??= requestAnimationFrame(runControls);
    };

    controls.addEventListener('start', () => {
        cancelAnimationFrame(moveFrame);
        dragging = true;
        wakeControls();
    });
    controls.addEventListener('end', () => {
        dragging = false;
        wakeControls();
    });
    controls.addEventListener('change', () => {
        // Panning stays around the scene, so it can't be lost.
        controls.target.clamp(panLimit.clone().negate(), panLimit);
        requestRender();
        wakeControls();
    });

    const fittedDistance = () => {
        const verticalFov = (camera.fov * Math.PI) / 180;
        const horizontalFov = 2 * Math.atan(Math.tan(verticalFov / 2) * camera.aspect);

        // The floor is seen at an angle, so it needs less height than width.
        return Math.max((radius * 0.95) / Math.sin(verticalFov / 2), (radius * 1.05) / Math.sin(horizontalFov / 2));
    };

    const moveCamera = (position, target, duration) => {
        cancelAnimationFrame(moveFrame);

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            camera.position.copy(position);
            controls.target.copy(target);
            controls.update();
            requestRender();

            return;
        }

        const fromPosition = camera.position.clone();
        const fromTarget = controls.target.clone();
        const started = performance.now();

        const step = (now) => {
            const progress = Math.min((now - started) / duration, 1);
            const eased = 1 - (1 - progress) ** 3;

            camera.position.lerpVectors(fromPosition, position, eased);
            controls.target.lerpVectors(fromTarget, target, eased);
            controls.update();
            requestRender();
            moveFrame = progress < 1 ? requestAnimationFrame(step) : null;
        };

        moveFrame = requestAnimationFrame(step);
    };

    const viewPosition = (view) => home.clone().add(views[view].clone().multiplyScalar(fittedDistance()));

    const resize = () => {
        const width = element.clientWidth;
        const height = element.clientHeight;

        if (width === 0 || height === 0) {
            return;
        }

        renderer.setSize(width, height);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        requestRender();
    };

    const resizes = new ResizeObserver(resize);
    resizes.observe(element);
    resize();

    // A short swing into place from the side.
    camera.position.copy(home).add(new Vector3(0.95, 0.6, 0.35).normalize().multiplyScalar(fittedDistance()));
    controls.update();
    moveCamera(viewPosition('corner'), home, MOVE_MS);

    return {
        requestRender,
        view(name) {
            moveCamera(viewPosition(name), home, MOVE_MS);
        },
        /** Closer below 1, farther above it. */
        zoom(factor) {
            const offset = camera.position.clone().sub(controls.target);
            const distance = Math.min(controls.maxDistance, Math.max(controls.minDistance, offset.length() * factor));

            moveCamera(controls.target.clone().add(offset.setLength(distance)), controls.target.clone(), ZOOM_MS);
        },
        setAutoRotate(isOn) {
            controls.autoRotate = isOn;
            wakeControls();
        },
        /** A name over every target; the same box as the tooltip. */
        setNames(isOn) {
            labels.forEach((label) => label.element.remove());
            labels = isOn
                ? targets.map((target) => {
                    const box = tooltip.cloneNode();
                    box.removeAttribute('x-ref');
                    box.hidden = false;
                    box.textContent = target.text;
                    element.append(box);

                    return { element: box, target };
                })
                : [];
            requestRender();
        },
        /** Forget the picked target. */
        clearSelection() {
            pinned = null;
            shown = null;
            placeTooltip();
        },
        /** Read by the browser check; see ADR 0049. */
        stats: () => ({
            calls: renderer.info.render.calls,
            triangles: renderer.info.render.triangles,
            camera: camera.position.toArray(),
            target: controls.target.toArray(),
        }),
        dispose() {
            cancelAnimationFrame(frame);
            cancelAnimationFrame(controlsFrame);
            cancelAnimationFrame(moveFrame);
            resizes.disconnect();
            controls.stopListenToKeyEvents();
            controls.dispose();
            canvas.removeEventListener('pointerdown', onPointerDown);
            canvas.removeEventListener('pointerup', onPointerUp);
            canvas.removeEventListener('pointermove', onPointerMove);
            canvas.removeEventListener('pointerleave', onPointerLeave);
            labels.forEach((label) => label.element.remove());
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
