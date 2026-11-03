import {
    ACESFilmicToneMapping,
    CapsuleGeometry,
    CircleGeometry,
    Color,
    CylinderGeometry,
    DirectionalLight,
    Group,
    HemisphereLight,
    MathUtils,
    Mesh,
    MeshBasicMaterial,
    MeshStandardMaterial,
    PerspectiveCamera,
    Scene,
    SphereGeometry,
    SRGBColorSpace,
    TorusGeometry,
    WebGLRenderer,
} from 'three';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';

/**
 * The assistant's 3D robot, built from primitives (no model file to fetch).
 *
 * One WebGL renderer for the page. Its canvas moves between the launcher and
 * the panel header (mount), which keeps the GL context instead of creating a
 * second one. The loop stops while the canvas is off screen or the tab is
 * hidden, and with reduced motion it only renders when the state changes.
 *
 * States: idle (floats, blinks, looks at the pointer), thinking (antenna
 * pulses amber, head tilts), talking (mouth moves, eyes bright), happy
 * (squinting smile, small hop; falls back to idle).
 */

const BRAND_NAVY = new Color('#202759');
const BRAND_GOLD = new Color('#e2b864');
const EYE = new Color('#7fe6ff');
const STATE_GLOW = {
    idle: new Color('#7fe6ff'),
    thinking: new Color('#ffb547'),
    talking: new Color('#6ef2b0'),
    happy: new Color('#7fe6ff'),
};

export function createRobot({ reducedMotion = false } = {}) {
    const renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = SRGBColorSpace;
    renderer.toneMapping = ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.setClearColor(0x000000, 0);

    const canvas = renderer.domElement;
    canvas.className = 'spu-chatbot__canvas';
    canvas.setAttribute('aria-hidden', 'true');

    const scene = new Scene();

    const camera = new PerspectiveCamera(26, 1, 0.1, 50);
    camera.position.set(0, 0.42, 6.4);
    camera.lookAt(0, 0.32, 0);

    scene.add(new HemisphereLight(0xffffff, 0xb9c3ea, 1.35));
    const key = new DirectionalLight(0xffffff, 2.3);
    key.position.set(3, 4, 5);
    scene.add(key);
    const fill = new DirectionalLight(0xdce6ff, 0.9);
    fill.position.set(-2.5, -1.5, 4);
    scene.add(fill);
    const rim = new DirectionalLight(0x8fb6ff, 1.5);
    rim.position.set(-4, 2.5, -3);
    scene.add(rim);
    const parts = buildRobot();
    scene.add(parts.root);

    /* ── State ─────────────────────────────────────────────────────────── */

    let state = 'idle';
    let stateSince = 0;
    let waveUntil = 0;
    let nextBlink = 1.5;
    let blinkEnd = 0;
    let pointer = { x: 0, y: 0 };
    let headTarget = { x: 0, y: 0 };
    let container = null;
    let visible = true;
    let running = false;
    let frame = 0;
    let lastFrameTime = 0;
    const start = performance.now();
    let activeUntil = start + 4_000;
    const glow = STATE_GLOW.idle.clone();

    const resizeObserver = new ResizeObserver(() => resize());
    const intersection = new IntersectionObserver((entries) => {
        visible = entries.some((entry) => entry.isIntersecting);
        schedule();
    });
    document.addEventListener('visibilitychange', () => schedule());

    function resize() {
        if (!container) {
            return;
        }
        const width = Math.max(1, container.clientWidth);
        const height = Math.max(1, container.clientHeight);
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        // A tall stage shows the whole robot; a small round avatar frames the head.
        const avatar = height < 80;
        camera.fov = avatar ? 17 : 28;
        camera.position.set(0, avatar ? 0.95 : 0.45, avatar ? 6.6 : 6.4);
        camera.lookAt(0, avatar ? 0.95 : 0.38, 0);
        camera.updateProjectionMatrix();
        render(performance.now());
    }

    function wake(durationMs = 3_500) {
        activeUntil = Math.max(activeUntil, performance.now() + durationMs);
        schedule();
    }

    function isAnimatedNow(now = performance.now()) {
        return state === 'thinking' || state === 'talking' || now < activeUntil;
    }

    function schedule() {
        const shouldRun = !reducedMotion && visible && !document.hidden && container !== null && isAnimatedNow();
        if (shouldRun && !running) {
            running = true;
            frame = requestAnimationFrame(loop);
        } else if (!shouldRun && running) {
            running = false;
            cancelAnimationFrame(frame);
        }
    }

    function loop(now) {
        if (!running) {
            return;
        }
        if (!isAnimatedNow(now)) {
            running = false;
            render(now);

            return;
        }
        frame = requestAnimationFrame(loop);
        // About 45 frames a second is smooth for a floating robot and kinder
        // to a laptop battery than 120.
        if (now - lastFrameTime < 21) {
            return;
        }
        lastFrameTime = now;
        render(now);
    }

    function render(now) {
        animate((now - start) / 1000);
        renderer.render(scene, camera);
    }

    function animate(time) {
        const sinceState = time - stateSince;
        const motion = reducedMotion ? 0 : 1;

        // Float and sway.
        parts.figure.position.y = Math.sin(time * 1.6) * 0.07 * motion + (state === 'happy' && sinceState < 0.5 ? Math.sin(sinceState * Math.PI * 2) * 0.12 * motion : 0);
        parts.figure.rotation.z = Math.sin(time * 0.9) * 0.025 * motion;
        parts.shadow.scale.setScalar(1 - parts.figure.position.y * 0.6);
        parts.shadow.material.opacity = 0.2 - parts.figure.position.y * 0.25;
        parts.thruster.material.opacity = 0.35 + Math.sin(time * 6) * 0.1 * motion;

        // Head follows the pointer; thinking tilts it.
        const thinkingTilt = state === 'thinking' ? 0.18 : 0;
        parts.head.rotation.y = MathUtils.lerp(parts.head.rotation.y, headTarget.y, 0.08);
        parts.head.rotation.x = MathUtils.lerp(parts.head.rotation.x, headTarget.x - (state === 'thinking' ? 0.08 : 0), 0.08);
        parts.head.rotation.z = MathUtils.lerp(parts.head.rotation.z, thinkingTilt + Math.sin(time * 1.3) * 0.02 * motion, 0.06);

        // Blink.
        if (time > nextBlink) {
            blinkEnd = time + 0.12;
            nextBlink = time + 2.4 + Math.random() * 3;
        }
        const blinking = time < blinkEnd;
        const happy = state === 'happy' && sinceState < 2.2;
        const eyeScale = blinking ? 0.12 : happy ? 0.45 : 1;
        for (const eye of parts.eyes) {
            eye.scale.y = MathUtils.lerp(eye.scale.y, eyeScale, blinking ? 0.6 : 0.25);
        }

        // Mouth.
        const talking = state === 'talking';
        const mouthTarget = talking ? 0.55 + Math.abs(Math.sin(time * 14)) * 0.9 : happy ? 1.35 : 1;
        parts.mouth.scale.y = MathUtils.lerp(parts.mouth.scale.y, mouthTarget, 0.35);

        // Antenna and chest light take the state's color.
        glow.lerp(STATE_GLOW[state] ?? STATE_GLOW.idle, 0.08);
        const pulseSpeed = state === 'thinking' ? 7 : 2.4;
        const pulse = 0.65 + (Math.sin(time * pulseSpeed) * 0.5 + 0.5) * 1.6 * (motion || 0.4);
        parts.antennaBall.material.emissive.copy(glow);
        parts.antennaBall.material.emissiveIntensity = pulse;
        parts.chestLight.material.emissive.copy(glow);
        parts.chestLight.material.emissiveIntensity = 0.6 + pulse * 0.5;
        parts.antenna.rotation.y = state === 'thinking' ? time * 3 : MathUtils.lerp(parts.antenna.rotation.y % (Math.PI * 2), 0, 0.05);
        for (const eye of parts.eyes) {
            eye.material.emissiveIntensity = talking ? 2.6 : 1.9;
        }

        // Arms: a gentle swing, or a wave.
        const waving = time < waveUntil;
        const swing = Math.sin(time * 1.8) * 0.06 * motion;
        parts.leftArm.rotation.z = MathUtils.lerp(parts.leftArm.rotation.z, 0.12 + swing, 0.1);
        parts.rightArm.rotation.z = MathUtils.lerp(
            parts.rightArm.rotation.z,
            waving ? -2.5 + Math.sin(time * 12) * 0.35 : -0.12 - swing,
            waving ? 0.25 : 0.1,
        );
        parts.leftArm.rotation.x = state === 'thinking' ? -0.5 : MathUtils.lerp(parts.leftArm.rotation.x, 0, 0.1);

        if (state === 'happy' && sinceState > 2.2) {
            state = 'idle';
        }
    }

    /* ── API ───────────────────────────────────────────────────────────── */

    return {
        mount(target) {
            if (!target || target === container) {
                return;
            }
            if (container) {
                resizeObserver.unobserve(container);
                intersection.unobserve(container);
            }
            container = target;
            container.append(canvas);
            resizeObserver.observe(container);
            intersection.observe(container);
            resize();
            wake(4_000);
        },
        setState(next) {
            if (!STATE_GLOW[next] || next === state) {
                return;
            }
            state = next;
            stateSince = (performance.now() - start) / 1000;
            if (reducedMotion) {
                // Let the colors settle, then draw the still frame.
                glow.copy(STATE_GLOW[next]);
                render(performance.now());
            } else {
                wake(4_000);
            }
        },
        wave() {
            waveUntil = (performance.now() - start) / 1000 + 1.4;
            wake(2_500);
        },
        lookAt(clientX, clientY) {
            if (!container || reducedMotion) {
                return;
            }
            const rect = container.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 3;
            pointer = {
                x: MathUtils.clamp((clientX - cx) / (window.innerWidth / 2), -1, 1),
                y: MathUtils.clamp((clientY - cy) / (window.innerHeight / 2), -1, 1),
            };
            headTarget = { y: pointer.x * 0.55, x: pointer.y * 0.32 };
            wake(2_000);
        },
        dispose() {
            running = false;
            cancelAnimationFrame(frame);
            resizeObserver.disconnect();
            intersection.disconnect();
            renderer.dispose();
            canvas.remove();
        },
    };
}

function buildRobot() {
    const shell = new MeshStandardMaterial({ color: 0xf6f8fd, roughness: 0.22, metalness: 0.08 });
    const visor = new MeshStandardMaterial({ color: 0x121a44, roughness: 0.14, metalness: 0.38 });
    const navy = new MeshStandardMaterial({ color: BRAND_NAVY, roughness: 0.4, metalness: 0.35 });
    const gold = new MeshStandardMaterial({ color: BRAND_GOLD, roughness: 0.28, metalness: 0.75 });
    const eyeMaterial = () => new MeshStandardMaterial({ color: EYE, emissive: EYE, emissiveIntensity: 1.9, roughness: 0.2 });

    const root = new Group();

    // Head.
    const head = new Group();
    head.position.y = 0.9;
    root.add(head);

    head.add(new Mesh(new RoundedBoxGeometry(1.3, 1.02, 1.02, 6, 0.34), shell));

    const face = new Mesh(new RoundedBoxGeometry(1.04, 0.7, 0.12, 5, 0.06), visor);
    face.position.z = 0.47;
    head.add(face);

    const eyes = [-0.23, 0.23].map((x) => {
        const eye = new Mesh(new CapsuleGeometry(0.075, 0.12, 6, 14), eyeMaterial());
        eye.position.set(x, 0.05, 0.545);
        head.add(eye);

        return eye;
    });

    const mouth = new Mesh(new TorusGeometry(0.11, 0.022, 10, 28, Math.PI), eyeMaterial());
    mouth.rotation.z = Math.PI;
    mouth.position.set(0, -0.15, 0.545);
    head.add(mouth);

    for (const x of [-0.38, 0.38]) {
        const cheek = new Mesh(new CircleGeometry(0.055, 20), new MeshBasicMaterial({ color: 0xff8fb3, transparent: true, opacity: 0.55 }));
        cheek.position.set(x, -0.1, 0.54);
        head.add(cheek);
    }

    for (const x of [-0.69, 0.69]) {
        const ear = new Mesh(new CylinderGeometry(0.17, 0.17, 0.12, 32), gold);
        ear.rotation.z = Math.PI / 2;
        ear.position.x = x;
        head.add(ear);
        const earCap = new Mesh(new CylinderGeometry(0.09, 0.09, 0.14, 24), navy);
        earCap.rotation.z = Math.PI / 2;
        earCap.position.x = x * 1.04;
        head.add(earCap);
    }

    const antenna = new Group();
    antenna.position.y = 0.5;
    head.add(antenna);
    const stalk = new Mesh(new CylinderGeometry(0.022, 0.03, 0.3, 12), gold);
    stalk.position.y = 0.15;
    antenna.add(stalk);
    const antennaBall = new Mesh(new SphereGeometry(0.08, 24, 16), new MeshStandardMaterial({ color: 0xffffff, emissive: EYE, emissiveIntensity: 1.5, roughness: 0.25 }));
    antennaBall.position.y = 0.33;
    antenna.add(antennaBall);

    // Neck and body.
    const neck = new Mesh(new CylinderGeometry(0.2, 0.25, 0.14, 24), navy);
    neck.position.y = 0.35;
    root.add(neck);

    const body = new Mesh(new CapsuleGeometry(0.44, 0.36, 8, 28), shell);
    body.scale.set(1.08, 0.92, 0.95);
    body.position.y = -0.08;
    root.add(body);

    const badge = new Mesh(new CircleGeometry(0.16, 32), visor);
    badge.position.set(0, 0, 0.425);
    root.add(badge);
    const ring = new Mesh(new TorusGeometry(0.165, 0.022, 12, 40), gold);
    ring.position.set(0, 0, 0.425);
    root.add(ring);
    const chestLight = new Mesh(new SphereGeometry(0.06, 20, 14), new MeshStandardMaterial({ color: 0xffffff, emissive: EYE, emissiveIntensity: 1, roughness: 0.3 }));
    chestLight.position.set(0, 0, 0.44);
    chestLight.scale.z = 0.4;
    root.add(chestLight);

    // Arms, pivoting at the shoulders.
    const makeArm = (side) => {
        const pivot = new Group();
        pivot.position.set(side * 0.53, 0.18, 0);
        const arm = new Mesh(new CapsuleGeometry(0.1, 0.3, 6, 16), shell);
        arm.position.y = -0.22;
        pivot.add(arm);
        const hand = new Mesh(new SphereGeometry(0.115, 20, 16), navy);
        hand.position.y = -0.46;
        pivot.add(hand);
        root.add(pivot);

        return pivot;
    };
    const leftArm = makeArm(1);
    const rightArm = makeArm(-1);

    // Hover glow and the shadow it casts.
    const thruster = new Mesh(new TorusGeometry(0.26, 0.05, 12, 40), new MeshBasicMaterial({ color: EYE, transparent: true, opacity: 0.35 }));
    thruster.rotation.x = Math.PI / 2;
    thruster.position.y = -0.62;
    root.add(thruster);

    const shadow = new Mesh(new CircleGeometry(0.55, 40), new MeshBasicMaterial({ color: BRAND_NAVY, transparent: true, opacity: 0.2, depthWrite: false }));
    shadow.rotation.x = -Math.PI / 2;
    shadow.position.y = -1.0;

    const scene = new Group();
    scene.add(root, shadow);

    return { root: scene, figure: root, head, eyes, mouth, antenna, antennaBall, chestLight, leftArm, rightArm, thruster, shadow };
}
